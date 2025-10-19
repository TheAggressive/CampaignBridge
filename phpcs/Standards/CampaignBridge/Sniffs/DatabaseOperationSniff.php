<?php
/**
 * Custom PHPCS sniff to enforce proper database operations in WordPress.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace Standards\CampaignBridge\Sniffs;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Database Operation Sniff class.
 *
 * Validates proper database operations including prepared statements,
 * WordPress database API usage, and query optimization.
 */
class DatabaseOperationSniff implements Sniff {

	/**
	 * Direct SQL query functions that should be avoided.
	 *
	 * @var array<string>
	 */
	private const DIRECT_SQL_FUNCTIONS = array(
		'mysql_query',
		'mysqli_query',
		'PDO::query',
		'PDO::prepare',
	);

	/**
	 * WordPress database methods that are preferred.
	 *
	 * @var array<string>
	 */
	private const WORDPRESS_DB_METHODS = array(
		'get_var',
		'get_row',
		'get_col',
		'get_results',
		'query',
		'prepare',
		'insert',
		'update',
		'delete',
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

		if ( T_STRING === $token['code'] ) {
			$this->processStringToken( $phpcs_file, $stack_ptr, $token['content'] );
		} elseif ( T_VARIABLE === $token['code'] ) {
			$this->processVariableToken( $phpcs_file, $stack_ptr, $token['content'] );
		}
	}

	/**
	 * Processes string tokens (function names).
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $content      The token content.
	 *
	 * @return void
	 */
	private function processStringToken( File $phpcs_file, int $stack_ptr, string $content ): void {
		// Check for direct SQL functions that should be avoided.
		if ( in_array( $content, self::DIRECT_SQL_FUNCTIONS, true ) ) {
			$error = sprintf(
				'Direct SQL function %s() is not allowed. Use WordPress database API ($wpdb) instead.',
				$content
			);
			$phpcs_file->addError( $error, $stack_ptr, 'DirectSQLFunction' );
		}

		// Check for WordPress database method usage.
		if ( in_array( $content, self::WORDPRESS_DB_METHODS, true ) ) {
			$this->validateWordPressDbUsage( $phpcs_file, $stack_ptr, $content );
		}

		// Check for potential SQL injection vulnerabilities.
		$this->checkForSQLInjection( $phpcs_file, $stack_ptr, $content );
	}

	/**
	 * Processes variable tokens.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $content      The token content.
	 *
	 * @return void
	 */
	private function processVariableToken( File $phpcs_file, int $stack_ptr, string $content ): void {
		// Check for direct use of $wpdb without proper methods.
		if ( '$wpdb' === $content ) {
			$this->validateWpdbUsage( $phpcs_file, $stack_ptr );
		}
	}

	/**
	 * Validates WordPress database method usage.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $method_name   The database method name.
	 *
	 * @return void
	 */
	private function validateWordPressDbUsage( File $phpcs_file, int $stack_ptr, string $method_name ): void {
		// Check if this is called on $wpdb.
		if ( ! $this->isCalledOnWpdb( $phpcs_file, $stack_ptr ) ) {
			$warning = sprintf(
				'%s() should be called on $wpdb object for proper WordPress database operations.',
				$method_name
			);
			$phpcs_file->addWarning( $warning, $stack_ptr, 'InvalidWpdbUsage' );
		}

		// Check for prepared statements when needed.
		if ( in_array( $method_name, array( 'query', 'get_var', 'get_row', 'get_col', 'get_results' ), true ) ) {
			$this->validatePreparedStatement( $phpcs_file, $stack_ptr, $method_name );
		}
	}

	/**
	 * Checks if a method is called on the $wpdb object.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if called on $wpdb.
	 */
	private function isCalledOnWpdb( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for -> or :: followed by $wpdb.
		$object_operator_ptr = $phpcs_file->findPrevious( array( T_OBJECT_OPERATOR, T_DOUBLE_COLON ), $stack_ptr - 1 );

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
	 * Validates that prepared statements are used when appropriate.
	 *
	 * @param File   $phpcs_file  The file being scanned.
	 * @param int    $stack_ptr   The position of the current token in the stack.
	 * @param string $method_name The database method name.
	 *
	 * @return void
	 */
	private function validatePreparedStatement( File $phpcs_file, int $stack_ptr, string $method_name ): void {
		$tokens = $phpcs_file->getTokens();

		// Look for prepare() method calls in the same statement.
		$prepare_found = false;
		$line_number   = $tokens[ $stack_ptr ]['line'];

		// Check the current line for prepare usage.
		$token_count = count( $tokens );
		for ( $i = $stack_ptr; $i < $token_count; $i++ ) {
			$token = $tokens[ $i ];

			// Stop at end of line or statement.
			if ( $token['line'] !== $line_number || in_array( $token['code'], array( T_SEMICOLON, T_CLOSE_PARENTHESIS ), true ) ) {
				break;
			}

			if ( T_STRING === $token['code'] && 'prepare' === $token['content'] ) {
				$prepare_found = true;
				break;
			}
		}

		if ( ! $prepare_found ) {
			// Check for variable interpolation or direct SQL strings.
			$this->checkForSQLInjection( $phpcs_file, $stack_ptr, $method_name );
		}
	}

	/**
	 * Checks for potential SQL injection vulnerabilities.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $stack_ptr     The position of the current token in the stack.
	 * @param string $function_name The function name being called.
	 *
	 * @return void
	 */
	private function checkForSQLInjection( File $phpcs_file, int $stack_ptr, string $function_name ): void {
		$tokens = $phpcs_file->getTokens();

		// Look for string concatenation or variable interpolation in SQL contexts.
		$max_tokens = min( count( $tokens ), $stack_ptr + 100 );
		for ( $i = $stack_ptr; $i < $max_tokens; $i++ ) {
			$token = $tokens[ $i ];

			// Stop at end of statement.
			if ( T_SEMICOLON === $token['code'] ) {
				break;
			}

			// Check for string concatenation that might indicate SQL injection.
			if ( T_STRING_CONCAT === $token['code'] ) {
				$warning = sprintf(
					'String concatenation detected in database operation. Consider using prepared statements to prevent SQL injection.',
					$function_name
				);
				$phpcs_file->addWarning( $warning, $stack_ptr, 'PotentialSQLInjection' );
				break;
			}

			// Check for variable interpolation in strings.
			if ( T_DOUBLE_QUOTED_STRING === $token['code'] ||
				T_HEREDOC === $token['code'] ) {
				if ( preg_match( '/\$[a-zA-Z_][a-zA-Z0-9_]*/', $token['content'] ) ) {
					$warning = 'Variable interpolation detected in database operation. Consider using prepared statements to prevent SQL injection.';
					$phpcs_file->addWarning( $warning, $stack_ptr, 'PotentialSQLInjection' );
					break;
				}
			}
		}
	}

	/**
	 * Validates $wpdb usage patterns.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	private function validateWpdbUsage( File $phpcs_file, int $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();

		// Check if $wpdb is used directly in assignments or operations.
		$next_token_ptr = $phpcs_file->findNext( array( T_WHITESPACE, T_COMMENT ), $stack_ptr + 1, null, true );

		if ( false !== $next_token_ptr ) {
			$next_token = $tokens[ $next_token_ptr ];

			// Warn about direct $wpdb manipulation.
			if ( in_array( $next_token['code'], array( T_EQUAL, T_PLUS_EQUAL, T_OBJECT_OPERATOR ), true ) ) {
				$warning = 'Direct manipulation of $wpdb object detected. Use WordPress database API methods instead.';
				$phpcs_file->addWarning( $warning, $stack_ptr, 'DirectWpdbManipulation' );
			}
		}
	}
}
