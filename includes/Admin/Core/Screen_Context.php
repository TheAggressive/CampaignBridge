<?php
/**
 * Screen Context - Available in all screen files as $screen
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Screen Context class - Available in all screen files as $screen.
 *
 * Provides helper methods for asset loading, data management, and
 * screen information within admin screen files.
 *
 * @package CampaignBridge\Admin\Core
 */
class Screen_Context {

	/**
	 * The name of the current screen.
	 *
	 * @var string
	 */
	private string $screen_name;

	/**
	 * The type of the current screen (single or tabbed).
	 *
	 * @var string
	 */
	private string $screen_type;

	/**
	 * The current active tab (for tabbed screens).
	 *
	 * @var string|null
	 */
	private ?string $current_tab;

	/**
	 * The controller instance for this screen.
	 *
	 * @var mixed
	 */
	private $controller;

	/**
	 * Array of data available to screen templates.
	 *
	 * @var array
	 */
	private array $data = array();


	/**
	 * Constructor - Initialize screen context.
	 *
	 * @param string  $screen_name The name of the screen.
	 * @param string  $screen_type The type of screen.
	 * @param ?string $current_tab The current tab.
	 * @param mixed   $controller The controller instance.
	 */
	public function __construct( string $screen_name, string $screen_type, ?string $current_tab, $controller ) {
		$this->screen_name = $screen_name;
		$this->screen_type = $screen_type;
		$this->current_tab = $current_tab;
		$this->controller  = $controller;
	}

	/**
	 * Stores a value in the screen data array for use in templates.
	 *
	 * @param string $key   The key to set.
	 * @param mixed  $value The value to set.
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Retrieves a value from the screen data array with optional fallback.
	 *
	 * @param string $key      The key to get.
	 * @param mixed  $fallback The default value to return if the key is not set.
	 * @return mixed The value from the data array or the fallback.
	 */
	public function get( string $key, $fallback = null ) {
		return $this->data[ $key ] ?? $fallback;
	}

	/**
	 * Returns all data from the screen data array.
	 *
	 * @return array All stored data for the screen.
	 */
	public function get_all(): array {
		return $this->data;
	}

	/**
	 * Checks if a key exists in the screen data array.
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has( string $key ): bool {
		return isset( $this->data[ $key ] );
	}


	/**
	 * Enqueues a CSS stylesheet for the admin screen.
	 *
	 * @param string      $handle  The handle to enqueue.
	 * @param string      $src     The source path relative to plugin URL.
	 * @param array       $deps    The dependencies to enqueue.
	 * @param string|null $version The version of the style.
	 */
	public function enqueue_style( string $handle, string $src, array $deps = array(), ?string $version = null ): void {
		wp_enqueue_style(
			'cb-' . $handle,
			\CampaignBridge_Plugin::url() . $src,
			array_merge( array( 'cb-admin-global' ), $deps ),
			$version ?? \CampaignBridge_Plugin::VERSION
		);
	}

	/**
	 * Enqueues a JavaScript file for the admin screen.
	 *
	 * @param string      $handle    The handle to enqueue.
	 * @param string      $src       The source path relative to plugin URL.
	 * @param array       $deps      The dependencies to enqueue.
	 * @param string|null $version   The version of the script.
	 * @param bool        $in_footer Whether to enqueue the script in the footer.
	 */
	public function enqueue_script( string $handle, string $src, array $deps = array(), ?string $version = null, bool $in_footer = true ): void {
		wp_enqueue_script(
			'cb-' . $handle,
			\CampaignBridge_Plugin::url() . $src,
			$deps,
			$version ?? \CampaignBridge_Plugin::VERSION,
			$in_footer
		);
	}

	/**
	 * Enqueues a built CSS asset with dependencies from asset.php file.
	 *
	 * @param string $handle           The handle to enqueue.
	 * @param string $asset_file_path  The path to the asset.php file.
	 * @param array  $additional_deps  Additional dependencies to enqueue.
	 * @return bool True if the asset was enqueued, false otherwise.
	 */
	public function asset_enqueue_style( string $handle, string $asset_file_path, array $additional_deps = array() ): bool {
		$asset_file = \CampaignBridge_Plugin::path() . $asset_file_path;
		if ( ! file_exists( $asset_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "CampaignBridge: Asset file not found: {$asset_file}" );
			}
			return false;
		}

		$asset    = require $asset_file;
		$css_file = str_replace( '.asset.php', '.css', $asset_file_path );

		if ( ! file_exists( \CampaignBridge_Plugin::path() . $css_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "CampaignBridge: CSS file not found: {$css_file}" );
			}
			return false;
		}

		wp_enqueue_style(
			'cb-' . $handle,
			\CampaignBridge_Plugin::url() . $css_file,
			array_merge( array( 'cb-admin-global' ), $asset['dependencies'] ?? array(), $additional_deps ),
			$asset['version'] ?? \CampaignBridge_Plugin::VERSION
		);

		return true;
	}

	/**
	 * Enqueues a built JavaScript asset with dependencies from asset.php file.
	 *
	 * @param string $handle           The handle to enqueue.
	 * @param string $asset_file_path  The path to the asset.php file.
	 * @param array  $additional_deps  Additional dependencies to enqueue.
	 * @param bool   $in_footer        Whether to enqueue the script in the footer.
	 * @return bool True if the asset was enqueued, false otherwise.
	 */
	public function asset_enqueue_script( string $handle, string $asset_file_path, array $additional_deps = array(), bool $in_footer = true ): bool {
		$asset_file = \CampaignBridge_Plugin::path() . $asset_file_path;
		if ( ! file_exists( $asset_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "CampaignBridge: Asset file not found: {$asset_file}" );
			}
			return false;
		}

		$asset   = require $asset_file;
		$js_file = str_replace( '.asset.php', '.js', $asset_file_path );

		if ( ! file_exists( \CampaignBridge_Plugin::path() . $js_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "CampaignBridge: JS file not found: {$js_file}" );
			}
			return false;
		}

		wp_enqueue_script(
			'cb-' . $handle,
			\CampaignBridge_Plugin::url() . $js_file,
			array_merge( $asset['dependencies'] ?? array(), $additional_deps ),
			$asset['version'] ?? \CampaignBridge_Plugin::VERSION,
			$in_footer
		);

		return true;
	}

	/**
	 * Enqueues both CSS and JS assets from the same asset.php file.
	 *
	 * @param string $handle           The handle to enqueue.
	 * @param string $asset_file_path  The path to the asset.php file.
	 * @param bool   $enqueue_style    Whether to enqueue the style.
	 * @param bool   $enqueue_script   Whether to enqueue the script.
	 * @return array Array with 'style' and 'script' boolean results.
	 */
	public function asset_enqueue( string $handle, string $asset_file_path, bool $enqueue_style = true, bool $enqueue_script = true ): array {
		return array(
			'style'  => $enqueue_style ? $this->asset_enqueue_style( $handle, $asset_file_path ) : false,
			'script' => $enqueue_script ? $this->asset_enqueue_script( $handle, $asset_file_path ) : false,
		);
	}

	/**
	 * Localizes a script with PHP data for use in JavaScript.
	 *
	 * @param string $handle      The script handle to localize.
	 * @param string $object_name The JavaScript object name.
	 * @param array  $data        The data to pass to JavaScript.
	 */
	public function localize_script( string $handle, string $object_name, array $data ): void {
		wp_localize_script( 'cb-' . $handle, $object_name, $data );
	}


	/**
	 * Returns information about the current screen.
	 *
	 * @return array Array containing screen name, type, and current tab.
	 */
	public function get_screen_info(): array {
		return array(
			'name'        => $this->screen_name,
			'type'        => $this->screen_type,
			'current_tab' => $this->current_tab,
		);
	}

	/**
	 * Checks if the given tab is currently active.
	 *
	 * @param string $tab_name The name of the tab to check.
	 * @return bool True if the current tab matches the given tab.
	 */
	public function is_tab( string $tab_name ): bool {
		return $this->current_tab === $tab_name;
	}

	/**
	 * Generates a URL for switching to the specified tab.
	 *
	 * @param string $tab_name The name of the tab to generate URL for.
	 * @return string The URL with tab parameter added.
	 */
	public function get_tab_url( string $tab_name ): string {
		return add_query_arg( 'tab', $tab_name );
	}

	/**
	 * Returns the controller instance associated with this screen.
	 *
	 * @return mixed The controller instance or null if none set.
	 */
	public function get_controller() {
		return $this->controller;
	}
}
