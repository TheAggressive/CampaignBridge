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
	 * Checks if the current request method is POST.
	 *
	 * @return bool True if the request method is POST, false otherwise.
	 */
	public function is_post(): bool {
		return ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Outputs a nonce field for CSRF protection in forms.
	 *
	 * @param string $action The action name for the nonce.
	 */
	public function nonce_field( string $action ): void {
		wp_nonce_field( 'cb_' . $action, '_wpnonce', true, true );
	}

	/**
	 * Verifies a nonce for CSRF protection.
	 *
	 * @param string $action The action name to verify against.
	 * @return bool True if the nonce is valid, false otherwise.
	 */
	public function verify_nonce( string $action ): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is handled by wp_verify_nonce()
		return isset( $_POST['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'cb_' . $action );
	}

	/**
	 * Retrieves and sanitizes a POST value with automatic type-based sanitization.
	 *
	 * @param string $key      The POST key to retrieve.
	 * @param mixed  $fallback The default value if the key is not set.
	 * @return mixed The sanitized POST value or the fallback.
	 */
	public function post( string $key, $fallback = null ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Input is sanitized immediately below
		$value = wp_unslash( $_POST[ $key ] );

		// Enhanced sanitization based on value type and context.
		if ( is_string( $value ) ) {
			// Use appropriate sanitization based on field name context.
			if ( strpos( $key, 'email' ) !== false || 'reply_to' === $key ) {
				return sanitize_email( $value );
			}
			if ( strpos( $key, 'url' ) !== false ) {
				return esc_url_raw( $value );
			}
			if ( strpos( $key, 'html' ) !== false || strpos( $key, 'content' ) !== false ) {
				return wp_kses_post( $value );
			}
			// Default text field sanitization.
			return sanitize_text_field( $value );
		}

		if ( is_array( $value ) ) {
			// Recursively sanitize array values.
			return $this->sanitize_array_input( $value, $key );
		}

		if ( is_numeric( $value ) ) {
			// For numeric values, ensure they're properly cast.
			if ( strpos( $key, 'id' ) !== false || strpos( $key, 'limit' ) !== false ) {
				return absint( $value );
			}
			return sanitize_text_field( $value );
		}

		if ( is_bool( $value ) ) {
			return (bool) $value;
		}

		// For other types, return as-is (already unslashed).
		// Log potential security concern for unknown types.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Unhandled input type for field "%s": %s', $key, gettype( $value ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $value;
	}

	/**
	 * Sanitize array input recursively.
	 *
	 * Applies basic WordPress sanitization functions based on data type,
	 * without domain-specific business logic. For custom sanitization rules,
	 * use a dedicated sanitizer service.
	 *
	 * @param array  $input_array The array to sanitize.
	 * @param string $parent_key  The parent key for context (optional).
	 * @return array The sanitized array.
	 */
	private function sanitize_array_input( array $input_array, string $parent_key = '' ): array {
		$sanitized = array();

		foreach ( $input_array as $key => $value ) {
			// Sanitize the key.
			$sanitized_key = sanitize_key( $key );

			// Recursively sanitize the value based on type.
			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_array_input( $value, $parent_key . '[' . $key . ']' );
			} elseif ( is_string( $value ) ) {
				$sanitized[ $sanitized_key ] = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $sanitized_key ] = is_int( $value ) ? absint( $value ) : sanitize_text_field( (string) $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $sanitized_key ] = (bool) $value;
			} elseif ( is_null( $value ) ) {
				$sanitized[ $sanitized_key ] = null;
			} else {
				// For unknown types, convert to string and sanitize, or skip if conversion fails.
				if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
					$sanitized[ $sanitized_key ] = sanitize_text_field( (string) $value );
				} else {
					// Log and skip non-convertible types.
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'Skipping non-convertible value type for "%s[%s]": %s', $parent_key, $key, gettype( $value ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					continue;
				}
			}
		}

		return $sanitized;
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
	public function asset_enqueue_script( string $handle, string $asset_file_path, array $additional_deps = [], bool $in_footer = true ): bool {
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
