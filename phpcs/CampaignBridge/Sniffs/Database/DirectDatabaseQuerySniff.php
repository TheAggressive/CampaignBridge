<?php
/**
 * Custom PHPCS sniff to enforce proper database query patterns.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Database;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Direct Database Query Sniff class.
 *
 * Detects direct database queries that should use Storage wrapper or prepared statements.
 */
class DirectDatabaseQuerySniff implements Sniff {

	/**
	 * Direct database methods that should be validated.
	 *
	 * @var array<string>
	 */
	private const DATABASE_METHODS = array(
		'query',
		'get_var',
		'get_row',
		'get_col',
		'get_results',
		'insert',
		'update',
		'delete',
		'prepare',
	);

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return array( T_STRING, T_VARIABLE );
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

		if ( T_VARIABLE === $token['code'] && '$wpdb' === $token['content'] ) {
			$this->validate_wpdb_usage( $phpcs_file, $stack_ptr );
		} elseif ( T_STRING === $token['code'] ) {
			$this->validate_database_method( $phpcs_file, $stack_ptr, $token['content'] );
		}
	}

	/**
	 * Validates $wpdb variable usage.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	private function validate_wpdb_usage( File $phpcs_file, int $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();

		// Look for direct property access or method calls on $wpdb.
		$next_token_ptr = $phpcs_file->findNext( array( T_WHITESPACE, T_COMMENT ), $stack_ptr + 1, null, true );

		if ( false !== $next_token_ptr ) {
			$next_token = $tokens[ $next_token_ptr ];

			// Check for direct property access (like $wpdb->posts).
			if ( T_OBJECT_OPERATOR === $next_token['code'] || T_DOUBLE_ARROW === $next_token['code'] ) {
				$warning = 'Direct $wpdb property access detected. Consider using Storage wrapper for consistency.';
				$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess' );
			}
		}
	}

	/**
	 * Validates database method calls.
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int    $stack_ptr    The position of the current token in the stack.
	 * @param string $method_name  The method name.
	 *
	 * @return void
	 */
	private function validate_database_method( File $phpcs_file, int $stack_ptr, string $method_name ): void {
		if ( ! in_array( $method_name, self::DATABASE_METHODS, true ) ) {
			return;
		}

		// Check if this method is called on $wpdb.
		if ( $this->is_called_on_wpdb( $phpcs_file, $stack_ptr ) ) {
			// This is a direct database call - check if it should use Storage instead.
			if ( ! $this->is_within_storage_wrapper( $phpcs_file, $stack_ptr ) &&
				! $this->is_within_allowed_class( $phpcs_file, $stack_ptr ) ) {
				$this->report_database_violation( $phpcs_file, $stack_ptr, $method_name );
			}
		}
	}

	/**
	 * Checks if the method is called on $wpdb.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if called on $wpdb.
	 */
	private function is_called_on_wpdb( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for -> followed by $wpdb.
		$object_operator_ptr = $phpcs_file->findPrevious( T_OBJECT_OPERATOR, $stack_ptr - 1 );

		if ( false === $object_operator_ptr ) {
			return false;
		}

		$object_ptr = $phpcs_file->findPrevious( T_VARIABLE, $object_operator_ptr - 1 );

		if ( false === $object_ptr ) {
			return false;
		}

		return '$wpdb' === $tokens[ $object_ptr ]['content'];
	}

	/**
	 * Checks if we're within the Storage wrapper class.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within Storage class.
	 */
	private function is_within_storage_wrapper( File $phpcs_file, int $stack_ptr ): bool {
		return $this->is_within_class_by_name( $phpcs_file, $stack_ptr, 'Storage' );
	}

	/**
	 * Checks if we're within an allowed class that can use direct database calls.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if within allowed class.
	 */
	private function is_within_allowed_class( File $phpcs_file, int $stack_ptr ): bool {
		$allowed_classes = array(
			'Performance_Optimizer', // Allowed for cleanup operations.
		);

		foreach ( $allowed_classes as $class_name ) {
			if ( $this->is_within_class_by_name( $phpcs_file, $stack_ptr, $class_name ) ) {
				return true;
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
		$tokens = $phpcs_file->getTokens();

		// Look backwards for class declaration.
		for ( $i = $stack_ptr; $i >= 0; $i-- ) {
			$token = $tokens[ $i ];

			if ( T_CLASS === $token['code'] ) {
				// Found a class, check if it's our target class.
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
	 * Reports a violation for direct database usage.
	 *
	 * @param File   $phpcs_file  The file being scanned.
	 * @param int    $stack_ptr   The position of the current token in the stack.
	 * @param string $method_name The database method name.
	 *
	 * @return void
	 */
	private function report_database_violation( File $phpcs_file, int $stack_ptr, string $method_name ): void {
		$replacement = 'Storage::* methods or prepared statements';

		$warning = sprintf(
			'Direct database method %s() detected. Consider using %s for consistency and security.',
			$method_name,
			$replacement
		);

		$phpcs_file->addWarning(
			$warning,
			$stack_ptr,
			'CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod'
		);
	}
}
