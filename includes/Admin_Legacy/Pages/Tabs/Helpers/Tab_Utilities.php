<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Tab Utilities for CampaignBridge Settings.
 *
 * Provides utility methods for settings tabs including URL generation,
 * state management, and common helper functions used across tab implementations.
 *
 * @package CampaignBridge\Admin\Pages\Tabs\Helpers
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs\Helpers;

use CampaignBridge\Admin\Pages\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tab utilities helper class.
 *
 * Provides common utility methods for settings tabs including URL generation,
 * state checking, and other helper functions.
 */
class Tab_Utilities {
	/**
	 * Check if a specific tab is currently active.
	 *
	 * @since 0.1.0
	 * @param string $tab_slug Tab slug to check.
	 * @return bool True if the tab is active.
	 */
	public static function is_tab_active( string $tab_slug ): bool {
		$current_tab = self::get_current_tab();
		return $current_tab === $tab_slug;
	}

	/**
	 * Get current tab from URL parameters.
	 *
	 * @since 0.1.0
	 * @return string Current tab slug.
	 */
	private static function get_current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::get_default_tab(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab parameter for UI display.
		return $tab;
	}

	/**
	 * Get URL for a specific tab.
	 *
	 * @since 0.1.0
	 * @param string $tab_slug Tab slug.
	 * @return string Tab URL.
	 */
	public static function get_tab_url( string $tab_slug ): string {
		$base_url = admin_url( 'admin.php?page=' . Settings::get_page_slug() );
		return add_query_arg( 'tab', $tab_slug, $base_url );
	}

	/**
	 * Display field errors.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @return void
	 */
	public static function display_field_errors( string $field_name ): void {
		$errors = get_settings_errors( Settings::get_page_slug() );

		if ( empty( $errors ) ) {
			return;
		}

		$field_errors = array_filter( $errors, function( $error ) use ( $field_name ) {
			return isset( $error['setting'] ) && $error['setting'] === $field_name;
		} );

		if ( ! empty( $field_errors ) ) {
			echo '<div class="campaignbridge-field-errors">';
			foreach ( $field_errors as $error ) {
				echo '<p class="campaignbridge-error">' . esc_html( $error['message'] ) . '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Get current admin screen.
	 *
	 * @since 0.1.0
	 * @return \WP_Screen|null Current screen object or null.
	 */
	public static function get_current_screen(): ?\WP_Screen {
		if ( function_exists( 'get_current_screen' ) ) {
			return get_current_screen();
		}
		return null;
	}

	/**
	 * Check if we're on the settings page.
	 *
	 * @since 0.1.0
	 * @return bool True if on settings page.
	 */
	public static function is_settings_page(): bool {
		$screen = self::get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return 'campaignbridge_page_' . Settings::get_page_slug() === $screen->id;
	}

	/**
	 * Sanitize tab slug.
	 *
	 * @since 0.1.0
	 * @param string $tab Tab slug to sanitize.
	 * @return string Sanitized tab slug.
	 */
	public static function sanitize_tab_slug( string $tab ): string {
		return sanitize_key( $tab );
	}

	/**
	 * Validate tab exists.
	 *
	 * @since 0.1.0
	 * @param string $tab Tab slug to validate.
	 * @param array  $registered_tabs Array of registered tabs.
	 * @return bool True if tab exists.
	 */
	public static function is_valid_tab( string $tab, array $registered_tabs ): bool {
		return isset( $registered_tabs[ $tab ] );
	}

	/**
	 * Get default tab slug.
	 *
	 * @since 0.1.0
	 * @return string Default tab slug.
	 */
	public static function get_default_tab(): string {
		return 'general';
	}
}
