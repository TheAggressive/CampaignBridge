<?php
/**
 * Custom PHPCS sniff to enforce proper HTTP request patterns.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Http;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Direct HTTP Request Sniff class.
 *
 * Detects direct HTTP requests that should use proper HTTP client wrappers.
 */
class DirectHttpRequestSniff implements Sniff {

	/**
	 * Direct HTTP functions that should be validated.
	 *
	 * @var array<string>
	 */
	private const HTTP_FUNCTIONS = array(
		'wp_remote_get',
		'wp_remote_post',
		'wp_remote_request',
		'wp_remote_head',
		'curl_exec',
		'curl_init',
		'file_get_contents', // Can be used for HTTP.
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

		// Check if this is a direct HTTP function.
		if ( in_array( $function_name, self::HTTP_FUNCTIONS, true ) ) {
			// Skip if it's within allowed classes that handle HTTP requests.
			if ( $this->is_within_allowed_class( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			$this->report_http_violation( $phpcs_file, $stack_ptr, $function_name );
		}
	}

	/**
	 * Checks if we're within an allowed class that can use direct HTTP functions.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within allowed class.
	 */
	private function is_within_allowed_class( File $phpcs_file, int $stack_ptr ): bool {
		$allowed_classes = array(
			'Provider', // Generic provider classes.
		);

		$allowed_class_prefixes = array(
			'Http_Client', // Our HTTP client wrapper classes.
		);

		// Allow in classes that contain "Provider" in their name.
		$class_name = $this->get_current_class_name( $phpcs_file, $stack_ptr );
		if ( false !== $class_name && strpos( $class_name, 'Provider' ) !== false ) {
			return true;
		}

		// Allow in our HTTP client wrapper classes.
		foreach ( $allowed_class_prefixes as $prefix ) {
			if ( false !== $class_name && strpos( $class_name, $prefix ) === 0 ) {
				return true;
			}
		}

		foreach ( $allowed_classes as $allowed_class ) {
			if ( $this->is_within_class_by_name( $phpcs_file, $stack_ptr, $allowed_class ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the current class name at the given position.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return string|false The class name or false if not found.
	 */
	private function get_current_class_name( File $phpcs_file, int $stack_ptr ): string|false {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for class declaration.
		for ( $i = $stack_ptr; $i >= 0; $i-- ) {
			$token = $tokens[ $i ];

			if ( T_CLASS === $token['code'] ) {
				// Found a class, get the class name.
				$class_name_token = $phpcs_file->findNext( T_STRING, $i + 1 );
				if ( false !== $class_name_token ) {
					return $tokens[ $class_name_token ]['content'];
				}
			}

			// Stop at function boundaries or if we go too far back.
			if ( T_FUNCTION === $token['code'] || $i < $stack_ptr - 1000 ) {
				break;
			}
		}

		return false;
	}

	/**
	 * Checks if the given token is within a class by name.
	 *
	 * @param File   $phpcs_file The file being scanned.
	 * @param int    $stack_ptr  The position of the current token in the stack.
	 * @param string $class_name The class name to check for.
	 *
	 * @return bool True if within the specified class.
	 */
	private function is_within_class_by_name( File $phpcs_file, int $stack_ptr, string $class_name ): bool {
		$current_class = $this->get_current_class_name( $phpcs_file, $stack_ptr );
		return $current_class === $class_name;
	}

	/**
	 * Reports a violation for direct HTTP request usage.
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int    $stack_ptr    The position of the current token in the stack.
	 * @param string $function_name The HTTP function name.
	 *
	 * @return void
	 */
	private function report_http_violation( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		$replacement = 'HTTP client wrapper or Provider classes';

		$warning = sprintf(
			'Direct HTTP function %s() detected. Consider using %s for consistency and error handling.',
			$function_name,
			$replacement
		);

		$phpcs_file->addWarning(
			$warning,
			$stack_ptr,
			'CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction'
		);
	}
}
