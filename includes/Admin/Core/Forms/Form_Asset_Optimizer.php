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
	 * @var array<int, string>
	 */
	private array $enqueued_scripts = array();

	/**
	 * Enqueued styles for deduplication.
	 *
	 * @var array<int, string>
	 */
	private array $enqueued_styles = array();

	/**
	 * Conditional asset rules.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $conditional_rules = array();

	/**
	 * Custom critical resource search paths.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $critical_paths = array();

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
	 * Set custom search paths for critical resources.
	 *
	 * Allows customization of where to look for critical CSS and JS files.
	 * Useful for themes or plugins that organize assets differently.
	 *
	 * @param string        $type   Resource type: 'css' or 'js'.
	 * @param array<string> $paths  Array of relative paths to search (in order of preference).
	 * @return void
	 *
	 * @example
	 * ```php
	 * $optimizer->set_critical_paths('css', [
	 *     'dist/styles/critical.min.css',
	 *     'assets/critical/critical.min.css',
	 *     'public/css/critical.min.css'
	 * ]);
	 * ```
	 */
	public function set_critical_paths( string $type, array $paths ): void {
		$this->critical_paths[ $type ] = $paths;
	}

	/**
	 * Enqueue script with optimization.
	 *
	 * @param string        $handle    Script handle.
	 * @param string        $src       Script source URL.
	 * @param array<string> $deps      Dependencies.
	 * @param string        $version   Version string.
	 * @param bool          $in_footer Whether to load in footer.
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

		\wp_enqueue_script( $handle, $src, $optimized_deps, $version, $in_footer ); // phpcs:ignore CampaignBridge.Standard.Sniffs.Assets.AssetEnqueue.DirectAssetEnqueue -- Form_Asset_Optimizer provides specialized asset optimization functionality.

		// Add resource hints for performance.
		if ( ! \is_admin() ) {
			$this->add_resource_hints( $src, 'script' );
		}

		$this->enqueued_scripts[] = $handle;
	}

	/**
	 * Enqueue style with optimization.
	 *
	 * @param string        $handle  Style handle.
	 * @param string        $src     Style source URL.
	 * @param array<string> $deps    Dependencies.
	 * @param string        $version Version string.
	 * @param string        $media   Media type.
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

		// phpcs:ignore CampaignBridge.Standard.Sniffs.Assets.AssetEnqueue.DirectAssetEnqueue -- Form_Asset_Optimizer provides specialized asset optimization functionality.
		\wp_enqueue_style( $handle, $src, $optimized_deps, $version, $media );

		// Add resource hints for performance.
		if ( ! \is_admin() ) {
			$this->add_resource_hints( $src, 'style' );
		}

		$this->enqueued_styles[] = $handle;
	}

	/**
	 * Optimize dependencies by removing redundant ones.
	 *
	 * @param array<string> $deps  Original dependencies.
	 * @param string        $type  Asset type ('script' or 'style').
	 * @return array<string> Optimized dependencies.
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
				if ( 'script' === $type && \wp_script_is( $dep, 'enqueued' ) ) {
					continue;
				} elseif ( 'style' === $type && \wp_style_is( $dep, 'enqueued' ) ) {
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
		$parsed_url = \wp_parse_url( $url );
		if ( ! isset( $parsed_url['host'] ) ) {
			return;
		}

		$domain = $parsed_url['host'];

		// Add preload hint for critical assets.
		if ( $this->is_critical_asset( $url, $type ) ) {
			\add_filter(
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
			\add_filter(
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
		$site_domain = \wp_parse_url( \get_site_url(), PHP_URL_HOST );
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
	 * Preload critical CSS resources for better performance.
	 *
	 * Adds preload link tags in the document head for critical CSS files
	 * that are essential for the initial page render. This improves the
	 * loading priority of critical styles.
	 *
	 * @param array<string> $css_handles Array of critical CSS handles to preload.
	 * @return void
	 */
	public function preload_critical_css( array $css_handles = array() ): void {
		if ( \is_admin() || \wp_doing_ajax() ) {
			return;
		}

		// Default critical CSS handles if none specified.
		if ( empty( $css_handles ) ) {
			$css_handles = array(
				'campaignbridge-core',
				'campaignbridge-forms',
				'wp-components',
			);
		}

		$added_preloads = array();

		foreach ( $css_handles as $handle ) {
			// Check if style is registered.
			if ( ! \wp_style_is( $handle, 'registered' ) ) {
				continue;
			}

			// Get style source URL.
			$src = \wp_styles()->registered[ $handle ]->src;

			if ( ! $src ) {
				continue;
			}

			// Convert relative URLs to absolute.
			if ( ! \wp_parse_url( $src, PHP_URL_HOST ) ) {
				$src = \site_url( $src );
			}

			// Add preload link if not already added.
			if ( ! in_array( $src, $added_preloads, true ) ) {
				$this->add_preload_link( $src, 'style', 'high' );
				$added_preloads[] = $src;
			}
		}
	}

	/**
	 * Preload critical JavaScript resources for better performance.
	 *
	 * Adds preload link tags in the document head for critical JavaScript files
	 * that are essential for the initial page functionality. This improves the
	 * loading priority of critical scripts.
	 *
	 * @param array<string> $js_handles Array of critical JS handles to preload.
	 * @return void
	 */
	public function preload_critical_js( array $js_handles = array() ): void {
		if ( \is_admin() || \wp_doing_ajax() ) {
			return;
		}

		// Default critical JS handles if none specified.
		if ( empty( $js_handles ) ) {
			$js_handles = array(
				'campaignbridge-core',
				'campaignbridge-forms',
				'wp-api-fetch',
				'wp-i18n',
			);
		}

		$added_preloads = array();

		foreach ( $js_handles as $handle ) {
			// Check if script is registered.
			if ( ! \wp_script_is( $handle, 'registered' ) ) {
				continue;
			}

			// Get script source URL.
			$src = \wp_scripts()->registered[ $handle ]->src;

			if ( ! $src ) {
				continue;
			}

			// Convert relative URLs to absolute.
			if ( ! \wp_parse_url( $src, PHP_URL_HOST ) ) {
				$src = \site_url( $src );
			}

			// Add preload link if not already added.
			if ( ! in_array( $src, $added_preloads, true ) ) {
				$this->add_preload_link( $src, 'script', 'high' );
				$added_preloads[] = $src;
			}
		}
	}

	/**
	 * Add preload link tag to document head.
	 *
	 * @param string $url    Resource URL to preload.
	 * @param string $resource_type Resource type ('style', 'script', etc.).
	 * @param string $priority Priority level ('high', 'low').
	 * @return void
	 */
	private function add_preload_link( string $url, string $resource_type, string $priority = 'high' ): void {
		$crossorigin = $this->is_cross_origin( $url ) ? ' crossorigin' : '';
		$importance  = 'high' === $priority ? ' importance="high"' : '';

		$link_tag = sprintf(
			'<link rel="preload" href="%s" as="%s"%s%s>',
			esc_url( $url ),
			esc_attr( $resource_type ),
			$importance,
			$crossorigin
		);

		// Add to wp_head action.
		\add_action(
			'wp_head',
			function () use ( $link_tag ) {
				echo wp_kses(
					$link_tag . "\n",
					array(
						'link' => array(
							'rel'         => array(),
							'href'        => array(),
							'as'          => array(),
							'importance'  => array(),
							'crossorigin' => array(),
						),
					)
				);
			},
			1
		);
	}

	/**
	 * Check if URL is cross-origin.
	 *
	 * @param string $url URL to check.
	 * @return bool True if cross-origin.
	 */
	private function is_cross_origin( string $url ): bool {
		$url_host  = \wp_parse_url( $url, PHP_URL_HOST );
		$site_host = \wp_parse_url( \get_site_url(), PHP_URL_HOST );

		return $url_host && $site_host && $url_host !== $site_host;
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

		// Preload critical CSS via HTTP headers.
		$critical_css_url = $this->get_critical_css_url();
		if ( $critical_css_url ) {
			header( sprintf( 'Link: <%s>; rel=preload; as=style', esc_url( $critical_css_url ) ), false );
		}

		// Preload critical JS via HTTP headers.
		$critical_js_url = $this->get_critical_js_url();
		if ( $critical_js_url ) {
			header( sprintf( 'Link: <%s>; rel=preload; as=script', esc_url( $critical_js_url ) ), false );
		}
	}

	/**
	 * Get critical CSS URL.
	 *
	 * Returns the URL of the critical CSS file if it exists.
	 * Critical CSS contains styles essential for above-the-fold content.
	 *
	 * Checks multiple possible locations in order of preference:
	 * 1. Custom paths set via set_critical_paths()
	 * 2. Default paths: dist/critical/ (production), assets/css/ (fallback)
	 *
	 * @return string|null Critical CSS URL or null if file doesn't exist.
	 */
	private function get_critical_css_url(): ?string {
		$plugin_dir = \CampaignBridge_Plugin::path();
		$plugin_url = \CampaignBridge_Plugin::url();

		// Get search paths (custom or default).
		$possible_paths = $this->critical_paths['css'] ?? $this->get_default_critical_css_paths();

		// Allow developers to filter the search paths.
		$possible_paths = apply_filters( 'campaignbridge_critical_css_paths', $possible_paths, $this );

		foreach ( $possible_paths as $relative_path ) {
			$full_path = $plugin_dir . $relative_path;

			if ( file_exists( $full_path ) && is_readable( $full_path ) ) {
				$version  = filemtime( $full_path );
				$url_path = str_replace( $plugin_dir, '', $full_path );
				return $plugin_url . $url_path . '?ver=' . $version;
			}
		}

		return null;
	}

	/**
	 * Get default critical CSS search paths.
	 *
	 * @return array<string> Default paths to search for critical CSS.
	 */
	private function get_default_critical_css_paths(): array {
		return array(
			'dist/critical/styles/critical.css',    // Production build.
		);
	}

	/**
	 * Get critical JS URL.
	 *
	 * Returns the URL of the critical JavaScript file if it exists.
	 * Critical JS contains scripts essential for initial page functionality.
	 *
	 * Checks multiple possible locations in order of preference:
	 * 1. Custom paths set via set_critical_paths()
	 * 2. Default paths: dist/critical/ (production), assets/js/ (fallback)
	 *
	 * @return string|null Critical JS URL or null if file doesn't exist.
	 */
	private function get_critical_js_url(): ?string {
		$plugin_dir = \CampaignBridge_Plugin::path();
		$plugin_url = \CampaignBridge_Plugin::url();

		// Get search paths (custom or default).
		$possible_paths = $this->critical_paths['js'] ?? $this->get_default_critical_js_paths();

		// Allow developers to filter the search paths.
		$possible_paths = apply_filters( 'campaignbridge_critical_js_paths', $possible_paths, $this );

		foreach ( $possible_paths as $relative_path ) {
			$full_path = $plugin_dir . $relative_path;

			if ( file_exists( $full_path ) && is_readable( $full_path ) ) {
				$version  = filemtime( $full_path );
				$url_path = str_replace( $plugin_dir, '', $full_path );
				return $plugin_url . $url_path . '?ver=' . $version;
			}
		}

		return null;
	}

	/**
	 * Get default critical JS search paths.
	 *
	 * @return array<string> Default paths to search for critical JS.
	 */
	private function get_default_critical_js_paths(): array {
		return array(
			'dist/critical/scripts/critical.js',     // Production build.
		);
	}

	/**
	 * Get asset loading statistics.
	 *
	 * @return array<string, mixed> Asset loading statistics.
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
