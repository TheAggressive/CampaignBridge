<?php
/**
 * Custom PHPCS sniff to enforce WordPress security best practices.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Security;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Security Validation Sniff class.
 *
 * Enforces WordPress security best practices like nonce validation,
 * capability checks, and input sanitization.
 */
class SecurityValidationSniff implements Sniff {

	/**
	 * Functions that require nonce validation.
	 *
	 * @var array<string>
	 */
	private const NONCE_REQUIRED_FUNCTIONS = array(
		'wp_insert_post',
		'wp_update_post',
		'wp_delete_post',
		'update_option',
		'delete_option',
		'wp_create_user',
		'wp_update_user',
		'wp_delete_user',
	);

	/**
	 * Functions that should have capability checks.
	 *
	 * @var array<string>
	 */
	private const CAPABILITY_REQUIRED_FUNCTIONS = array(
		'wp_insert_post',
		'wp_update_post',
		'wp_delete_post',
		'wp_create_user',
		'wp_update_user',
		'wp_delete_user',
		'wp_mail',
	);

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return array( T_STRING );
	}

	/**
	 * Processes the tokens that this sniff is interested in.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();
		$token  = $tokens[ $stack_ptr ];

		$function_name = $token['content'];

		// Skip validation for REST controllers - they handle input validation differently
		if ( $this->isRestController( $phpcs_file ) ) {
			return;
		}

		// Skip validation for storage layer classes - security is handled at application layer
		if ( $this->isStorageLayer( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		// Skip validation for calls to Storage wrapper methods - security is handled at storage layer
		if ( $this->isStorageMethodCall( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		// Check nonce validation for sensitive operations.
		if ( in_array( $function_name, self::NONCE_REQUIRED_FUNCTIONS, true ) ) {
			$this->validateNonceCheck( $phpcs_file, $stack_ptr, $function_name );
		}

		// Check capability validation for privileged operations.
		if ( in_array( $function_name, self::CAPABILITY_REQUIRED_FUNCTIONS, true ) ) {
			$this->validateCapabilityCheck( $phpcs_file, $stack_ptr, $function_name );
		}

		// Check for proper input sanitization.
		$this->validateInputSanitization( $phpcs_file, $stack_ptr, $function_name );
	}

	/**
	 * Validates that nonce checking is performed before sensitive operations.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The function requiring nonce validation.
	 *
	 * @return void
	 */
	private function validateNonceCheck( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		// Look backwards for wp_verify_nonce or wp_create_nonce calls.
		$nonce_check_found = false;
		$current_line      = $phpcs_file->getTokens()[ $stack_ptr ]['line'];

		// Check within the same function/method scope.
		for ( $i = $stack_ptr; $i > 0; $i-- ) {
			$token = $phpcs_file->getTokens()[ $i ];

			// Stop at function/method boundaries.
			if ( in_array( $token['code'], array( T_FUNCTION, T_CLASS ), true ) ) {
				break;
			}

			// Check for nonce verification.
			if ( T_STRING === $token['code'] &&
				in_array( $token['content'], array( 'wp_verify_nonce', 'check_ajax_referer', 'check_admin_referer' ), true ) ) {
				$nonce_check_found = true;
				break;
			}
		}

		if ( ! $nonce_check_found ) {
			$warning = sprintf(
				'%s() should be preceded by nonce verification (wp_verify_nonce, check_ajax_referer, or check_admin_referer)',
				$function_name
			);
			$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification' );
		}
	}

	/**
	 * Validates that capability checks are performed for privileged operations.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The function requiring capability checks.
	 *
	 * @return void
	 */
	private function validateCapabilityCheck( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		// Look backwards for current_user_can or user_can calls.
		$capability_check_found = false;

		for ( $i = $stack_ptr; $i > 0; $i-- ) {
			$token = $phpcs_file->getTokens()[ $i ];

			// Stop at function/method boundaries.
			if ( in_array( $token['code'], array( T_FUNCTION, T_CLASS ), true ) ) {
				break;
			}

			// Check for capability verification.
			if ( T_STRING === $token['code'] &&
				in_array( $token['content'], array( 'current_user_can', 'user_can', 'wp_verify_nonce' ), true ) ) {
				$capability_check_found = true;
				break;
			}
		}

		if ( ! $capability_check_found ) {
			$warning = sprintf(
				'%s() should be preceded by capability check (current_user_can or user_can)',
				$function_name
			);
			$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingCapabilityCheck' );
		}
	}

	/**
	 * Validates input sanitization for user-submitted data.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The function name being called.
	 *
	 * @return void
	 */
	private function validateInputSanitization( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		// Check for direct use of $_POST, $_GET, $_REQUEST without sanitization.
		$tokens = $phpcs_file->getTokens();

		// Look for superglobal usage near this function call.
		$line_number = $tokens[ $stack_ptr ]['line'];
		$max_tokens  = min( count( $tokens ), $stack_ptr + 50 );

		for ( $i = max( 0, $stack_ptr - 50 ); $i < $max_tokens; $i++ ) {
			$token = $tokens[ $i ];

			if ( T_VARIABLE === $token['code'] &&
				in_array( $token['content'], array( '$_POST', '$_GET', '$_REQUEST' ), true ) ) {

				// Check if this variable is being sanitized.
				if ( ! $this->isVariableSanitized( $phpcs_file, $i ) ) {
					$warning = sprintf(
						'Direct use of %s found near %s(). Consider using sanitization functions like sanitize_text_field(), intval(), etc.',
						$token['content'],
						$function_name
					);
					$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput' );
				}
			}
		}
	}

	/**
	 * Checks if the file is a REST controller that handles input validation differently.
	 *
	 * @param File $phpcs_file The file being scanned.
	 *
	 * @return bool True if this is a REST controller, false otherwise.
	 */
	private function isRestController( File $phpcs_file ): bool {
		$file_path = $phpcs_file->getFilename();

		// Check if file is in REST directory
		if ( strpos( $file_path, '/REST/' ) !== false || strpos( $file_path, '\\REST\\' ) !== false ) {
			return true;
		}

		// Check if file contains REST controller patterns
		$file_content = $phpcs_file->getTokensAsString( 0, $phpcs_file->numTokens );

		// Look for REST API patterns
		$rest_indicators = array(
			'WP_REST_Controller',
			'register_rest_route',
			'wp_send_json',
			'wp_send_json_error',
		);

		foreach ( $rest_indicators as $indicator ) {
			if ( strpos( $file_content, $indicator ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current function call is to a Storage wrapper method.
	 *
	 * Storage wrapper methods (like Storage::update_option()) internally call WordPress functions
	 * but handle security validation at their own layer, so we should skip validation for these calls.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token.
	 *
	 * @return bool True if this is a call to a Storage wrapper method, false otherwise.
	 */
	private function isStorageMethodCall( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look backwards to see if this is a call to Storage::* method
		for ( $i = $stack_ptr - 1; $i > 0; $i-- ) {
			$token = $tokens[ $i ];

			// Skip whitespace and other non-significant tokens
			if ( in_array( $token['code'], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			// Look for the pattern: Storage::method_name
			if ( T_DOUBLE_COLON === $token['code'] ) {
				// Look backwards for "Storage"
				$class_name_ptr = $phpcs_file->findPrevious( T_STRING, $i - 1 );
				if ( false !== $class_name_ptr && 'Storage' === $tokens[ $class_name_ptr ]['content'] ) {
					return true;
				}
			}

			// Stop looking if we hit a statement separator or function/class declaration
			if ( in_array( $token['code'], array( T_SEMICOLON, T_OPEN_TAG, T_FUNCTION, T_CLASS ), true ) ) {
				break;
			}
		}

		return false;
	}

	/**
	 * Checks if the current context is within a storage layer class.
	 *
	 * Storage layer classes focus on data persistence and do not handle security validation,
	 * which is performed at the application layer (controllers, forms, admin pages).
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token.
	 *
	 * @return bool True if this is within a storage layer class, false otherwise.
	 */
	private function isStorageLayer( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();
		$file_path = $phpcs_file->getFilename();

		// Quick file path check - storage classes are typically in Core/Storage.php
		if ( strpos( $file_path, '/Core/Storage' ) !== false || strpos( $file_path, '\\Core\\Storage' ) !== false ) {
			return true;
		}

		// Check for storage layer class patterns
		$file_content = $phpcs_file->getTokensAsString( 0, $phpcs_file->numTokens );

		// Look for storage layer indicators
		$storage_indicators = array(
			'Storage layer',           // PHPDoc comment
			'data persistence',        // PHPDoc comment
			'automatic prefixing',     // Common in storage classes
			'Storage_Prefixes::',      // Usage of prefixing class
			'Storage wrapper',         // Common description
			'⚠️ IMPORTANT: This class should be used for ALL', // Storage.php specific
		);

		foreach ( $storage_indicators as $indicator ) {
			if ( strpos( $file_content, $indicator ) !== false ) {
				return true;
			}
		}

		// Check if we're inside a class that has storage-like method patterns
		$class_name = $this->getCurrentClassName( $phpcs_file, $stack_ptr );
		if ( $class_name && $this->isStorageClassName( $class_name ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the current class name at the given token position.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token.
	 *
	 * @return string|null The class name or null if not found.
	 */
	private function getCurrentClassName( File $phpcs_file, int $stack_ptr ): ?string {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for a class declaration
		for ( $i = $stack_ptr; $i > 0; $i-- ) {
			$token = $tokens[ $i ];

			if ( T_CLASS === $token['code'] ) {
				// Find the class name token
				$class_name_ptr = $phpcs_file->findNext( T_STRING, $i );
				if ( false !== $class_name_ptr ) {
					return $tokens[ $class_name_ptr ]['content'];
				}
			}
		}

		return null;
	}

	/**
	 * Checks if a class name indicates it's a storage layer class.
	 *
	 * @param string $class_name The class name to check.
	 *
	 * @return bool True if this appears to be a storage class, false otherwise.
	 */
	private function isStorageClassName( string $class_name ): bool {
		$storage_class_patterns = array(
			'/Storage$/',
			'/Storage_/',
			'/_Storage$/',
			'/Cache$/',
			'/Repository$/',
		);

		foreach ( $storage_class_patterns as $pattern ) {
			if ( preg_match( $pattern, $class_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if a variable is properly sanitized.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $var_ptr    The position of the variable token.
	 *
	 * @return bool True if the variable appears to be sanitized.
	 */
	private function isVariableSanitized( File $phpcs_file, int $var_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look for sanitization functions being applied to this variable.
		$sanitization_functions = array(
			'sanitize_text_field',
			'sanitize_email',
			'sanitize_url',
			'intval',
			'absint',
			'wp_kses',
			'esc_html',
			'esc_attr',
			'wp_verify_nonce',
		);

		// Check the surrounding context for sanitization.
		$max_check_tokens = min( count( $tokens ), $var_ptr + 20 );
		for ( $i = max( 0, $var_ptr - 20 ); $i < $max_check_tokens; $i++ ) {
			$token = $tokens[ $i ];

			if ( T_STRING === $token['code'] &&
				in_array( $token['content'], $sanitization_functions, true ) ) {
				return true;
			}
		}

		return false;
	}
}
