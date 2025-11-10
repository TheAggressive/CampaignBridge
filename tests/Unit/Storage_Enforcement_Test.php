<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Storage Enforcement Test
 *
 * Ensures all WordPress storage operations use the CampaignBridge Storage wrapper
 * instead of direct WordPress functions. This prevents "oops moments" where
 * direct functions are used instead of properly prefixed operations.
 *
 * @package CampaignBridge\Tests\Unit
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

/**
 * Test that enforces proper usage of Storage wrappers.
 */
class _Storage_Enforcement_Test extends \PHPUnit\Framework\TestCase {

	/**
	 * Functions that should not be used directly.
	 *
	 * @var array<string>
	 */
	private const FORBIDDEN_FUNCTIONS = array(
		'get_option',
		'update_option',
		'add_option',
		'delete_option',
		'get_transient',
		'set_transient',
		'delete_transient',
		'get_post_meta',
		'update_post_meta',
		'add_post_meta',
		'delete_post_meta',
		'get_user_meta',
		'update_user_meta',
		'add_user_meta',
		'delete_user_meta',
		'wp_cache_get',
		'wp_cache_set',
		'wp_cache_delete',
		'wp_cache_flush_group',
	);

	/**
	 * Files that are allowed to use these functions directly.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_FILES = array(
		'includes/Core/Storage.php',
		'includes/Core/Storage_Prefixes.php',
		'uninstall.php',
	);

	/**
	 * Directories that are allowed during migration and testing.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_DIRECTORIES = array(
		'vendor/',
		'node_modules/',
		'tests/',
		'bin/',
	);

	/**
	 * Test that no direct WordPress storage functions are used in plugin code.
	 *
	 * This test scans the entire codebase for direct usage of WordPress storage
	 * functions that should go through the Storage wrapper instead.
	 */
	public function test_no_direct_wordpress_storage_functions(): void {
		$violations = $this->scan_for_storage_violations();

		$this->assertEmpty(
			$violations,
			$this->format_violation_message( $violations )
		);
	}

	/**
	 * Test that Storage wrapper methods exist for all WordPress storage functions.
	 */
	public function test_storage_wrapper_methods_exist(): void {
		$storage_class = 'CampaignBridge\\Core\\Storage';

		$this->assertTrue( class_exists( $storage_class ), 'Storage class must exist' );

		// Test that all expected wrapper methods exist
		$expected_methods = array(
			'get_option',
			'update_option',
			'add_option',
			'delete_option',
			'get_transient',
			'set_transient',
			'delete_transient',
			'get_post_meta',
			'update_post_meta',
			'add_post_meta',
			'delete_post_meta',
			'get_user_meta',
			'update_user_meta',
			'add_user_meta',
			'delete_user_meta',
			'wp_cache_get',
			'wp_cache_set',
			'wp_cache_delete',
			'wp_cache_flush_group',
		);

		foreach ( $expected_methods as $method ) {
			$this->assertTrue(
				method_exists( $storage_class, $method ),
				"Storage class must have {$method} method"
			);

			$this->assertTrue(
				is_callable( array( $storage_class, $method ) ),
				"Storage::{$method} must be callable"
			);
		}
	}

	/**
	 * Test that Storage_Prefixes provides all necessary prefix arrays.
	 */
	public function test_storage_prefixes_provides_all_arrays(): void {
		$prefixes_class = 'CampaignBridge\\Core\\Storage_Prefixes';

		$this->assertTrue( class_exists( $prefixes_class ), 'Storage_Prefixes class must exist' );

		// Test that all expected arrays exist
		$expected_arrays = array(
			'get_all_option_keys',
			'get_all_transient_prefixes',
			'get_all_post_meta_prefixes',
			'get_all_user_meta_prefixes',
		);

		foreach ( $expected_arrays as $method ) {
			$this->assertTrue(
				method_exists( $prefixes_class, $method ),
				"Storage_Prefixes must have {$method} method"
			);

			$result = call_user_func( array( $prefixes_class, $method ) );
			$this->assertIsArray( $result, "{$method} must return an array" );
			$this->assertNotEmpty( $result, "{$method} must return non-empty array" );
		}
	}

	/**
	 * Test that prefixing works correctly and prevents double-prefixing.
	 */
	public function test_prefixing_prevents_double_prefixing(): void {
		$prefixes_class = 'CampaignBridge\\Core\\Storage_Prefixes';

		// Test option key prefixing
		$this->assertEquals(
			'campaignbridge_from_name',
			$prefixes_class::get_option_key( 'from_name' ),
			'New option key should get campaignbridge_ prefix'
		);

		$this->assertEquals(
			'campaignbridge_from_name',
			$prefixes_class::get_option_key( 'campaignbridge_from_name' ),
			'Already prefixed option key should not get double-prefixed'
		);

		$this->assertEquals(
			'campaignbridge_settings',
			$prefixes_class::get_option_key( 'campaignbridge_settings' ),
			'Already prefixed campaignbridge_ key should not get double-prefixed'
		);

		// Test transient key prefixing
		$this->assertEquals(
			'campaignbridge_user_data',
			$prefixes_class::get_transient_key( 'user_data' ),
			'New transient key should get campaignbridge_ prefix'
		);

		$this->assertEquals(
			'campaignbridge_stats',
			$prefixes_class::get_transient_key( 'campaignbridge_stats' ),
			'Already prefixed transient key should not get double-prefixed'
		);

		// Test cache group prefixing
		$this->assertEquals(
			'campaignbridge_queries',
			$prefixes_class::get_cache_group( 'queries' ),
			'New cache group should get campaignbridge_ prefix'
		);

		$this->assertEquals(
			'campaignbridge_users',
			$prefixes_class::get_cache_group( 'campaignbridge_users' ),
			'Already prefixed cache group should not get double-prefixed'
		);
	}

	/**
	 * Test that is_properly_prefixed validation works correctly.
	 */
	public function test_properly_prefixed_validation(): void {
		$prefixes_class = 'CampaignBridge\\Core\\Storage_Prefixes';

		// Test valid prefixed keys
		$this->assertTrue(
			$prefixes_class::is_properly_prefixed( 'campaignbridge_from_name', 'option' ),
			'campaignbridge_from_name should be valid for options'
		);

		$this->assertTrue(
			$prefixes_class::is_properly_prefixed( 'campaignbridge_settings', 'option' ),
			'campaignbridge_settings should be valid for options'
		);

		$this->assertTrue(
			$prefixes_class::is_properly_prefixed( 'campaignbridge_stats', 'transient' ),
			'campaignbridge_stats should be valid for transients'
		);

		$this->assertTrue(
			$prefixes_class::is_properly_prefixed( 'campaignbridge_subject', 'post_meta' ),
			'campaignbridge_subject should be valid for post meta'
		);

		// Test invalid keys
		$this->assertFalse(
			$prefixes_class::is_properly_prefixed( 'unprefixed_option', 'option' ),
			'unprefixed_option should be invalid for options'
		);

		$this->assertFalse(
			$prefixes_class::is_properly_prefixed( 'random_transient', 'transient' ),
			'random_transient should be invalid for transients'
		);
	}

	/**
	 * Scan the codebase for storage violations.
	 *
	 * @return array<array{file: string, line: int, function: string, line_content: string}> Array of violations.
	 */
	private function scan_for_storage_violations(): array {
		$violations = array();

		$project_root = dirname( __DIR__, 2 );
		$iterator     = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $project_root, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}

			$file_path = $file->getPathname();

			if ( $this->is_allowed_file( $file_path ) ) {
				continue;
			}

			$file_violations = $this->scan_file_for_violations( $file_path );
			$violations      = array_merge( $violations, $file_violations );
		}

		return $violations;
	}

	/**
	 * Check if a file is allowed to use forbidden functions.
	 *
	 * @param string $file_path The file path to check.
	 * @return bool True if the file is allowed.
	 */
	private function is_allowed_file( string $file_path ): bool {
		$relative_path = str_replace( dirname( __DIR__, 2 ) . '/', '', $file_path );

		// Check exact file matches
		foreach ( self::ALLOWED_FILES as $allowed ) {
			if ( str_contains( $relative_path, $allowed ) ) {
				return true;
			}
		}

		// Check directory matches
		foreach ( self::ALLOWED_DIRECTORIES as $allowed_dir ) {
			if ( str_starts_with( $relative_path, $allowed_dir ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Scan a single file for storage violations.
	 *
	 * @param string $file_path The file path to scan.
	 * @return array<array{file: string, line: int, function: string, line_content: string}> Array of violations.
	 */
	private function scan_file_for_violations( string $file_path ): array {
		$violations = array();

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return $violations;
		}

		$content = file_get_contents( $file_path );
		$lines   = explode( "\n", $content );

		foreach ( $lines as $line_number => $line ) {
			foreach ( self::FORBIDDEN_FUNCTIONS as $function ) {
				// Enhanced pattern to catch more variations
				$patterns = array(
					'/\b' . preg_quote( $function, '/' ) . '\s*\(/',           // Direct calls: get_option(
					'/\b\\\\' . preg_quote( $function, '/' ) . '\s*\(/',      // Namespaced: \get_option(
					'/\$\w+\s*\(\s*[\'"]' . preg_quote( $function, '/' ) . '[\'"]\s*\)/', // Variable functions: $func('get_option')
					'/call_user_func\s*\(\s*[\'"]' . preg_quote( $function, '/' ) . '[\'"]/', // call_user_func
				);

				foreach ( $patterns as $pattern ) {
					if ( preg_match( $pattern, $line ) ) {
						// Skip if it's a method call (contains :: or ->)
						if ( str_contains( $line, '::' ) || str_contains( $line, '->' ) ) {
							continue 2; // Continue to next pattern
						}

						// Skip if it's inside a comment
						if ( preg_match( '/^\s*\/\//', $line ) || preg_match( '/^\s*\*/', $line ) ) {
							continue 2; // Continue to next pattern
						}

						// Skip if it's inside a string literal
						if ( $this->isInsideStringLiteral( $line, $function ) ) {
							continue 2; // Continue to next pattern
						}

						// Skip if it's a function definition
						if ( preg_match( '/^\s*function\s+' . preg_quote( $function, '/' ) . '\s*\(/', $line ) ) {
							continue 2; // Continue to next pattern
						}

						$violations[] = array(
							'file'         => $file_path,
							'line'         => $line_number + 1,
							'function'     => $function,
							'line_content' => trim( $line ),
						);
					}
				}
			}
		}

		return $violations;
	}

	/**
	 * Format violation array into a readable error message.
	 *
	 * @param array<array{file: string, line: int, function: string, line_content: string}> $violations Array of violations.
	 * @return string Formatted error message.
	 */
	private function format_violation_message( array $violations ): string {
		if ( empty( $violations ) ) {
			return '';
		}

		$message = 'Found ' . count( $violations ) . " storage violations:\n\n";

		foreach ( $violations as $violation ) {
			$relative_path = str_replace( dirname( __DIR__, 2 ) . '/', '', $violation['file'] );
			$message      .= "📁 {$relative_path}:{$violation['line']}\n";
			$message      .= "   Function: {$violation['function']}()\n";
			$message      .= "   Line: {$violation['line_content']}\n";
			$message      .= "   💡 Use: CampaignBridge\\Core\\Storage::{$violation['function']}() instead\n\n";
		}

		$message .= "🔧 To fix these violations:\n";
		$message .= "   1. Replace direct function calls with Storage wrapper methods\n";
		$message .= "   2. Example: get_option('key') → CampaignBridge\\Core\\Storage::get_option('key')\n";
		$message .= "   3. Run tests again to verify fixes\n";

		return $message;
	}

	/**
	 * Check if a function name appears to be inside a string literal.
	 *
	 * This is a simple heuristic to avoid false positives when function names
	 * appear in strings, comments, or other non-executable contexts.
	 *
	 * @param string $line     The line of code to check.
	 * @param string $function The function name to check for.
	 * @return bool True if the function appears to be inside quotes.
	 */
	private function isInsideStringLiteral( string $line, string $function ): bool {
		// Simple check: if the function appears between quotes
		if ( preg_match( '/[\'"][^\'"]*' . preg_quote( $function, '/' ) . '[^\'"]*[\'"]/', $line ) ) {
			return true;
		}

		// Check for function in array keys/values that might be strings
		if ( preg_match( '/[\'"]' . preg_quote( $function, '/' ) . '[\'"]\s*=>/', $line ) ) {
			return true;
		}

		return false;
	}
}
