<?php
/**
 * Custom PHPCS sniff to enforce usage of Storage class instead of direct WordPress storage functions.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Database;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Storage Usage Sniff class.
 *
 * Detects direct usage of WordPress storage functions that should be replaced
 * with Storage class wrappers for proper prefixing and consistency.
 */
class StorageUsageSniff implements Sniff {

	/**
	 * WordPress storage functions that should be replaced with Storage class methods.
	 *
	 * @var array<string, string>
	 */
	private const FORBIDDEN_FUNCTIONS = array(
		// Options.
		'get_option'           => 'Storage::get_option()',
		'update_option'        => 'Storage::update_option()',
		'add_option'           => 'Storage::add_option()',
		'delete_option'        => 'Storage::delete_option()',

		// Transients.
		'get_transient'        => 'Storage::get_transient()',
		'set_transient'        => 'Storage::set_transient()',
		'delete_transient'     => 'Storage::delete_transient()',

		// Post meta.
		'get_post_meta'        => 'Storage::get_post_meta()',
		'update_post_meta'     => 'Storage::update_post_meta()',
		'add_post_meta'        => 'Storage::add_post_meta()',
		'delete_post_meta'     => 'Storage::delete_post_meta()',

		// User meta.
		'get_user_meta'        => 'Storage::get_user_meta()',
		'update_user_meta'     => 'Storage::update_user_meta()',
		'add_user_meta'        => 'Storage::add_user_meta()',
		'delete_user_meta'     => 'Storage::delete_user_meta()',

		// Cache.
		'wp_cache_get'         => 'Storage::wp_cache_get()',
		'wp_cache_set'         => 'Storage::wp_cache_set()',
		'wp_cache_delete'      => 'Storage::wp_cache_delete()',
		'wp_cache_flush_group' => 'Storage::wp_cache_flush_group()',
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

		// Check if this is a forbidden function.
		if ( isset( self::FORBIDDEN_FUNCTIONS[ $function_name ] ) ) {
			// Check if this is being called on the Storage class.
			if ( $this->isStorageClassCall( $phpcs_file, $stack_ptr ) ) {
				// This is a legitimate Storage class call, don't report.
				return;
			}

			// Check if this is being called on an approved wrapper class
			if ( $this->isApprovedWrapperClassCall( $phpcs_file, $stack_ptr ) ) {
				// This is a call on an approved wrapper class, don't report.
				return;
			}

			// This is a direct WordPress function call, report violation.
			$this->reportViolation( $phpcs_file, $stack_ptr, $function_name );
		}
	}

	/**
	 * Checks if the given token is a function call.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if this is a function call, false otherwise.
	 */
	private function isFunctionCall( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look for opening parenthesis after the function name.
		$next_token = $phpcs_file->findNext( T_WHITESPACE, $stack_ptr + 1, null, true );
		if ( false === $next_token ) {
			return false;
		}

		return T_OPEN_PARENTHESIS === $tokens[ $next_token ]['code'];
	}

	/**
	 * Checks if the function call is being made on the Storage class.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if this is a Storage class call, false otherwise.
	 */
	private function isStorageClassCall( File $phpcs_file, int $stack_ptr ): bool {
		return $this->isClassCallOnApprovedClass(
			$phpcs_file,
			$stack_ptr,
			array(
				'Storage',
				'CampaignBridge\\Core\\Storage',
				'\\CampaignBridge\\Core\\Storage',
			)
		);
	}

	/**
	 * Checks if the function call is being made on an approved wrapper class.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if this is an approved wrapper class call, false otherwise.
	 */
	private function isApprovedWrapperClassCall( File $phpcs_file, int $stack_ptr ): bool {
		$approved_classes = array(
			// Storage class
			'Storage',
			'CampaignBridge\\Core\\Storage',
			'\\CampaignBridge\\Core\\Storage',

			// Error Handler class
			'Error_Handler',
			'CampaignBridge\\Core\\Error_Handler',
			'\\CampaignBridge\\Core\\Error_Handler',

			// Form Security class
			'Form_Security',
			'CampaignBridge\\Admin\\Core\\Forms\\Form_Security',
			'\\CampaignBridge\\Admin\\Core\\Forms\\Form_Security',
		);

		return $this->isClassCallOnApprovedClass( $phpcs_file, $stack_ptr, $approved_classes );
	}

	/**
	 * Checks if the function call is being made on one of the approved classes.
	 *
	 * @param File   $phpcs_file      The file being scanned.
	 * @param int    $stack_ptr       The position of the current token in the stack.
	 * @param array  $approved_classes List of approved class names.
	 *
	 * @return bool True if this is a call on an approved class, false otherwise.
	 */
	private function isClassCallOnApprovedClass( File $phpcs_file, int $stack_ptr, array $approved_classes ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for double colon (::).
		$double_colon_ptr = $phpcs_file->findPrevious( T_DOUBLE_COLON, $stack_ptr - 1 );
		if ( false === $double_colon_ptr ) {
			// No double colon found, this is not a static method call.
			return false;
		}

		// Look backwards from double colon for the class name (immediate previous token).
		$class_name_ptr = $double_colon_ptr - 1;
		if ( $class_name_ptr < 0 || T_STRING !== $tokens[ $class_name_ptr ]['code'] ) {
			return false;
		}

		$class_name      = $tokens[ $class_name_ptr ]['content'];
		$namespace_parts = array();

		// Look backwards from class name for namespace parts.
		$current_ptr = $class_name_ptr - 1;

		// Collect namespace parts (namespace\Class format).
		while ( $current_ptr >= 0 ) {
			$token = $tokens[ $current_ptr ];

			if ( T_NS_SEPARATOR === $token['code'] ) {
				// Found namespace separator, continue.
				--$current_ptr;
				continue;
			} elseif ( T_STRING === $token['code'] ) {
				// Found a string token (part of namespace or class).
				$namespace_parts[] = $token['content'];
				--$current_ptr;
			} else {
				// Stop at any other token type.
				break;
			}
		}

		// Build the full class name (reverse the parts since we collected backwards).
		if ( ! empty( $namespace_parts ) ) {
			$full_class_name = implode( '\\', array_reverse( $namespace_parts ) ) . '\\' . $class_name;
		} else {
			$full_class_name = $class_name;
		}

		// Check if it's an approved class.
		return in_array( $full_class_name, $approved_classes, true );
	}

	/**
	 * Reports a violation for using a forbidden storage function.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The name of the forbidden function.
	 *
	 * @return void
	 */
	private function reportViolation( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		$replacement = self::FORBIDDEN_FUNCTIONS[ $function_name ];

		$error = sprintf(
			'Direct usage of %s() is not allowed. Use %s instead for proper prefixing and consistency.',
			$function_name,
			$replacement
		);

		$phpcs_file->addError(
			$error,
			$stack_ptr,
			'CampaignBridge.Standard.Sniffs.Database.StorageUsage.ForbiddenStorageFunction'
		);
	}
}
