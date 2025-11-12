<?php // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log,CampaignBridge.Standard.Sniffs.Logging.DirectLogging.DirectLoggingFunction -- Bootstrap error logging before Error_Handler is available.
/**
 * Plugin Name: CampaignBridge
 * Description: A comprehensive WordPress plugin for creating and managing email campaigns with dynamic content from multiple post types. Features include Mailchimp integration, custom email templates, block-based email design, and automated campaign generation. Perfect for newsletters, promotional emails, and content marketing automation.
 *
 * Version: 1.0.2
 *
 * @note This version is automatically synced from package.json via pnpm version:sync
 *
 * Requires at least: 6.5.0
 * Tested up to: 6.8.2
 * Requires PHP: 8.2
 * Author: Aggressive Network, LLC
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: campaignbridge
 * Domain Path: /languages
 * Network: false
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main CampaignBridge plugin bootstrap class.
 *
 * Handles plugin initialization, activation, deactivation, and bootstrapping
 * following modern WordPress development practices.
 *
 * @since 0.1.0
 * @package CampaignBridge
 */
class CampaignBridge_Plugin {

	/**
	 * Plugin file path.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const FILE = __FILE__;

	/**
	 * Plugin version.
	 *
	 * This constant is automatically synced from package.json via pnpm version:sync
	 * Do not edit this value manually - update package.json version instead.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const VERSION = '1.0.2';

	/**
	 * Minimum PHP version required.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const MIN_PHP_VERSION = '8.2.0';

	/**
	 * Minimum WordPress version required.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const MIN_WP_VERSION = '6.5.0';

	/**
	 * Plugin path.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	public static function path(): string {
		return plugin_dir_path( self::FILE );
	}

	/**
	 * Plugin URL.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	public static function url(): string {
		return plugin_dir_url( self::FILE );
	}

	/**
	 * Plugin basename.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	public static function basename(): string {
		return plugin_basename( self::FILE );
	}

	/**
	 * Initialize the plugin.
	 *
	 * Sets up hooks, loads text domain, and bootstraps the plugin.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Register activation/deactivation hooks.
		\register_activation_hook( self::FILE, array( self::class, 'activate' ) );
		\register_deactivation_hook( self::FILE, array( self::class, 'deactivate' ) );

		// Load text domain immediately to prevent just-in-time loading issues.
		self::load_textdomain();

		// Bootstrap plugin.
		self::bootstrap();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function load_textdomain(): void {
		\load_plugin_textdomain(
			'campaignbridge',
			false,
			dirname( self::basename() ) . '/languages'
		);
	}

	/**
	 * Bootstrap the plugin.
	 *
	 * Handles autoloader loading and main plugin initialization.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function bootstrap(): void {
		// Load custom autoloader.
		if ( ! self::load_autoloader() ) {
			return;
		}

		// Initialize plugin with enhanced error handling.
		// Defer initialization until WordPress is fully loaded and translations are ready.
		\add_action(
			'init',
			function () {
				try {
					new \CampaignBridge\Plugin();
				} catch ( \Exception $e ) {
					self::handle_bootstrap_error( $e );
				}
			}
		);
	}

	/**
	 * Load the custom autoloader.
	 *
	 * @since 0.1.0
	 * @return bool True if autoloader loaded successfully, false otherwise.
	 */
	private static function load_autoloader(): bool {
		$autoloader_path = __DIR__ . '/includes/Autoloader.php';

		if ( file_exists( $autoloader_path ) ) {
			require_once $autoloader_path;
			return true;
		}

		// Show error notice if autoloader is missing.
		\add_action(
			'admin_notices',
			function () {
				$class   = 'notice notice-error';
				$message = esc_html__(
					'CampaignBridge: Autoloader not found. Please reinstall the plugin.',
					'campaignbridge'
				);
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}
		);

		return false;
	}

	/**
	 * Handle bootstrap errors.
	 *
	 * @since 0.1.0
	 * @param \Exception $e The exception that occurred.
	 * @return void
	 */
	private static function handle_bootstrap_error( \Exception $e ): void {
		// Enhanced error handling for bootstrap failures.
		\add_action(
			'admin_notices',
			function () use ( $e ) {
				$class   = 'notice notice-error is-dismissible';
				$message = sprintf(
					'<strong>%1$s</strong> %2$s<br><small>%3$s</small>',
					esc_html__( 'CampaignBridge Error:', 'campaignbridge' ),
					esc_html( $e->getMessage() ),
					esc_html__( 'Check the error logs for more details.', 'campaignbridge' )
				);
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}
		);

		// Log the error for debugging (debug only).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CampaignBridge Bootstrap Error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );
		}
	}

	/**
	 * Plugin activation hook.
	 *
	 * Performs setup tasks when the plugin is activated.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function activate(): void {
		// Check PHP version compatibility.
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			self::deactivate_and_die(
				sprintf(
					'CampaignBridge requires PHP %s or higher. Your current version is %s.',
					self::MIN_PHP_VERSION,
					PHP_VERSION
				)
			);
		}

		// Check WordPress version compatibility.
		if ( version_compare( \get_bloginfo( 'version' ), self::MIN_WP_VERSION, '<' ) ) {
			self::deactivate_and_die(
				sprintf(
					'CampaignBridge requires WordPress %s or higher. Your current version is %s.',
					self::MIN_WP_VERSION,
					\get_bloginfo( 'version' )
				)
			);
		}

		// Grant custom capability to administrators.
		$admin_role = \get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( 'campaignbridge_manage' ) ) {
			$admin_role->add_cap( 'campaignbridge_manage' );
		}

		// Flush rewrite rules on activation.
		\flush_rewrite_rules();

		// Log activation (debug only).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CampaignBridge plugin activated successfully.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,CampaignBridge.Sniffs.DirectLogging.DirectLoggingFunction -- Plugin lifecycle logging before Error_Handler is available.
		}
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * Performs cleanup tasks when the plugin is deactivated.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function deactivate(): void {
		// Flush rewrite rules on deactivation.
		\flush_rewrite_rules();

		// Log deactivation (debug only).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( __( 'CampaignBridge plugin deactivated.', 'campaignbridge' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,CampaignBridge.Sniffs.DirectLogging.DirectLoggingFunction -- Plugin lifecycle logging before Error_Handler is available.
		}
	}

	/**
	 * Deactivate plugin and show error message.
	 *
	 * @since 0.1.0
	 * @param string $message Error message to display.
	 * @return void
	 */
	private static function deactivate_and_die( string $message ): void {
		\deactivate_plugins( self::basename() );
		\wp_die(
			esc_html(
				$message,
			),
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}
}

// Initialize the plugin.
CampaignBridge_Plugin::init();
