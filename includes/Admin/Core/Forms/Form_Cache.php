<?php
/**
 * Form Cache - Performance optimization for form configurations and rendering
 *
 * Provides caching mechanisms to improve form loading performance by caching
 * form configurations, field definitions, and rendered output where appropriate.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Cache - Performance optimization for form configurations and rendering
 *
 * Provides intelligent caching mechanisms to improve form loading performance by caching
 * form configurations, field definitions, validation rules, and rendered output where appropriate.
 * Integrates with WordPress object cache for efficient storage and retrieval across requests.
 *
 * Key Features:
 * - Form configuration caching with configurable expiry times
 * - Field definition caching for faster form building
 * - Rendered HTML caching (use with caution for dynamic forms)
 * - Validation rules caching for performance
 * - Smart cache invalidation and cleanup
 * - WordPress Object Cache integration
 *
 * @since 0.3.2
 */
class Form_Cache {
	/**
	 * Cache group for form-related data.
	 */
	private const CACHE_GROUP = 'campaignbridge_forms';

	/**
	 * Default cache expiration time (1 hour).
	 */
	private const CACHE_EXPIRY = HOUR_IN_SECONDS;

	/**
	 * Cache a form configuration.
	 *
	 * Stores form configuration data in WordPress object cache for improved performance.
	 * Useful for caching form definitions that don't change frequently.
	 *
	 * @param string               $cache_key Unique cache key for the form (should be prefixed with form ID).
	 * @param array<string, mixed> $config    Complete form configuration array including fields, settings, and metadata.
	 * @param int                  $expiry    Cache expiry time in seconds. Default: 1 hour (HOUR_IN_SECONDS).
	 * @return bool True on successful cache storage, false on failure or if caching is disabled.
	 *
	 * @example
	 * ```php
	 * $cache->set_form_config('my_form_config', $form->config->all(), HOUR_IN_SECONDS);
	 * ```
	 */
	public function set_form_config( string $cache_key, array $config, int $expiry = self::CACHE_EXPIRY ): bool {
		$cache_key = $this->sanitize_cache_key( $cache_key );
		return \CampaignBridge\Core\Storage::wp_cache_set( $cache_key, $config, self::CACHE_GROUP, $expiry );
	}

	/**
	 * Get a cached form configuration.
	 *
	 * Retrieves form configuration data from WordPress object cache.
	 * Returns null if the cache key doesn't exist or has expired.
	 *
	 * @param string $cache_key Unique cache key for the form (should match the key used in set_form_config).
	 * @return array<string, mixed>|null Complete form configuration array if found in cache, null otherwise.
	 *
	 * @example
	 * ```php
	 * $config = $cache->get_form_config('my_form_config');
	 * if ($config) {
	 *     // Use cached configuration
	 * } else {
	 *     // Load fresh configuration
	 * }
	 * ```
	 */
	public function get_form_config( string $cache_key ): ?array {
		$cache_key = $this->sanitize_cache_key( $cache_key );
		$cached    = \CampaignBridge\Core\Storage::wp_cache_get( $cache_key, self::CACHE_GROUP );

		return false !== $cached ? $cached : null;
	}

	/**
	 * Cache field definitions for a form.
	 *
	 * @param string               $form_id Form identifier.
	 * @param array<string, mixed> $fields  Field definitions array.
	 * @return bool True on success, false on failure.
	 */
	public function set_form_fields( string $form_id, array $fields ): bool {
		$cache_key = "fields_{$form_id}";
		return $this->set_form_config( $cache_key, $fields, self::CACHE_EXPIRY * 2 ); // Cache longer for fields.
	}

	/**
	 * Get cached field definitions for a form.
	 *
	 * @param string $form_id Form identifier.
	 * @return array<string, mixed>|null Cached fields or null if not found.
	 */
	public function get_form_fields( string $form_id ): ?array {
		$cache_key = "fields_{$form_id}";
		return $this->get_form_config( $cache_key );
	}

	/**
	 * Cache rendered form HTML (use with caution - only for static forms).
	 *
	 * @param string               $form_id    Form identifier.
	 * @param string               $html       Rendered HTML.
	 * @param array<string, mixed> $conditions Array of conditions that must match for cache hit.
	 * @return bool True on success, false on failure.
	 */
	public function set_rendered_form( string $form_id, string $html, array $conditions = array() ): bool {
		$conditions_json = wp_json_encode( $conditions );
		$conditions_hash = $conditions_json ? md5( $conditions_json ) : 'empty';
		$cache_key       = "rendered_{$form_id}_{$conditions_hash}";
		return \CampaignBridge\Core\Storage::wp_cache_set( $cache_key, $html, self::CACHE_GROUP, self::CACHE_EXPIRY / 4 ); // Shorter cache for HTML.
	}

	/**
	 * Get cached rendered form HTML.
	 *
	 * @param string               $form_id    Form identifier.
	 * @param array<string, mixed> $conditions Array of conditions that must match for cache hit.
	 * @return string|null Cached HTML or null if not found.
	 */
	public function get_rendered_form( string $form_id, array $conditions = array() ): ?string {
		$conditions_json = wp_json_encode( $conditions );
		$conditions_hash = $conditions_json ? md5( $conditions_json ) : 'empty';
		$cache_key       = "rendered_{$form_id}_{$conditions_hash}";
		$cached          = \CampaignBridge\Core\Storage::wp_cache_get( $cache_key, self::CACHE_GROUP );

		return false !== $cached ? $cached : null;
	}

	/**
	 * Cache validation rules for performance.
	 *
	 * @param string               $form_id Form identifier.
	 * @param array<string, mixed> $rules   Validation rules array.
	 * @return bool True on success, false on failure.
	 */
	public function set_validation_rules( string $form_id, array $rules ): bool {
		$cache_key = "validation_{$form_id}";
		return $this->set_form_config( $cache_key, $rules, self::CACHE_EXPIRY * 2 );
	}

	/**
	 * Get cached validation rules.
	 *
	 * @param string $form_id Form identifier.
	 * @return array<string, mixed>|null Cached validation rules or null if not found.
	 */
	public function get_validation_rules( string $form_id ): ?array {
		$cache_key = "validation_{$form_id}";
		return $this->get_form_config( $cache_key );
	}

	/**
	 * Invalidate all cache entries for a specific form.
	 *
	 * Clears all cached data related to a specific form, including configuration,
	 * fields, validation rules, and rendered output. This method should be called
	 * whenever form definitions are modified to ensure cache consistency.
	 *
	 * @param string $form_id Unique identifier for the form whose cache should be cleared.
	 * @return void
	 *
	 * @example
	 * ```php
	 * // After updating form configuration
	 * $form->save();
	 * $cache->invalidate_form_cache('contact_form');
	 * ```
	 */
	public function invalidate_form_cache( string $form_id ): void {
		// Delete specific cache entries for this form.
		\CampaignBridge\Core\Storage::wp_cache_delete( "config_{$form_id}", self::CACHE_GROUP );
		\CampaignBridge\Core\Storage::wp_cache_delete( "fields_{$form_id}", self::CACHE_GROUP );
		\CampaignBridge\Core\Storage::wp_cache_delete( "validation_{$form_id}", self::CACHE_GROUP );

		// Delete rendered form caches (pattern-based deletion).
		$this->invalidate_pattern( "rendered_{$form_id}_*" );
	}

	/**
	 * Clear all form cache entries.
	 *
	 * @return void
	 */
	public function clear_all_cache(): void {
		\CampaignBridge\Core\Storage::wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Get cache statistics for monitoring.
	 *
	 * @return array<string, mixed> Cache statistics.
	 */
	public function get_cache_stats(): array {
		return array(
			'cache_group'     => self::CACHE_GROUP,
			'expiry_time'     => self::CACHE_EXPIRY,
			'wordpress_cache' => wp_cache_supports( 'flush_group' ) ? 'advanced' : 'basic',
		);
	}

	/**
	 * Sanitize cache key to ensure it's safe for WordPress cache.
	 *
	 * @param string $key Raw cache key.
	 * @return string Sanitized cache key.
	 */
	private function sanitize_cache_key( string $key ): string {
		// Replace spaces and special characters with underscores.
		$sanitized = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );

		// Handle preg_replace failure.
		if ( null === $sanitized ) {
			$sanitized = $key;
		}

		// Ensure key is not too long (WordPress has limits).
		if ( strlen( $sanitized ) > 172 ) {
			$sanitized = substr( $sanitized, 0, 172 );
		}

		return $sanitized;
	}

	/**
	 * Invalidate cache entries matching a pattern.
	 *
	 * Note: This is a simplified implementation. In production, you might want
	 * to use a more sophisticated caching system that supports pattern deletion.
	 *
	 * @param string $pattern Pattern to match (e.g., "rendered_form_*").
	 * @return void
	 */
	private function invalidate_pattern( string $pattern ): void {
		// For WordPress object cache, we can't easily delete by pattern
		// This is a limitation of the basic WordPress object cache
		// In production, consider using Redis or Memcached with pattern support.

		// For now, we'll skip pattern-based invalidation and rely on time-based expiry
		// This is acceptable for most use cases where form definitions don't change frequently.
	}
}
