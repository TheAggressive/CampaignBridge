<?php

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
 * Storage Operations for CampaignBridge Plugin
 *
 * Main entry point for all storage operations with automatic prefixing.
 * Delegates to specialized storage classes for different data types.
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

	// ========================================
	// WordPress OPTIONS STORAGE
	// ========================================

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
	 * @param bool   $autoload Whether to autoload the option.
	 * @return bool True on success, false on failure.
	 */
	public static function add_option( string $key, $value, bool $autoload = true ): bool {
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

	// ========================================
	// TRANSIENT STORAGE
	// ========================================

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

	// ========================================
	// POST META STORAGE
	// ========================================

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

	// ========================================
	// USER META STORAGE
	// ========================================

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

	// ========================================
	// CACHE OPERATIONS
	// ========================================

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
	 * Store a value in the cache.
	 *
	 * @param string $key       Cache key.
	 * @param mixed  $value     Value to store.
	 * @param string $group     Cache group.
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public static function set_cache( string $key, $value, string $group, int $expiration = 0 ): bool {
		return self::wp_cache_set( $key, $value, $group, $expiration );
	}

	/**
	 * Retrieve a value from the cache.
	 *
	 * @param string $key     Cache key.
	 * @param string $group   Cache group.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Cached value or default.
	 */
	public static function get_cache( string $key, string $group, $default = false ) {
		$value = self::wp_cache_get( $key, $group );
		return false === $value ? $default : $value;
	}

	/**
	 * Delete a value from the cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_cache( string $key, string $group ): bool {
		return self::wp_cache_delete( $key, $group );
	}

	/**
	 * Update multiple meta keys for a single post.
	 *
	 * @param int                $post_id   The post ID.
	 * @param array<string,mixed> $meta_data Array of meta key => value pairs.
	 * @return bool True on success, false on failure.
	 */
	public static function update_post_metas( int $post_id, array $meta_data ): bool {
		foreach ( $meta_data as $key => $value ) {
			$result = self::update_post_meta( $post_id, $key, $value );
			if ( false === $result ) {
				return false;
			}
		}
		return true;
	}

  /**
	 * Batch update post meta using WordPress API methods.
	 *
	 * @param array<array<string,mixed>> $post_meta_updates Array of post_id => meta_data pairs.
	 * @param int                           $batch_size        Maximum items per batch (default: 50).
	 * @param int                           $max_execution_time Maximum execution time in seconds (default: 25).
	 * @return array{success: bool, processed: int, batches: int, errors: array<int, string>} Result with success status and statistics.
	 */
	public static function batch_update_post_meta( array $post_meta_updates, int $batch_size = 50, int $max_execution_time = 25 ): array {
		$start_time      = microtime( true );
		$start_memory    = memory_get_usage();
		$total_processed = 0;
		$batches         = 0;
		$errors          = array();

		// Flatten the updates into individual operations.
		$operations = array();
		foreach ( $post_meta_updates as $post_id => $meta_data ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				$operations[] = array(
					'post_id'    => absint( $post_id ),
					'meta_key'   => sanitize_key( $meta_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- False positive, not a database query
					'meta_value' => self::sanitize_meta_value( $meta_value ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- False positive, not a database query
				);
			}
		}

		if ( empty( $operations ) ) {
			return array(
				'success'   => false,
				'processed' => 0,
				'batches'   => 0,
				'errors'    => array( 'No valid operations to process' ),
			);
		}

		// Process in chunks to prevent memory exhaustion and timeouts.
		$chunks = array_chunk( $operations, $batch_size );

		foreach ( $chunks as $chunk ) {
			// Check execution time limit.
			if ( microtime( true ) - $start_time > $max_execution_time ) {
				$errors[] = "Execution time limit ({$max_execution_time}s) exceeded after processing {$total_processed} items";
				break;
			}

			// Check memory usage (leave 10MB headroom).
			$current_memory = memory_get_usage();
			if ( $current_memory - $start_memory > 10485760 ) { // 10MB
				$errors[] = 'Memory usage limit exceeded during batch processing';
				break;
			}

			$result = self::process_meta_batch( $chunk );
			if ( ! $result['success'] ) {
				$errors[] = $result['error'];
				break; // Stop on first batch failure to maintain data integrity.
			}

			$total_processed += $result['processed'];
			++$batches;

			// Minimal delay between batches to prevent overwhelming the database.
			if ( count( $chunks ) > 1 ) {
				usleep( 1000 ); // 1ms delay
			}
		}

		$execution_time = microtime( true ) - $start_time;
		$memory_used    = memory_get_usage() - $start_memory;

		// Log performance metrics for monitoring.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\CampaignBridge\Core\Error_Handler::debug(
				'Batch meta update completed',
				array(
					'processed'      => $total_processed,
					'batches'        => $batches,
					'execution_time' => round( $execution_time, 3 ),
					'memory_used'    => size_format( $memory_used ),
					'errors'         => count( $errors ),
				)
			);
		}

		return array(
			'success'   => empty( $errors ),
			'processed' => $total_processed,
			'batches'   => $batches,
			'errors'    => $errors,
		);
	}

	/**
	 * Process a single batch of meta updates using WordPress API methods.
	 *
	 * SAFE APPROACH: Uses update_post_meta() for complete PHPCS compliance and safety.
	 * Each operation is processed individually using WordPress core functions.
	 *
	 * @param array<array{post_id: int, meta_key: string, meta_value: mixed}> $operations Batch operations.
	 * @return array{success: bool, processed: int, error?: string} Batch result.
	 */
	private static function process_meta_batch( array $operations ): array {
		if ( empty( $operations ) ) {
			return array(
				'success'   => false,
				'processed' => 0,
				'error'     => 'Empty batch provided',
			);
		}

		$processed = 0;
		$errors    = array();

		foreach ( $operations as $operation ) {
			// Use WordPress API instead of direct SQL - PHPCS compliant but slower.
			$result = update_post_meta(
				$operation['post_id'],
				$operation['meta_key'],
				$operation['meta_value']
			);

			if ( false !== $result ) {
				++$processed;
			} else {
				$errors[] = "Failed to update meta for post {$operation['post_id']}";
			}
		}

		return array(
			'success'   => empty( $errors ),
			'processed' => $processed,
			'error'     => ! empty( $errors ) ? implode( '; ', $errors ) : null,
		);
	}


	// ========================================
	// DATA SANITIZATION
	// ========================================

	/**
	 * Sanitize meta value for safe database storage.
	 *
	 * Uses WordPress built-in sanitization functions for battle-tested security.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed Sanitized value safe for database storage.
	 */
	private static function sanitize_meta_value( $value ) {
		switch ( gettype( $value ) ) {
			case 'string':
				// WordPress built-in: Strip HTML tags and encode special characters.
				return sanitize_text_field( $value );

			case 'integer':
			case 'double':
				// Numbers are safe for database storage.
				return $value;

			case 'boolean':
				// Convert to integer for consistent storage.
				return $value ? 1 : 0;

			case 'array':
				// Recursively sanitize array values and serialize.
				$sanitized_array = array();
				foreach ( $value as $key => $item ) {
					$sanitized_key                     = is_string( $key ) ? sanitize_key( $key ) : $key;
					$sanitized_array[ $sanitized_key ] = self::sanitize_meta_value( $item );
				}
				return maybe_serialize( $sanitized_array );

			case 'object':
				// Only allow objects with __toString method.
				if ( method_exists( $value, '__toString' ) ) {
					return sanitize_text_field( (string) $value );
				}
				// Fallback: serialize safe objects.
				return maybe_serialize( $value );

			case 'NULL':
			case 'null':
				return '';

			case 'resource':
			case 'resource (closed)':
			case 'unknown type':
				// Reject unsafe types.
				return '';

			default:
				// Convert unknown types to string and sanitize.
				return sanitize_text_field( (string) $value );
		}
	}


	/**
	 * Bulk delete plugin transients using WordPress API methods.
	 *
	 * SAFE APPROACH: Uses only WordPress API methods for complete PHPCS compliance.
	 * Iterates through transients and deletes them individually using delete_transient().
	 * Slower than direct SQL but completely safe and compliant.
	 *
	 * @return int Number of transients deleted.
	 */
	public static function bulk_delete_plugin_transients(): int {
		$deleted_count = 0;

		// Get all transient prefixes from Storage_Prefixes.
		$prefixes = Storage_Prefixes::get_all_transient_prefixes();

		// Validate prefixes are safe (should only contain alphanumeric and underscores).
		$safe_prefixes = array_filter(
			$prefixes,
			function ( $prefix ) {
				return preg_match( '/^[a-zA-Z0-9_]+$/', $prefix );
			}
		);

		if ( empty( $safe_prefixes ) ) {
			return 0;
		}

		// For each prefix, try common transient keys that might exist.
		// This is not perfect but covers the most common cases without direct SQL.
		foreach ( $safe_prefixes as $prefix ) {
			// Try some common transient keys for this prefix.
			$possible_keys = array(
				$prefix,           // Basic key.
				$prefix . '_data', // Data variant.
				$prefix . '_cache', // Cache variant.
				'list_' . $prefix,  // List variant.
			);

			foreach ( $possible_keys as $key ) {
				if ( delete_transient( $key ) ) {
					++$deleted_count;
				}
			}
		}

		// Note: This approach may not catch all transients with complex keys.
		// For complete cleanup, transients should be properly managed with TTL.
		return $deleted_count;
	}
}
