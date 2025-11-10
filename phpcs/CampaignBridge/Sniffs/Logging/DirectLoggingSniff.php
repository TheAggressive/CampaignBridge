<?php
/**
 * Custom PHPCS sniff to enforce proper logging patterns.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Logging;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Direct Logging Sniff class.
 *
 * Detects direct usage of logging functions that should use Logger wrapper.
 */
class DirectLoggingSniff implements Sniff {

	/**
	 * Direct logging functions that should be replaced.
	 *
	 * @var array<string>
	 */
	private const DIRECT_LOGGING_FUNCTIONS = array(
		'error_log',
		'trigger_error',
		'user_error',
		// Note: wp_die is excluded as it's appropriate for security validation.
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

		// Check if this is a direct logging function.
		if ( in_array( $function_name, self::DIRECT_LOGGING_FUNCTIONS, true ) ) {
			// Only flag direct function calls, not method definitions or method calls.
			if ( ! $this->is_direct_function_call( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			// Skip if it's within Logger class itself.
			if ( $this->is_within_logger_class( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			// Skip if it's within Error_Handler class (which is the logger).
			if ( $this->is_within_error_handler_class( $phpcs_file, $stack_ptr ) ) {
				return;
			}

			$this->report_direct_logging_violation( $phpcs_file, $stack_ptr, $function_name );
		}
	}

	/**
	 * Checks if the given token is within a Logger class.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within Logger class.
	 */
	private function is_within_logger_class( File $phpcs_file, int $stack_ptr ): bool {
		return $this->is_within_class_by_name( $phpcs_file, $stack_ptr, 'Logger' );
	}

	/**
	 * Checks if the given token is within Error_Handler class.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within Error_Handler class.
	 */
	private function is_within_error_handler_class( File $phpcs_file, int $stack_ptr ): bool {
		return $this->is_within_class_by_name( $phpcs_file, $stack_ptr, 'Error_Handler' );
	}

	/**
	 * Checks if the token represents a direct function call.
	 *
	 * Only flags direct function calls like trigger_error(), not:
	 * - Method definitions: public function trigger_error()
	 * - Method calls: $obj->trigger_error()
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if it's a direct function call.
	 */
	private function is_direct_function_call( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Check if followed by opening parenthesis (function call).
		$next_token = $phpcs_file->findNext( T_WHITESPACE, $stack_ptr + 1, null, true );
		if ( false === $next_token || T_OPEN_PARENTHESIS !== $tokens[ $next_token ]['code'] ) {
			return false; // Not a function call.
		}

		// Check if preceded by -> (method call).
		$prev_token = $phpcs_file->findPrevious( T_WHITESPACE, $stack_ptr - 1, null, true );
		if ( false !== $prev_token && T_OBJECT_OPERATOR === $tokens[ $prev_token ]['code'] ) {
			return false; // Method call.
		}

		// Check if preceded by "function" keyword (method definition).
		for ( $i = $stack_ptr - 1; $i >= 0; $i-- ) {
			$token = $tokens[ $i ];

			// Stop at statement boundaries.
			if ( T_SEMICOLON === $token['code'] || T_OPEN_CURLY_BRACKET === $token['code'] ) {
				break;
			}

			if ( T_FUNCTION === $token['code'] ) {
				return false; // Function/method definition.
			}
		}

		return true; // Direct function call.
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
		$tokens = $phpcs_file->getTokens();

		// Look backwards for class declaration.
		for ( $i = $stack_ptr; $i >= 0; $i-- ) {
			$token = $tokens[ $i ];

			if ( T_CLASS === $token['code'] ) {
				// Found a class, check if it's our logger class.
				$class_name_token = $phpcs_file->findNext( T_STRING, $i + 1 );
				if ( false !== $class_name_token ) {
					$found_class_name = $tokens[ $class_name_token ]['content'];
					return $found_class_name === $class_name;
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
	 * Reports a violation for using direct logging functions.
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int    $stack_ptr    The position of the current token in the stack.
	 * @param string $function_name The name of the forbidden function.
	 *
	 * @return void
	 */
	private function report_direct_logging_violation( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		$replacement = 'Error_Handler::log()';

		$error = sprintf(
			'Direct logging function %s() is not allowed. Use %s instead for consistent logging.',
			$function_name,
			$replacement
		);

		$phpcs_file->addWarning(
			$error,
			$stack_ptr,
			'CampaignBridge.Standard.Sniffs.Logging.DirectLogging.DirectLoggingFunction'
		);
	}
}
