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
