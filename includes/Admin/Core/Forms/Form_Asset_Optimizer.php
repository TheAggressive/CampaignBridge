<?php
/**
 * Form Asset Optimizer - Performance optimization for form assets
 *
 * Provides asset loading optimizations including conditional loading,
 * dependency management, and resource hints for better performance.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Asset Optimizer - Loading performance optimizations for forms
 *
 * Provides comprehensive asset loading optimizations to improve form page load times,
 * including conditional loading, dependency optimization, resource hints, and deferring
 * non-critical assets.
 *
 * Key Features:
 * - Conditional asset loading based on form requirements
 * - Dependency optimization to remove redundant enqueues
 * - Resource hints (preload, prefetch, DNS prefetch) for better performance
 * - Deferring of non-critical scripts to improve initial page load
 * - Duplicate prevention to avoid multiple enqueues of the same asset
 * - Integration with WordPress asset loading system
 *
 * @since 0.3.2
 */
class Form_Asset_Optimizer {
	/**
	 * Enqueued assets for deduplication.
	 *
	 * @var array
	 */
	private array $enqueued_scripts = array();

	/**
	 * Enqueued styles for deduplication.
	 *
	 * @var array
	 */
	private array $enqueued_styles = array();

	/**
	 * Conditional asset rules.
	 *
	 * @var array
	 */
	private array $conditional_rules = array();

	/**
	 * Register a conditional asset rule.
	 *
	 * Defines conditions under which specific assets should be loaded.
	 * Useful for loading assets only when certain form features are used.
	 *
	 * @param string   $asset_handle Handle of the asset to conditionally load.
	 * @param callable $condition    Function that returns true when the asset should be loaded.
	 * @param string   $type         Asset type: 'script' or 'style'. Default: 'script'.
	 * @return void
	 *
	 * @example
	 * ```php
	 * $optimizer->add_conditional_rule(
	 *     'my-form-validation-js',
	 *     function() { return is_page('contact'); },
	 *     'script'
	 * );
	 * ```
	 */
	public function add_conditional_rule( string $asset_handle, callable $condition, string $type = 'script' ): void {
		$this->conditional_rules[ $asset_handle ] = array(
			'condition' => $condition,
			'type'      => $type,
		);
	}

	/**
	 * Enqueue script with optimization.
	 *
	 * @param string $handle    Script handle.
	 * @param string $src       Script source URL.
	 * @param array  $deps      Dependencies.
	 * @param string $version   Version string.
	 * @param bool   $in_footer Whether to load in footer.
	 * @return void
	 */
	public function enqueue_script( string $handle, string $src, array $deps = array(), string $version = '', bool $in_footer = true ): void {
		// Skip if already enqueued to prevent duplicates.
		if ( in_array( $handle, $this->enqueued_scripts, true ) ) {
			return;
		}

		// Check conditional rules.
		if ( isset( $this->conditional_rules[ $handle ] ) &&
			'script' === $this->conditional_rules[ $handle ]['type'] ) {
			$rule = $this->conditional_rules[ $handle ];
			if ( ! call_user_func( $rule['condition'] ) ) {
				return; // Condition not met, skip enqueuing.
			}
		}

		// Optimize dependencies - remove unnecessary ones.
		$optimized_deps = $this->optimize_dependencies( $deps, 'script' );

		wp_enqueue_script( $handle, $src, $optimized_deps, $version, $in_footer );

		// Add resource hints for performance.
		if ( ! is_admin() ) {
			$this->add_resource_hints( $src, 'script' );
		}

		$this->enqueued_scripts[] = $handle;
	}

	/**
	 * Enqueue style with optimization.
	 *
	 * @param string $handle  Style handle.
	 * @param string $src     Style source URL.
	 * @param array  $deps    Dependencies.
	 * @param string $version Version string.
	 * @param string $media   Media type.
	 * @return void
	 */
	public function enqueue_style( string $handle, string $src, array $deps = array(), string $version = '', string $media = 'all' ): void {
		// Skip if already enqueued to prevent duplicates.
		if ( in_array( $handle, $this->enqueued_styles, true ) ) {
			return;
		}

		// Check conditional rules.
		if ( isset( $this->conditional_rules[ $handle ] ) &&
			'style' === $this->conditional_rules[ $handle ]['type'] ) {
			$rule = $this->conditional_rules[ $handle ];
			if ( ! call_user_func( $rule['condition'] ) ) {
				return; // Condition not met, skip enqueuing.
			}
		}

		// Optimize dependencies.
		$optimized_deps = $this->optimize_dependencies( $deps, 'style' );

		wp_enqueue_style( $handle, $src, $optimized_deps, $version, $media );

		// Add resource hints for performance.
		if ( ! is_admin() ) {
			$this->add_resource_hints( $src, 'style' );
		}

		$this->enqueued_styles[] = $handle;
	}

	/**
	 * Optimize dependencies by removing redundant ones.
	 *
	 * @param array  $deps  Original dependencies.
	 * @param string $type  Asset type ('script' or 'style').
	 * @return array Optimized dependencies.
	 */
	private function optimize_dependencies( array $deps, string $type ): array {
		if ( empty( $deps ) ) {
			return $deps;
		}

		$optimized = array();

		// Core WordPress dependencies that are usually already loaded.
		$core_deps = array(
			'script' => array( 'jquery', 'wp-api', 'wp-i18n' ),
			'style'  => array( 'wp-components', 'wp-admin' ),
		);

		foreach ( $deps as $dep ) {
			// Skip core dependencies that are likely already loaded.
			if ( in_array( $dep, $core_deps[ $type ] ?? array(), true ) ) {
				// Only skip if the dependency is actually registered/enqueued globally.
				if ( 'script' === $type && wp_script_is( $dep, 'enqueued' ) ) {
					continue;
				} elseif ( 'style' === $type && wp_style_is( $dep, 'enqueued' ) ) {
					continue;
				}
			}

			$optimized[] = $dep;
		}

		return $optimized;
	}

	/**
	 * Add resource hints for better performance.
	 *
	 * @param string $url  Asset URL.
	 * @param string $type Asset type ('script' or 'style').
	 * @return void
	 */
	private function add_resource_hints( string $url, string $type ): void {
		if ( ! function_exists( 'wp_resource_hints' ) ) {
			return;
		}

		// Extract domain from URL.
		$parsed_url = wp_parse_url( $url );
		if ( ! isset( $parsed_url['host'] ) ) {
			return;
		}

		$domain = $parsed_url['host'];

		// Add preload hint for critical assets.
		if ( $this->is_critical_asset( $url, $type ) ) {
			add_filter(
				'wp_resource_hints',
				function ( $hints ) use ( $url, $type ) {
					$hint_type             = 'script' === $type ? 'script' : 'style';
					$hints[ $hint_type ][] = $url;
					return $hints;
				}
			);
		}

		// Add DNS prefetch for external domains.
		if ( ! $this->is_same_domain( $domain ) ) {
			add_filter(
				'wp_resource_hints',
				function ( $hints ) use ( $domain ) {
					$hints['dns-prefetch'][] = $domain;
					return $hints;
				}
			);
		}
	}

	/**
	 * Check if an asset is considered critical.
	 *
	 * @param string $url  Asset URL.
	 * @param string $type Asset type.
	 * @return bool True if critical.
	 */
	private function is_critical_asset( string $url, string $type ): bool {
		// Define critical assets by handle patterns.
		$critical_patterns = array(
			'script' => array( 'campaignbridge-core', 'form-validation' ),
			'style'  => array( 'campaignbridge-core', 'form-styles' ),
		);

		foreach ( $critical_patterns[ $type ] ?? array() as $pattern ) {
			if ( strpos( $url, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if domain matches current site.
	 *
	 * @param string $domain Domain to check.
	 * @return bool True if same domain.
	 */
	private function is_same_domain( string $domain ): bool {
		$site_domain = wp_parse_url( get_site_url(), PHP_URL_HOST );
		return $domain === $site_domain;
	}

	/**
	 * Defer non-critical scripts.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 * @return string Modified script tag.
	 */
	public function defer_non_critical_scripts( string $tag, string $handle, string $src ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $src parameter required by WordPress script_loader_tag hook signature

		// Define critical scripts that should load immediately.
		$critical_scripts = array(
			'campaignbridge-core',
			'jquery', // Usually needed immediately.
		);

		// Skip if this is a critical script.
		if ( in_array( $handle, $critical_scripts, true ) ) {
			return $tag;
		}

		// Add defer attribute to non-critical scripts.
		if ( strpos( $tag, '<script' ) === 0 && strpos( $tag, 'defer' ) === false ) {
			return str_replace( '<script ', '<script defer ', $tag );
		}

		return $tag;
	}

	/**
	 * Add preload headers for critical assets.
	 *
	 * @return void
	 */
	public function add_preload_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		// Preload critical CSS.
		$critical_css = $this->get_critical_css_url();
		if ( $critical_css ) {
			header( "Link: <{$critical_css}>; rel=preload; as=style", false );
		}

		// Preload critical JS.
		$critical_js = $this->get_critical_js_url();
		if ( $critical_js ) {
			header( "Link: <{$critical_js}>; rel=preload; as=script", false );
		}
	}

	/**
	 * Get critical CSS URL.
	 *
	 * @return string|null Critical CSS URL or null.
	 */
	private function get_critical_css_url(): ?string {
		// This would be implemented to return the URL of critical CSS
		// For now, return null.
		return null;
	}

	/**
	 * Get critical JS URL.
	 *
	 * @return string|null Critical JS URL or null.
	 */
	private function get_critical_js_url(): ?string {
		// This would be implemented to return the URL of critical JS
		// For now, return null.
		return null;
	}

	/**
	 * Get asset loading statistics.
	 *
	 * @return array Asset loading statistics.
	 */
	public function get_asset_stats(): array {
		return array(
			'scripts_enqueued'  => count( $this->enqueued_scripts ),
			'styles_enqueued'   => count( $this->enqueued_styles ),
			'script_handles'    => $this->enqueued_scripts,
			'style_handles'     => $this->enqueued_styles,
			'conditional_rules' => count( $this->conditional_rules ),
		);
	}
}
