<?php
/**
 * Form Query Optimizer - Database performance optimizations for forms
 *
 * Provides optimized database query patterns for form data operations,
 * including proper indexing recommendations and query performance monitoring.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Query Optimizer - Database performance optimizations for forms
 *
 * Provides optimized database query patterns for form data operations,
 * including proper indexing recommendations, query performance monitoring,
 * and efficient data loading strategies.
 *
 * Key Features:
 * - Optimized WP_Query arguments for better performance
 * - Batch database operations for meta data updates
 * - Selective data loading to reduce memory usage
 * - Query performance monitoring and logging
 * - Database optimization recommendations
 * - Cached data loading with automatic performance tracking
 *
 * @since 0.3.2
 */
class Form_Query_Optimizer {
	/**
	 * Get optimized query arguments for form posts.
	 *
	 * @param array<string, mixed> $args Additional query arguments.
	 * @return array<string, mixed> Optimized WP_Query arguments.
	 */
	public function get_optimized_post_query( array $args = array() ): array {
		$defaults = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'paged'                  => get_query_var( 'paged', 1 ),

			// Performance optimizations.
			'no_found_rows'          => true,  // Skip pagination count for better performance.
			'update_post_meta_cache' => false, // Don't load meta cache if not needed.
			'update_post_term_cache' => false, // Don't load term cache if not needed.

			// Use IDs for faster queries when possible.
			'fields'                 => 'ids',
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Get optimized query for form-related posts with meta queries.
	 *
	 * @param array<string, mixed> $meta_conditions Meta query conditions.
	 * @param array<string, mixed> $args            Additional query arguments.
	 * @return array<string, mixed> Optimized query arguments.
	 */
	public function get_optimized_meta_query( array $meta_conditions, array $args = array() ): array {
		$defaults = $this->get_optimized_post_query(
			array(
				'meta_query' => $meta_conditions, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Batch update post meta with optimized queries.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta_data Array of meta key => value pairs.
	 * @return bool True on success, false on failure.
	 */
	public function batch_update_post_meta( int $post_id, array $meta_data ): bool {
		if ( empty( $meta_data ) ) {
			return true;
		}

		$success = true;

		// Use WordPress core functions to avoid direct database queries.
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$result = \CampaignBridge\Core\Storage::update_post_meta( $post_id, $meta_key, $meta_value );

			if ( false === $result ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get posts with optimized meta loading.
	 *
	 * @param array<int>    $post_ids Array of post IDs.
	 * @param array<string> $meta_keys Array of meta keys to load (optional).
	 * @return array<int, array<string, mixed>> Array of post data with optimized meta loading.
	 */
	public function get_posts_with_meta( array $post_ids, array $meta_keys = array() ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		// Sanitize and limit post IDs for performance.
		$post_ids = array_map( 'intval', $post_ids );
		$post_ids = array_slice( $post_ids, 0, 100 ); // Limit to 100 posts max.

		$posts = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$post_data = array(
				'ID'        => $post->ID,
				'title'     => $post->post_title,
				'content'   => $post->post_content,
				'excerpt'   => $post->post_excerpt,
				'status'    => $post->post_status,
				'date'      => $post->post_date,
				'modified'  => $post->post_modified,
				'post_type' => $post->post_type,
			);

			// Load specific meta keys if requested.
			if ( ! empty( $meta_keys ) ) {
				$post_data['meta'] = array();
				foreach ( $meta_keys as $meta_key ) {
					$post_data['meta'][ $meta_key ] = \CampaignBridge\Core\Storage::get_post_meta( $post_id, $meta_key, true );
				}
			}

			$posts[] = $post_data;
		}

		return $posts;
	}

	/**
	 * Get database performance recommendations.
	 *
	 * @return array<int, array<string, string>> Array of database optimization recommendations.
	 */
	public function get_performance_recommendations(): array {
		// Return static performance recommendations to avoid direct database queries.
		// Dynamic analysis could be added later as a separate admin diagnostic tool.
		return array(
			array(
				'type'       => 'index',
				'table'      => 'wp_postmeta',
				'column'     => 'meta_key',
				'suggestion' => 'Consider adding composite index on (meta_key, meta_value) for better meta query performance',
				'sql'        => 'CREATE INDEX idx_meta_key_value ON wp_postmeta (meta_key(50), meta_value(100))',
			),
			array(
				'type'       => 'optimization',
				'table'      => 'wp_postmeta',
				'suggestion' => 'Monitor postmeta table size and consider archiving old meta data if it grows very large',
			),
			array(
				'type'       => 'caching',
				'suggestion' => 'Enable persistent object caching (Redis/Memcached) for better performance',
			),
		);
	}

	/**
	 * Monitor query performance for form operations.
	 *
	 * Executes a database operation while tracking its performance metrics.
	 * Logs operations that take longer than 100ms or use more than 10MB of memory.
	 * Useful for identifying performance bottlenecks in form data operations.
	 *
	 * @param string   $operation_name Descriptive name for the operation (e.g., "Load user preferences").
	 * @param callable $operation      The database operation to execute and monitor.
	 * @return mixed The return value from the executed operation.
	 *
	 * @example
	 * ```php
	 * $posts = $optimizer->monitor_query_performance(
	 *     'Load form submissions',
	 *     function() {
	 *         return get_posts(['post_type' => 'form_submission', 'posts_per_page' => 50]);
	 *     }
	 * );
	 * ```
	 */
	public function monitor_query_performance( string $operation_name, callable $operation ) {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		$result = $operation();

		$end_time   = microtime( true );
		$end_memory = memory_get_usage();

		$duration    = $end_time - $start_time;
		$memory_used = $end_memory - $start_memory;

		// Log slow queries (>100ms) or high memory usage (>10MB) in debug mode only.
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && ( $duration > 0.1 || $memory_used > 10485760 ) ) {
			\CampaignBridge\Core\Error_Handler::info(
				'[FORM_QUERY_PERFORMANCE] ' . sprintf(
					'%s: %.4f seconds, %s memory',
					$operation_name,
					$duration,
					size_format( $memory_used )
				)
			);
		}

		return $result;
	}

	/**
	 * Optimize form data loading with selective caching.
	 *
	 * @param string   $form_id Form identifier.
	 * @param callable $data_loader Function that loads the data.
	 * @return mixed Cached or fresh data.
	 */
	public function get_cached_form_data( string $form_id, callable $data_loader ) {
		$cache_key   = "form_data_{$form_id}";
		$cached_data = \CampaignBridge\Core\Storage::wp_cache_get( $cache_key, 'campaignbridge_forms' );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$data = $this->monitor_query_performance(
			"Load form data for {$form_id}",
			$data_loader
		);

		// Cache for 5 minutes.
		\CampaignBridge\Core\Storage::wp_cache_set( $cache_key, $data, 'campaignbridge_forms', 300 );

		return $data;
	}
}
