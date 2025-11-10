<?php
/**
 * Custom autoloader for CampaignBridge
 * Handles WordPress naming conventions and PSR-4 autoloading
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CampaignBridge Autoloader Class
 *
 * Provides secure, performant PSR-4 autoloading with WordPress integration.
 * Class-based design for better organization and testability while maintaining
 * simple procedural registration interface.
 *
 * @since 1.0.0
 */
class CampaignBridge_Autoloader {

	/**
	 * Namespace prefix for CampaignBridge classes.
	 */
	private const NAMESPACE_PREFIX = 'CampaignBridge\\';

	/**
	 * Base directory for class files.
	 */
	private const BASE_DIRECTORY = __DIR__;

	/**
	 * Maximum allowed file path length.
	 */
	private const MAX_PATH_LENGTH = 500;

	/**
	 * Cache for resolved class-to-file mappings.
	 *
	 * @var array<string, string>
	 */
	private static array $class_map = array();

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function register(): bool {
		return spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Unregister the autoloader.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function unregister(): bool {
		return spl_autoload_unregister( array( self::class, 'load' ) );
	}

	/**
	 * Main autoloader method.
	 *
	 * @param string $class_name The fully-qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		// Only handle CampaignBridge classes.
		if ( ! self::is_campaignbridge_class( $class_name ) ) {
			return;
		}

		// Check cache first for performance.
		if ( self::load_from_cache( $class_name ) ) {
			return;
		}

		// Attempt to load the class.
		$file_path = self::resolve_file_path( $class_name );
		if ( $file_path && self::load_file( $file_path ) ) {
			self::$class_map[ $class_name ] = $file_path;
		}
	}

	/**
	 * Check if class belongs to CampaignBridge namespace.
	 *
	 * @param string $class_name The class name to check.
	 * @return bool True if CampaignBridge class, false otherwise.
	 */
	private static function is_campaignbridge_class( string $class_name ): bool {
		return 0 === strpos( $class_name, self::NAMESPACE_PREFIX );
	}

	/**
	 * Attempt to load class from cache.
	 *
	 * @param string $class_name The class name to load.
	 * @return bool True if loaded from cache, false otherwise.
	 */
	private static function load_from_cache( string $class_name ): bool {
		if ( ! isset( self::$class_map[ $class_name ] ) ) {
			return false;
		}

		$cached_path = self::$class_map[ $class_name ];
		if ( ! file_exists( $cached_path ) ) {
			// Cached file doesn't exist, remove from cache.
			unset( self::$class_map[ $class_name ] );
			return false;
		}

		require_once $cached_path;
		return true;
	}

	/**
	 * Resolve file path for a class name.
	 *
	 * @param string $class_name The class name to resolve.
	 * @return string|null The file path or null if invalid.
	 */
	private static function resolve_file_path( string $class_name ): ?string {
		$relative_class = str_replace( self::NAMESPACE_PREFIX, '', $class_name );

		// Security: Validate the relative path to prevent directory traversal.
		if ( ! self::validate_class_path( $relative_class ) ) {
			self::log_error( "Invalid class path: $relative_class" );
			return null;
		}

		$file_path = self::BASE_DIRECTORY . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Security: Validate absolute file path.
		if ( ! self::validate_file_path( $file_path ) ) {
			self::log_error( "Invalid file path: $file_path" );
			return null;
		}

		return $file_path;
	}

	/**
	 * Load a PHP file if it exists.
	 *
	 * @param string $file_path The file path to load.
	 * @return bool True if loaded successfully, false otherwise.
	 */
	private static function load_file( string $file_path ): bool {
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return true;
		}

		self::log_error( "File not found: $file_path" );
		return false;
	}

	/**
	 * Validate relative class path to prevent directory traversal.
	 *
	 * @param string $relative_class The relative class path.
	 * @return bool True if path is valid, false otherwise.
	 */
	private static function validate_class_path( string $relative_class ): bool {
		// Basic validation - prevent obvious directory traversal.
		if ( false !== strpos( $relative_class, '..' ) ) {
			return false;
		}

		// Ensure path doesn't start with / .
		if ( 0 === strpos( $relative_class, '/' ) ) {
			return false;
		}

		// Allow alphanumeric, underscores, forward slashes, and backslashes.
		if ( ! preg_match( '/^[a-zA-Z0-9_\/\\\\]+$/', $relative_class ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate absolute file path for security.
	 *
	 * @param string $file_path The absolute file path.
	 * @return bool True if path is valid, false otherwise.
	 */
	private static function validate_file_path( string $file_path ): bool {
		// Check path length.
		if ( self::MAX_PATH_LENGTH < strlen( $file_path ) ) {
			return false;
		}

		// Ensure file is within the expected directory.
		$real_file_path = realpath( $file_path );
		$real_base_dir  = realpath( self::BASE_DIRECTORY );

		if ( false === $real_file_path || false === $real_base_dir ) {
			return false;
		}

		// Ensure the file path starts with the base directory.
		if ( 0 !== strpos( $real_file_path, $real_base_dir ) ) {
			return false;
		}

		// Additional security: ensure we're not loading sensitive files.
		$forbidden_patterns = array(
			self::BASE_DIRECTORY . '/../',
			self::BASE_DIRECTORY . '/../../',
			'.git',
			'.env',
			'config.php',
			'wp-config.php',
		);

		foreach ( $forbidden_patterns as $pattern ) {
			if ( false !== strpos( $real_file_path, $pattern ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Log autoloader errors for debugging.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private static function log_error( string $message ): void {
		// Don't log expected validation errors (invalid class/file paths)
		// These are normal during testing and shouldn't clutter logs.
		if ( str_contains( $message, 'Invalid class path:' ) ||
			str_contains( $message, 'Invalid file path:' ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CampaignBridge Autoloader: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,CampaignBridge.Standard.Sniffs.Logging.DirectLogging.DirectLoggingFunction -- Autoloader cannot use Error_Handler class before it's loaded
		}
	}

	/**
	 * Get current class map (for testing/debugging).
	 *
	 * @return array<string, string> Current class map.
	 */
	public static function get_class_map(): array {
		return self::$class_map;
	}

	/**
	 * Clear the class map cache (for testing).
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$class_map = array();
	}
}

// Register the autoloader using the class-based approach.
CampaignBridge_Autoloader::register();
