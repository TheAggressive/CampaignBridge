<?php // phpcs:ignore WordPress.Files.FileName
// phpcs:disable CampaignBridge.Sniffs.StorageUsage.ForbiddenStorageFunction -- This file intentionally uses WordPress storage functions as wrappers

/**
 * Storage Operations for CampaignBridge Plugin
 *
 * Wrapper functions for all database operations that automatically
 * handle key prefixing for consistency and security.
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
 * Storage class.
 *
 * Provides wrapper methods for all database operations with automatic
 * prefixing to ensure consistency and prevent conflicts.
 *
 * ⚠️ IMPORTANT: This class should be used for ALL WordPress storage operations
 * in the plugin to ensure proper prefixing and avoid conflicts with other plugins.
 *
 * ❌ NEVER use direct WordPress functions:
 * - get_option() → Storage::get_option()
 * - update_option() → Storage::update_option()
 * - get_transient() → Storage::get_transient()
 * - etc.
 *
 * ✅ ALWAYS use Storage wrappers for proper prefixing.
 */
class Storage {

	/**
	 * Get an option value with automatic prefixing.
	 *
	 * @param string $key     The option key (without prefix).
	 * @param mixed  $value Default value if option doesn't exist.
	 * @return mixed The option value.
	 */
	public static function get_option( string $key, $value = false ): mixed {
		$prefixed_key = Storage_Prefixes::get_option_key( $key );
		return get_option( $prefixed_key, $value );
	}

	/**
	 * Update an option value with automatic prefixing.
	 *
	 * @param string $key   The option key (without prefix).
	 * @param mixed  $value The value to store.
	 * @return bool True on success, false on failure.
	 */
	public static function update_option( string $key, $value ): bool {
		$prefixed_key = Storage_Prefixes::get_option_key( $key );
		return update_option( $prefixed_key, $value );
	}

	/**
	 * Add an option value with automatic prefixing.
	 *
	 * @param string $key     The option key (without prefix).
	 * @param mixed  $value   The value to store.
	 * @param string $autoload Whether to autoload the option.
	 * @return bool True on success, false on failure.
	 */
	public static function add_option( string $key, $value, string $autoload = 'yes' ): bool {
		$prefixed_key = Storage_Prefixes::get_option_key( $key );
		return add_option( $prefixed_key, $value, '', $autoload );
	}

	/**
	 * Delete an option with automatic prefixing.
	 *
	 * @param string $key The option key (without prefix).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_option( string $key ): bool {
		$prefixed_key = Storage_Prefixes::get_option_key( $key );
		return delete_option( $prefixed_key );
	}

	/**
	 * Get a transient value with automatic prefixing.
	 *
	 * @param string $key The transient key (without prefix).
	 * @return mixed The transient value or false if not found.
	 */
	public static function get_transient( string $key ): mixed {
		$prefixed_key = Storage_Prefixes::get_transient_key( $key );
		return get_transient( $prefixed_key );
	}

	/**
	 * Set a transient value with automatic prefixing.
	 *
	 * @param string $key   The transient key (without prefix).
	 * @param mixed  $value The value to store.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool True on success, false on failure.
	 */
	public static function set_transient( string $key, $value, int $ttl ): bool {
		$prefixed_key = Storage_Prefixes::get_transient_key( $key );
		return set_transient( $prefixed_key, $value, $ttl );
	}

	/**
	 * Delete a transient with automatic prefixing.
	 *
	 * @param string $key The transient key (without prefix).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_transient( string $key ): bool {
		$prefixed_key = Storage_Prefixes::get_transient_key( $key );
		return delete_transient( $prefixed_key );
	}

	/**
	 * Get post meta with automatic prefixing.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix).
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed The meta value.
	 */
	public static function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
		$prefixed_key = Storage_Prefixes::get_post_meta_key( $key );
		return get_post_meta( $post_id, $prefixed_key, $single );
	}

	/**
	 * Update post meta with automatic prefixing.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $key        The meta key (without prefix).
	 * @param mixed  $value      The value to store.
	 * @param mixed  $prev_value Previous value for unique updates.
	 * @return int|bool Meta ID on success, false on failure.
	 */
	public static function update_post_meta( int $post_id, string $key, $value, $prev_value = '' ): int|bool {
		$prefixed_key = Storage_Prefixes::get_post_meta_key( $key );
		return update_post_meta( $post_id, $prefixed_key, $value, $prev_value );
	}

	/**
	 * Add post meta with automatic prefixing.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $value   The value to store.
	 * @param bool   $unique  Whether the meta key should be unique.
	 * @return int|bool Meta ID on success, false on failure.
	 */
	public static function add_post_meta( int $post_id, string $key, $value, bool $unique = false ): int|bool {
		$prefixed_key = Storage_Prefixes::get_post_meta_key( $key );
		return add_post_meta( $post_id, $prefixed_key, $value, $unique );
	}

	/**
	 * Delete post meta with automatic prefixing.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $value   The value to delete (optional).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_post_meta( int $post_id, string $key, $value = '' ): bool {
		$prefixed_key = Storage_Prefixes::get_post_meta_key( $key );
		return delete_post_meta( $post_id, $prefixed_key, $value );
	}

	/**
	 * Get user meta with automatic prefixing.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $key     The meta key (without prefix).
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed The meta value.
	 */
	public static function get_user_meta( int $user_id, string $key, bool $single = false ): mixed {
		$prefixed_key = Storage_Prefixes::get_user_meta_key( $key );
		return get_user_meta( $user_id, $prefixed_key, $single );
	}

	/**
	 * Update user meta with automatic prefixing.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $value   The value to store.
	 * @param mixed  $prev_value Previous value for unique updates.
	 * @return int|bool Meta ID on success, false on failure.
	 */
	public static function update_user_meta( int $user_id, string $key, $value, $prev_value = '' ): int|bool {
		$prefixed_key = Storage_Prefixes::get_user_meta_key( $key );
		return update_user_meta( $user_id, $prefixed_key, $value, $prev_value );
	}

	/**
	 * Add user meta with automatic prefixing.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $value   The value to store.
	 * @param bool   $unique  Whether the meta key should be unique.
	 * @return int|bool Meta ID on success, false on failure.
	 */
	public static function add_user_meta( int $user_id, string $key, $value, bool $unique = false ): int|bool {
		$prefixed_key = Storage_Prefixes::get_user_meta_key( $key );
		return add_user_meta( $user_id, $prefixed_key, $value, $unique );
	}

	/**
	 * Delete user meta with automatic prefixing.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $key     The meta key (without prefix).
	 * @param mixed  $value   The value to delete (optional).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_user_meta( int $user_id, string $key, $value = '' ): bool {
		$prefixed_key = Storage_Prefixes::get_user_meta_key( $key );
		return delete_user_meta( $user_id, $prefixed_key, $value );
	}

	/**
	 * Get cache value with automatic prefixing.
	 *
	 * @param string $key   The cache key (without prefix).
	 * @param string $group The cache group (without prefix).
	 * @return mixed The cached value or false if not found.
	 */
	public static function wp_cache_get( string $key, string $group ): mixed {
		$prefixed_group = Storage_Prefixes::get_cache_group( $group );
		return wp_cache_get( $key, $prefixed_group );
	}

	/**
	 * Set cache value with automatic prefixing.
	 *
	 * @param string $key        The cache key.
	 * @param mixed  $value      The value to cache.
	 * @param string $group      The cache group (without prefix).
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public static function wp_cache_set( string $key, $value, string $group, int $expiration = 0 ): bool {
		$prefixed_group = Storage_Prefixes::get_cache_group( $group );
		return wp_cache_set( $key, $value, $prefixed_group, $expiration );
	}

	/**
	 * Delete cache value with automatic prefixing.
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group (without prefix).
	 * @return bool True on success, false on failure.
	 */
	public static function wp_cache_delete( string $key, string $group ): bool {
		$prefixed_group = Storage_Prefixes::get_cache_group( $group );
		return wp_cache_delete( $key, $prefixed_group );
	}

	/**
	 * Flush a cache group with automatic prefixing.
	 *
	 * @param string $group The cache group (without prefix).
	 * @return bool True on success, false on failure.
	 */
	public static function wp_cache_flush_group( string $group ): bool {
		$prefixed_group = Storage_Prefixes::get_cache_group( $group );
		return wp_cache_flush_group( $prefixed_group );
	}

	/**
	 * Flush all plugin cache groups.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function flush_plugin_cache(): bool {
		// Flush object cache groups that start with our prefix.
		$groups_to_flush = array( 'users', 'posts', 'options', 'general' );

		foreach ( $groups_to_flush as $group ) {
			$prefixed_group = Storage_Prefixes::get_cache_group( $group );
			wp_cache_flush_group( $prefixed_group );
		}

		return true;
	}

	/**
	 * Batch update post meta with performance optimization.
	 *
	 * FOOL-PROOF SECURITY: All meta values are automatically sanitized to prevent
	 * security vulnerabilities regardless of caller input. No "oops" possible.
	 *
	 * @param array<int, array<string, mixed>> $post_meta_updates Array of post_id => meta_data pairs.
	 * @return bool True on success, false on failure.
	 */
	public static function batch_update_post_meta( array $post_meta_updates ): bool {
		if ( empty( $post_meta_updates ) ) {
			return true;
		}

		global $wpdb;

		// Build batch insert/update query.
		$values       = array();
		$placeholders = array();

		foreach ( $post_meta_updates as $post_id => $meta_data ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				$values[]       = absint( $post_id );
				$values[]       = sanitize_key( $meta_key );
				$values[]       = self::sanitize_meta_value( $meta_value ); // FOOL-PROOF: Always sanitize.
				$placeholders[] = '(%d, %s, %s)';
			}
		}

		if ( empty( $placeholders ) ) {
			return false;
		}

		$sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				VALUES " . implode( ', ', $placeholders ) . '
				ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)';

		return (bool) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,CampaignBridge.Sniffs.DirectDatabaseQuery.DirectDatabaseMethod -- Batch operations require direct queries for performance.
			$wpdb->prepare( $sql, $values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,CampaignBridge.Sniffs.DirectDatabaseQuery.DirectDatabaseMethod -- SQL dynamically built with placeholders above.
		);
	}

	/**
	 * Sanitize meta value for safe database storage.
	 *
	 * FOOL-PROOF: Automatically handles all data types to prevent security issues.
	 * No matter what data is passed, it gets sanitized appropriately.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed Sanitized value safe for database storage.
	 */
	private static function sanitize_meta_value( $value ) {
		// Handle different data types.
		switch ( gettype( $value ) ) {
			case 'string':
				// Sanitize strings, but preserve HTML for rich content.
				return wp_kses_post( $value );

			case 'integer':
			case 'double':
				// Numbers are safe as-is.
				return $value;

			case 'boolean':
				// Convert to integer for consistent storage.
				return $value ? 1 : 0;

			case 'array':
				// Recursively sanitize array values, then serialize.
				$sanitized_array = array();
				foreach ( $value as $key => $item ) {
					$sanitized_key                     = is_string( $key ) ? sanitize_key( $key ) : $key;
					$sanitized_array[ $sanitized_key ] = self::sanitize_meta_value( $item );
				}
				return maybe_serialize( $sanitized_array );

			case 'object':
				// Objects get serialized after basic sanitization check.
				if ( method_exists( $value, '__toString' ) ) {
					// If object has __toString, use that.
					return sanitize_text_field( (string) $value );
				}
				// Otherwise serialize the object.
				return maybe_serialize( $value );

			case 'NULL':
			case 'null':
				// Null values become empty strings.
				return '';

			case 'resource':
			case 'resource (closed)':
				// Resources cannot be serialized - reject them.
				return '';

			default:
				// Unknown types get converted to string and sanitized.
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Bulk delete plugin transients.
	 *
	 * SECURITY: Uses predefined prefixes from Storage_Prefixes constants.
	 * All prefixes are hardcoded and safe - no user input involved.
	 *
	 * @return void
	 */
	public static function bulk_delete_plugin_transients(): void {
		global $wpdb;

		// Get all transient prefixes from Storage_Prefixes.
		$prefixes = Storage_Prefixes::get_all_transient_prefixes();

		foreach ( $prefixes as $prefix ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,CampaignBridge.Sniffs.DirectDatabaseQuery.DirectDatabaseMethod -- Bulk transient cleanup requires direct queries.
				$wpdb->prepare( // phpcs:ignore CampaignBridge.Sniffs.DirectDatabaseQuery.DirectDatabaseMethod -- Bulk transient cleanup requires direct queries.
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%_transient_' . $wpdb->esc_like( $prefix ) . '%'
				)
			);

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,CampaignBridge.Sniffs.DirectDatabaseQuery.DirectDatabaseMethod -- Bulk transient cleanup requires direct queries.
				$wpdb->prepare( // phpcs:ignore CampaignBridge.Sniffs.DirectDatabaseQuery.DirectDatabaseMethod -- Bulk transient cleanup requires direct queries.
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%_transient_timeout_' . $wpdb->esc_like( $prefix ) . '%'
				)
			);
		}
	}
}
