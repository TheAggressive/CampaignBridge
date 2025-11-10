<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Storage Prefixes for CampaignBridge Plugin
 *
 * Centralized management of all database prefixes to ensure consistency
 * and prevent conflicts with other plugins.
 *
 * @package CampaignBridge\Core
 * @since 0.3.0
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage Prefixes class.
 *
 * Manages all prefixes used for database storage including options,
 * transients, post meta, user meta, and cache groups.
 */
class Storage_Prefixes {

	/**
	 * Main plugin prefix for general options and data.
	 */
	public const PLUGIN_PREFIX = 'campaignbridge_';

	/**
	 * Short prefix for individual settings (more readable).
	 */
	public const SETTINGS_PREFIX = 'campaignbridge_';

	/**
	 * Cache group prefix.
	 */
	public const CACHE_GROUP_PREFIX = 'campaignbridge';

	/**
	 * Standard WordPress cache groups that need prefixing for plugin data.
	 */
	public const STANDARD_CACHE_GROUPS = array(
		'options',     // WordPress options cache.
		'post_meta',   // Post meta cache.
		'user_meta',   // User meta cache.
	);

	/**
	 * Transient prefixes for different data types.
	 */
	public const TRANSIENT_PREFIXES = array(
		'campaignbridge_mc_',           // Mailchimp related.
		'campaignbridge_',              // General plugin transients.
		'campaignbridge_form_rate_limit_', // Form security rate limiting.
		'campaignbridge_performance_',  // Performance monitoring transients.
	);

	/**
	 * Post meta key prefixes.
	 */
	public const POST_META_PREFIXES = array(
		'campaignbridge_template_',     // Email template meta.
		'campaignbridge_subject',       // Subject line.
		'campaignbridge_preheader',     // Preheader text.
		'campaignbridge_audience_tags', // Audience tags.
	);

	/**
	 * User meta key prefixes.
	 */
	public const USER_META_PREFIXES = array(
		'campaignbridge_',           // General user meta.
		'campaignbridge_email_',     // Email related user meta.
		'campaignbridge_template_',  // Template related user meta.
	);

	/**
	 * Option keys that use individual storage (not in main settings array).
	 */
	public const INDIVIDUAL_OPTIONS = array(
		'campaignbridge_from_name',
		'campaignbridge_from_email',
		'campaignbridge_reply_to',
		'campaignbridge_mailchimp_api_key',
		'campaignbridge_mailchimp_audience',
		'campaignbridge_debug_mode',
		'campaignbridge_log_level',
		'campaignbridge_cache_duration',
		'campaignbridge_rate_limit',
		'campaignbridge_last_sync',
		'campaignbridge_mailchimp_last_test',
		'campaignbridge_included_post_types',
	);

	/**
	 * Option keys that use array storage (single option containing array).
	 */
	public const ARRAY_OPTIONS = array(
		'campaignbridge_settings', // Main settings array.
		'campaignbridge_performance_config', // Performance monitoring configuration.
		'campaignbridge_performance_metrics', // Current performance metrics.
	);

	/**
	 * Get prefixed option key.
	 *
	 * @param string $key The option key without prefix.
	 * @return string The prefixed option key.
	 */
	public static function get_option_key( string $key ): string {
		// If key already has a known prefix, return as-is.
		if ( self::has_known_prefix( $key ) ) {
			return $key;
		}

		// Add settings prefix for new options.
		return self::SETTINGS_PREFIX . $key;
	}

	/**
	 * Get prefixed transient key.
	 *
	 * @param string $key The transient key without prefix.
	 * @return string The prefixed transient key.
	 */
	public static function get_transient_key( string $key ): string {
		// If key already has a known prefix, return as-is.
		if ( self::has_known_prefix( $key ) ) {
			return $key;
		}

		return self::PLUGIN_PREFIX . $key;
	}

	/**
	 * Get prefixed post meta key.
	 *
	 * @param string $key The meta key without prefix.
	 * @return string The prefixed meta key.
	 */
	public static function get_post_meta_key( string $key ): string {
		// If key already has a known prefix, return as-is.
		if ( self::has_known_prefix( $key ) ) {
			return $key;
		}

		return self::SETTINGS_PREFIX . $key;
	}

	/**
	 * Get prefixed user meta key.
	 *
	 * @param string $key The meta key without prefix.
	 * @return string The prefixed meta key.
	 */
	public static function get_user_meta_key( string $key ): string {
		// If key already has a known prefix, return as-is.
		if ( self::has_known_prefix( $key ) ) {
			return $key;
		}

		return self::PLUGIN_PREFIX . $key;
	}

	/**
	 * Get cache group name.
	 *
	 * @param string $group The cache group without prefix.
	 * @return string The prefixed cache group.
	 */
	public static function get_cache_group( string $group ): string {
		// If group already has our cache prefix, return as-is.
		if ( str_starts_with( $group, self::CACHE_GROUP_PREFIX . '_' ) ) {
			return $group;
		}

		return self::CACHE_GROUP_PREFIX . '_' . $group;
	}

	/**
	 * Check if a key already has a known plugin prefix.
	 *
	 * This prevents double-prefixing when keys are already properly prefixed.
	 * Covers all prefixes used by the CampaignBridge plugin.
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key has a known plugin prefix.
	 */
	private static function has_known_prefix( string $key ): bool {
		$known_prefixes = array(
			self::PLUGIN_PREFIX,    // 'campaignbridge_'
			self::SETTINGS_PREFIX,  // 'campaignbridge_'
		);

		foreach ( $known_prefixes as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all option keys that should be cleaned up during uninstall.
	 *
	 * @return array<string> Array of all option keys to clean up.
	 */
	public static function get_all_option_keys(): array {
		return array_merge(
			self::INDIVIDUAL_OPTIONS,
			self::ARRAY_OPTIONS
		);
	}

	/**
	 * Get all transient prefixes for cleanup.
	 *
	 * @return array<string> Array of transient prefixes.
	 */
	public static function get_all_transient_prefixes(): array {
		return self::TRANSIENT_PREFIXES;
	}

	/**
	 * Get all post meta prefixes for cleanup.
	 *
	 * @return array<string> Array of post meta prefixes.
	 */
	public static function get_all_post_meta_prefixes(): array {
		return self::POST_META_PREFIXES;
	}

	/**
	 * Get all user meta prefixes for cleanup.
	 *
	 * @return array<string> Array of user meta prefixes.
	 */
	public static function get_all_user_meta_prefixes(): array {
		return self::USER_META_PREFIXES;
	}

	/**
	 * Validate if a key follows proper plugin prefixing conventions.
	 *
	 * Useful for debugging and ensuring all plugin storage uses prefixes.
	 *
	 * @param string $key The key to validate.
	 * @param string $type The storage type ('option', 'transient', 'post_meta', 'user_meta', 'cache').
	 * @return bool True if the key follows proper prefixing.
	 */
	public static function is_properly_prefixed( string $key, string $type = 'option' ): bool {
		switch ( $type ) {
			case 'option':
				return self::has_known_prefix( $key ) ||
						in_array( $key, self::INDIVIDUAL_OPTIONS, true ) ||
						in_array( $key, self::ARRAY_OPTIONS, true );

			case 'transient':
				foreach ( self::TRANSIENT_PREFIXES as $prefix ) {
					if ( str_starts_with( $key, $prefix ) ) {
						return true;
					}
				}
				return false;

			case 'post_meta':
				foreach ( self::POST_META_PREFIXES as $prefix ) {
					if ( str_starts_with( $key, $prefix ) ) {
						return true;
					}
				}
				return false;

			case 'user_meta':
				foreach ( self::USER_META_PREFIXES as $prefix ) {
					if ( str_starts_with( $key, $prefix ) ) {
						return true;
					}
				}
				return false;

			case 'cache':
				return str_starts_with( $key, self::CACHE_GROUP_PREFIX . '_' ) ||
						in_array( $key, self::STANDARD_CACHE_GROUPS, true );

			default:
				return false;
		}
	}
}
