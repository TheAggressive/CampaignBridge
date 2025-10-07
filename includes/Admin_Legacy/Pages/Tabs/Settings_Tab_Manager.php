<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Settings Tab Manager for CampaignBridge Admin Interface.
 *
 * Handles tab navigation, registration, state management, and URL generation
 * for the settings page with enhanced functionality for managing multiple
 * settings tabs and their interactions.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs;

use CampaignBridge\Admin\Pages\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Tab Manager class.
 *
 * Handles tab registration, navigation, state management, URL generation,
 * and rendering coordination for the settings page. Provides a centralized
 * system for managing multiple settings tabs with proper validation and
 * user experience patterns.
 */
class Settings_Tab_Manager {
	/**
	 * Registered tabs array.
	 *
	 * @var array<string, array>
	 */
	private static array $registered_tabs = array();

	/**
	 * Default tab slug.
	 */
	private const DEFAULT_TAB = 'general';

	/**
	 * Register a settings tab.
	 *
	 * @since 0.1.0
	 * @param string $slug        Tab slug (identifier).
	 * @param string $label       Tab display label.
	 * @param string $description Tab description.
	 * @param string $class_name  Tab class name that implements Abstract_Settings_Tab.
	 * @return void
	 */
	public static function register_tab( string $slug, string $label, string $description, string $class_name ): void {
		self::$registered_tabs[ $slug ] = array(
			'label'       => $label,
			'description' => $description,
			'class'       => $class_name,
		);
	}

	/**
	 * Get all registered tabs.
	 *
	 * @since 0.1.0
	 * @return array Array of registered tabs.
	 */
	public static function get_registered_tabs(): array {
		return self::$registered_tabs;
	}

	/**
	 * Get the current active tab from URL parameters.
	 *
	 * @since 0.1.0
	 * @return string The current tab slug, defaults to 'general'.
	 */
	public static function get_current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::DEFAULT_TAB; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab parameter for UI display.

		// Validate tab exists in registered tabs.
		if ( ! isset( self::$registered_tabs[ $tab ] ) ) {
			$tab = self::DEFAULT_TAB;
		}

		return $tab;
	}

	/**
	 * Check if a tab is currently active.
	 *
	 * @since 0.1.0
	 * @param string $tab Tab slug to check.
	 * @return bool True if the tab is active, false otherwise.
	 */
	public static function is_tab_active( string $tab ): bool {
		return self::get_current_tab() === $tab;
	}

	/**
	 * Get URL for a specific tab.
	 *
	 * @since 0.1.0
	 * @param string $tab The tab slug.
	 * @return string The URL for the tab.
	 */
	public static function get_tab_url( string $tab ): string {
		$base_url = admin_url( 'admin.php?page=' . Settings::get_page_slug() );
		return add_query_arg( 'tab', $tab, $base_url );
	}

	/**
	 * Get tab label.
	 *
	 * @since 0.1.0
	 * @param string $tab Tab slug.
	 * @return string Tab label or empty string if not found.
	 */
	public static function get_tab_label( string $tab ): string {
		return self::$registered_tabs[ $tab ]['label'] ?? '';
	}

	/**
	 * Get tab description.
	 *
	 * @since 0.1.0
	 * @param string $tab Tab slug.
	 * @return string Tab description or empty string if not found.
	 */
	public static function get_tab_description( string $tab ): string {
		return self::$registered_tabs[ $tab ]['description'] ?? '';
	}

	/**
	 * Get tab class name.
	 *
	 * @since 0.1.0
	 * @param string $tab Tab slug.
	 * @return string Tab class name or empty string if not found.
	 */
	public static function get_tab_class( string $tab ): string {
		return self::$registered_tabs[ $tab ]['class'] ?? '';
	}

	/**
	 * Render tab navigation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_navigation(): void {
		$current_tab = self::get_current_tab();

		?>
		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Settings tabs', 'campaignbridge' ); ?>">
			<?php foreach ( self::$registered_tabs as $tab_slug => $tab_info ) : ?>
				<a href="<?php echo esc_url( self::get_tab_url( $tab_slug ) ); ?>"
					class="nav-tab<?php echo ( $tab_slug === $current_tab ) ? ' nav-tab-active' : ''; ?>"
					aria-current="<?php echo ( $tab_slug === $current_tab ) ? 'page' : 'false'; ?>">
					<?php echo esc_html( $tab_info['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render current tab content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_current_tab(): void {
		$current_tab = self::get_current_tab();
		$tab_class   = self::get_tab_class( $current_tab );

		if ( $tab_class && class_exists( $tab_class ) ) {
			$tab_class::render();
		}
	}

	/**
	 * Initialize default tabs.
	 *
	 * This method should be called during plugin initialization to register default tabs.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init_default_tabs(): void {
		self::register_tab(
			'general',
			__( 'General', 'campaignbridge' ),
			__( 'Basic plugin configuration including sender information and global settings.', 'campaignbridge' ),
			'CampaignBridge\Admin\Pages\Tabs\General_Settings_Tab'
		);

		self::register_tab(
			'providers',
			__( 'Providers', 'campaignbridge' ),
			__( 'Configure email service provider settings, API connections, and integration options.', 'campaignbridge' ),
			'CampaignBridge\Admin\Pages\Tabs\Email_Providers_Settings_Tab'
		);
	}
}
