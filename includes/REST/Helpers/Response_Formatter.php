<?php
/**
 * Response Formatter for CampaignBridge REST API.
 *
 * Handles response formatting and data filtering for REST API endpoints
 * with proper security considerations.
 *
 * @package CampaignBridge\REST\Helpers
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Response Formatter class.
 *
 * Provides methods for formatting REST API responses and filtering
 * sensitive data from responses.
 */
class Response_Formatter {
	/**
	 * Format posts response data.
	 *
	 * @param array<int, int> $post_ids Array of post IDs keyed by index.
	 * @return list<array{id: int, label: string}> Formatted posts data.
	 */
	public static function format_posts_response( array $post_ids ): array {
		$items = array();
		foreach ( (array) $post_ids as $pid ) {
			$title_raw     = (string) get_post_field( 'post_title', $pid );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$title_escaped = esc_html( $title_decoded ); // Escape HTML to prevent XSS.
			$items[]       = array(
				'id'    => (int) $pid,
				'label' => $title_escaped,
			);
		}
		return $items;
	}

	/**
	 * Filter sensitive data from settings.
	 *
	 * @param array<string, mixed> $settings Raw settings array.
	 * @return array<string, mixed> Filtered settings with sensitive data redacted.
	 */
	public static function filter_sensitive_settings( array $settings ): array {
		// Redact sensitive fields for REST API responses.
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				// Replace with placeholder to indicate field exists but is hidden.
				$settings[ $field ] = '[REDACTED]';
			}
		}

		return $settings;
	}

	/**
	 * Filter sensitive keys from editor settings.
	 *
	 * @param array<string, mixed> $settings Raw editor settings.
	 * @return array<string, mixed> Filtered settings.
	 */
	public static function filter_editor_settings( array $settings ): array {
		$sensitive_keys = self::get_sensitive_editor_keys();

		foreach ( $sensitive_keys as $key ) {
			unset( $settings[ $key ] );
		}

		return $settings;
	}

	/**
	 * Get sensitive keys that should be removed from editor settings.
	 *
	 * @return array<string> List of sensitive keys to filter out.
	 */
	private static function get_sensitive_editor_keys(): array {
		return array(
			'__experimentalDashboardLink',       // Admin URL.
			'__unstableResolvedAssets',          // Contains URLs, versions, scripts with sensitive data.
			'__experimentalDiscussionSettings',  // Contains avatar URLs and discussion settings.
			'canUpdateBlockBindings',            // Not needed for editor functionality.
		);
	}
}
