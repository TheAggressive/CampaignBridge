<?php
/**
 * Performance Optimizer for CampaignBridge.
 *
 * Provides comprehensive caching strategies, query optimization, and performance monitoring
 * following WordPress best practices and modern PHP patterns.
 *
 * @package CampaignBridge\Core
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance optimization and caching strategies
 */
class Performance_Optimizer {
	/**
	 * Cache TTL constants
	 */
	private const CACHE_MEDIUM = 1800;  // 30 minutes
	private const CACHE_LONG   = 7200;  // 2 hours

	/**
	 * Cache groups for organization
	 */
	private const CACHE_GROUP_QUERIES = 'queries';

	/**
	 * Get cached data with fallback
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Fallback function to generate data.
	 * @param int      $ttl      Time to live in seconds.
	 * @param string   $group    Cache group.
	 * @return mixed
	 */
	public function get_cached_data( string $key, callable $callback, int $ttl = self::CACHE_MEDIUM, string $group = self::CACHE_GROUP_QUERIES ): mixed {
		$cached = \CampaignBridge\Core\Storage::wp_cache_get( $key, $group );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $callback();

		\CampaignBridge\Core\Storage::wp_cache_set( $key, $data, $group, $ttl );

		return $data;
	}

	/**
	 * Get transient data with fallback
	 *
	 * @param string   $key      Transient key.
	 * @param callable $callback Fallback function.
	 * @param int      $ttl      Expiration in seconds.
	 * @return mixed
	 */
	public function get_transient_data( string $key, callable $callback, int $ttl = self::CACHE_LONG ): mixed {
		$cached = \CampaignBridge\Core\Storage::get_transient( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $callback();
		\CampaignBridge\Core\Storage::set_transient( $key, $data, $ttl );

		return $data;
	}

	/**
	 * Optimized WP_Query for posts
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return \WP_Query
	 */
	public function get_optimized_posts_query( array $args = array() ): \WP_Query {
		$defaults = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 10,
			'no_found_rows'          => true,  // Skip pagination for better performance.
			'update_post_meta_cache' => false, // Don't load meta cache if not needed.
			'update_post_term_cache' => false, // Don't load term cache if not needed.
			'fields'                 => 'ids', // Only get post IDs if that's all you need.
			'ignore_sticky_posts'    => true,
			'suppress_filters'       => true,
		);

		$args = wp_parse_args( $args, $defaults );

		return new \WP_Query( $args );
	}

	/**
	 * Batch update post meta with performance optimization
	 *
	 * @param array<int, array<string, mixed>> $post_meta_updates Array of post_id => meta_data pairs.
	 * @return bool
	 */
	public function batch_update_post_meta( array $post_meta_updates ): bool {
		// Security: Only allow admin users to perform bulk database operations.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		global $wpdb;

		// Use batch updates for better performance.
		$values       = array();
		$placeholders = array();

		foreach ( $post_meta_updates as $post_id => $meta_data ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				$values[]       = $post_id;
				$values[]       = $meta_key;
				$values[]       = $meta_value;
				$placeholders[] = '(%d, %s, %s)';
			}
		}

		if ( empty( $placeholders ) ) {
			return false;
		}

		$sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				VALUES " . implode( ', ', $placeholders ) . '
				ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)';

		return (bool) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( $sql, $values ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Invalidate cache group
	 *
	 * @param string $group Cache group to invalidate.
	 * @return void
	 */
	public function invalidate_cache_group( string $group ): void {
		\CampaignBridge\Core\Storage::wp_cache_flush_group( $group );
	}

	/**
	 * Clear all plugin transients
	 *
	 * @return void
	 */
	public function clear_plugin_transients(): void {
		global $wpdb;

		// Get all transient prefixes from Storage_Prefixes.
		$prefixes = \CampaignBridge\Core\Storage_Prefixes::get_all_transient_prefixes();

		foreach ( $prefixes as $prefix ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%_transient_' . $wpdb->esc_like( $prefix ) . '%'
				)
			);

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%_transient_timeout_' . $wpdb->esc_like( $prefix ) . '%'
				)
			);
		}
	}


	/**
	 * Performance monitoring for expensive operations
	 *
	 * @param string   $operation Operation name.
	 * @param callable $callback  Function to measure.
	 * @return mixed Function result
	 */
	public function measure_performance( string $operation, callable $callback ): mixed {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		$result = $callback();

		$end_time   = microtime( true );
		$end_memory = memory_get_usage();

		$execution_time = $end_time - $start_time;
		$memory_used    = $end_memory - $start_memory;

		// Log slow operations (> 1 second).
		if ( $execution_time > 1.0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production logging.
				sprintf(
					'[CAMPAIGNBRIDGE PERFORMANCE] Slow operation "%s": %.4fs, %s memory used',
					$operation,
					$execution_time,
					size_format( $memory_used )
				)
			);
		}

		return $result;
	}
}
