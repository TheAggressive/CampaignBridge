<?php
/**
 * Custom PHPCS sniff to enforce proper database operations in WordPress.
 *
 * @package CampaignBridge\Sniffs
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Standard\Sniffs\Database;

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
			$phpcs_file->addError( $error, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectSQLFunction' );
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
		// Skip validation if this appears to be an HTTP client operation.
		if ( $this->isHttpClientOperation( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		// Check if this is called on $wpdb.
		if ( ! $this->isCalledOnWpdb( $phpcs_file, $stack_ptr ) ) {
			$warning = sprintf(
				'%s() should be called on $wpdb object for proper WordPress database operations.',
				$method_name
			);
			$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.InvalidWpdbUsage' );
		}

		// Check for prepared statements when needed.
		if ( in_array( $method_name, array( 'query', 'get_var', 'get_row', 'get_col', 'get_results' ), true ) ) {
			$this->validatePreparedStatement( $phpcs_file, $stack_ptr, $method_name );
		}
	}

	/**
	 * Checks if this appears to be an HTTP client operation rather than a database operation.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if this appears to be an HTTP client operation.
	 */
	private function isHttpClientOperation( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Check if we're in a class that contains "Http" or "Client" in the name.
		$class_name = $this->getClassName( $phpcs_file );
		if ( $class_name && ( stripos( $class_name, 'Http' ) !== false || stripos( $class_name, 'Client' ) !== false ) ) {
			return true;
		}

		// Check if the method returns HTTP-related types (WP_Error, array for HTTP responses).
		$method_return_type = $this->getMethodReturnType( $phpcs_file, $stack_ptr );
		if ( $method_return_type && ( stripos( $method_return_type, 'WP_Error' ) !== false || stripos( $method_return_type, 'array' ) !== false ) ) {
			return true;
		}

		// Check if the method contains HTTP-related parameters (URL, args).
		if ( $this->hasHttpParameters( $phpcs_file, $stack_ptr ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the class name containing the current token.
	 *
	 * @param File $phpcs_file The file being scanned.
	 *
	 * @return string|null The class name or null if not found.
	 */
	private function getClassName( File $phpcs_file ): ?string {
		$tokens = $phpcs_file->getTokens();

		// Find the class declaration.
		foreach ( $tokens as $token ) {
			if ( $token['code'] === T_CLASS ) {
				$class_name_ptr = $phpcs_file->findNext( T_STRING, $token['scope_opener'] + 1 );
				if ( $class_name_ptr !== false ) {
					return $tokens[ $class_name_ptr ]['content'];
				}
			}
		}

		return null;
	}

	/**
	 * Gets the return type of the method containing the current token.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return string|null The return type or null if not found.
	 */
	private function getMethodReturnType( File $phpcs_file, int $stack_ptr ): ?string {
		$tokens = $phpcs_file->getTokens();

		// Find the method declaration.
		$method_start = $phpcs_file->findPrevious( T_FUNCTION, $stack_ptr );
		if ( $method_start === false ) {
			return null;
		}

		// Look for return type after the closing parenthesis.
		$closing_paren = $phpcs_file->findNext( T_CLOSE_PARENTHESIS, $method_start );
		if ( $closing_paren === false ) {
			return null;
		}

		$colon = $phpcs_file->findNext( T_COLON, $closing_paren );
		if ( $colon === false ) {
			return null;
		}

		$return_type_end = $phpcs_file->findNext( array( T_WHITESPACE, T_OPEN_CURLY_BRACKET ), $colon, null, true );
		if ( $return_type_end === false ) {
			$return_type_end = $colon + 1;
		}

		$return_type = '';
		for ( $i = $colon + 1; $i < $return_type_end; $i++ ) {
			$return_type .= $tokens[ $i ]['content'];
		}

		return trim( $return_type );
	}

	/**
	 * Checks if the method has HTTP-related parameters.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if HTTP parameters are found.
	 */
	private function hasHttpParameters( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Find the method declaration.
		$method_start = $phpcs_file->findPrevious( T_FUNCTION, $stack_ptr );
		if ( $method_start === false ) {
			return false;
		}

		// Look at the method parameters.
		$opening_paren = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $method_start );
		$closing_paren = $phpcs_file->findNext( T_CLOSE_PARENTHESIS, $opening_paren );

		if ( $opening_paren === false || $closing_paren === false ) {
			return false;
		}

		$method_content = '';
		for ( $i = $opening_paren; $i <= $closing_paren; $i++ ) {
			$method_content .= $tokens[ $i ]['content'];
		}

		// Check for HTTP-related parameter patterns.
		if ( stripos( $method_content, '$url' ) !== false || stripos( $method_content, '$args' ) !== false ) {
			return true;
		}

		return false;
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

		// Only check for SQL injection in functions that actually execute SQL queries.
		if ( ! in_array( $function_name, array( 'query', 'get_var', 'get_row', 'get_col', 'get_results', 'prepare' ), true ) ) {
			return;
		}

		// Look for actual SQL injection patterns: variables concatenated directly into SQL strings.
		$sql_context = $this->isInSQLContext( $phpcs_file, $stack_ptr );

		if ( ! $sql_context ) {
			return;
		}

		// Check for dangerous patterns within the function call.
		$max_tokens = min( count( $tokens ), $stack_ptr + 50 );
		for ( $i = $stack_ptr; $i < $max_tokens; $i++ ) {
			$token = $tokens[ $i ];

			// Stop at end of statement or closing parenthesis.
			if ( T_SEMICOLON === $token['code'] || T_CLOSE_PARENTHESIS === $token['code'] ) {
				break;
			}

			// Check for string concatenation with variables (dangerous pattern).
			if ( T_STRING_CONCAT === $token['code'] ) {
				// Look backwards for variable and forwards for string to confirm SQL injection pattern.
				$prev_token = $phpcs_file->findPrevious( T_WHITESPACE, $i - 1, null, true );
				$next_token = $phpcs_file->findNext( T_WHITESPACE, $i + 1, null, true );

				if ( $prev_token && $next_token &&
					( T_VARIABLE === $tokens[ $prev_token ]['code'] || T_STRING === $tokens[ $prev_token ]['code'] ) &&
					( T_CONSTANT_ENCAPSED_STRING === $tokens[ $next_token ]['code'] ) ) {
					$warning = sprintf(
						'Potential SQL injection: Variable concatenated with SQL string in %s(). Use prepared statements.',
						$function_name
					);
					$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.PotentialSQLInjection' );
					break;
				}
			}
		}
	}

	/**
	 * Checks if the current position is in an SQL context.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool True if in SQL context, false otherwise.
	 */
	private function isInSQLContext( File $phpcs_file, int $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look backwards for SQL keywords or patterns.
		$max_lookback = 20;
		for ( $i = $stack_ptr - 1; $i > max( 0, $stack_ptr - $max_lookback ); $i-- ) {
			$token = $tokens[ $i ];

			// Skip whitespace and comments.
			if ( T_WHITESPACE === $token['code'] || T_COMMENT === $token['code'] || T_DOC_COMMENT === $token['code'] ) {
				continue;
			}

			// Check for SQL keywords.
			if ( T_STRING === $token['code'] ) {
				$content = strtolower( $token['content'] );
				if ( in_array( $content, array( 'select', 'insert', 'update', 'delete', 'where', 'from', 'join' ), true ) ) {
					return true;
				}
			}

			// Stop at function calls or other significant tokens.
			if ( T_OPEN_PARENTHESIS === $token['code'] || T_SEMICOLON === $token['code'] ) {
				break;
			}
		}

		return false;
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
				$phpcs_file->addWarning( $warning, $stack_ptr, 'CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation' );
			}
		}
	}
}
