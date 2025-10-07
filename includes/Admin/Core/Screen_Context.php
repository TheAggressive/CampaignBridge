<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Screen Context
/**
 * Screen Context - Available in all screen files as $screen
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Screen Context class - Available in all screen files as $screen.
 *
 * Provides helper methods for form handling, asset loading, messages,
 * and data management within screen files.
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
	 * Array of success messages to display.
	 *
	 * @var array
	 */
	private array $messages = array();

	/**
	 * Array of error messages to display.
	 *
	 * @var array
	 */
	private array $errors = array();

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
	 * Set a value in the data array.
	 *
	 * @param string $key The key to set.
	 * @param mixed  $value The value to set.
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Get a value from the data array.
	 *
	 * @param string $key The key to get.
	 * @param mixed  $fallback The default value to return if the key is not set.
	 * @return mixed The value from the data array.
	 */
	public function get( string $key, $fallback = null ) {
		return $this->data[ $key ] ?? $fallback;
	}

	/**
	 * Get all data from the data array.
	 *
	 * @return array The data array.
	 */
	public function get_all(): array {
		return $this->data;
	}

	/**
	 * Check if a key exists in the data array.
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has( string $key ): bool {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Check if the request method is POST.
	 *
	 * @return bool True if the request method is POST, false otherwise.
	 */
	public function is_post(): bool {
		return ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Add a nonce field to the form.
	 *
	 * @param string $action The action to add the nonce field for.
	 * @return void
	 */
	public function nonce_field( string $action ): void {
		wp_nonce_field( 'cb_' . $action, '_wpnonce', true, true );
	}

	/**
	 * Verify a nonce field.
	 *
	 * @param string $action The action to verify the nonce field for.
	 * @return bool True if the nonce is valid, false otherwise.
	 */
	public function verify_nonce( string $action ): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is handled by wp_verify_nonce()
		return isset( $_POST['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'cb_' . $action );
	}

	/**
	 * Get a POST value.
	 *
	 * @param string $key The key to get.
	 * @param mixed  $fallback The default value to return if the key is not set.
	 * @return mixed The value from the POST array.
	 */
	public function post( string $key, $fallback = null ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Input is sanitized immediately below
		$value = wp_unslash( $_POST[ $key ] );

		// Sanitize based on value type.
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}
		if ( is_numeric( $value ) ) {
			return sanitize_text_field( $value );
		}

		// For other types, return as-is (already unslashed).
		return $value;
	}

	/**
	 * Enqueue a style.
	 *
	 * @param string $handle The handle to enqueue.
	 * @param string $src The source of the style.
	 * @param array  $deps The dependencies to enqueue.
	 * @param string $version The version of the style.
	 *
	 * @return void
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
	 * Enqueue a script.
	 *
	 * @param string $handle The handle to enqueue.
	 * @param string $src The source of the script.
	 * @param array  $deps The dependencies to enqueue.
	 * @param string $version The version of the script.
	 * @param bool   $in_footer Whether to enqueue the script in the footer.
	 *
	 * @return void
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
	 * Enqueue a built asset style.
	 *
	 * @param string $handle The handle to enqueue.
	 * @param string $asset_file_path The path to the asset file.
	 * @param array  $additional_deps The additional dependencies to enqueue.
	 *
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
	 * Enqueue a built asset script.
	 *
	 * @param string $handle The handle to enqueue.
	 * @param string $asset_file_path The path to the asset file.
	 * @param array  $additional_deps The additional dependencies to enqueue.
	 * @param bool   $in_footer Whether to enqueue the script in the footer.
	 *
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
	 * Enqueue a built asset.
	 *
	 * @param string $handle The handle to enqueue.
	 * @param string $asset_file_path The path to the asset file.
	 * @param bool   $enqueue_style Whether to enqueue the style.
	 * @param bool   $enqueue_script Whether to enqueue the script.
	 *
	 * @return array The asset was enqueued, false otherwise.
	 */
	public function asset_enqueue( string $handle, string $asset_file_path, bool $enqueue_style = true, bool $enqueue_script = true ): array {
		return array(
			'style'  => $enqueue_style ? $this->asset_enqueue_style( $handle, $asset_file_path ) : false,
			'script' => $enqueue_script ? $this->asset_enqueue_script( $handle, $asset_file_path ) : false,
		);
	}

	/**
	 * Localize a script.
	 *
	 * @param string $handle The handle to localize.
	 * @param string $object_name The name of the object to localize.
	 * @param array  $data The data to localize.
	 *
	 * @return void
	 */
	public function localize_script( string $handle, string $object_name, array $data ): void {
		wp_localize_script( 'cb-' . $handle, $object_name, $data );
	}

	/**
	 * Add a message.
	 *
	 * @param string $message The message to add.
	 *
	 * @return void
	 */
	public function add_message( string $message ): void {
		$this->messages[] = $message;
		add_action( 'admin_notices', array( $this, 'display_messages' ) );
	}

	/**
	 * Add an error.
	 *
	 * @param string $error The error to add.
	 *
	 * @return void
	 */
	public function add_error( string $error ): void {
		$this->errors[] = $error;
		add_action( 'admin_notices', array( $this, 'display_messages' ) );
	}

	/**
	 * Add a warning.
	 *
	 * @param string $warning The warning to add.
	 *
	 * @return void
	 */
	public function add_warning( string $warning ): void {
		add_action( 'admin_notices', fn() => printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $warning ) ) );
	}

	/**
	 * Add an info.
	 *
	 * @param string $info The info to add.
	 *
	 * @return void
	 */
	public function add_info( string $info ): void {
		add_action( 'admin_notices', fn() => printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html( $info ) ) );
	}

	/**
	 * Display messages.
	 *
	 * @return void
	 */
	public function display_messages(): void {
		foreach ( $this->messages as $message ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
		foreach ( $this->errors as $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	/**
	 * Get the screen info.
	 *
	 * @return array The screen info.
	 */
	public function get_screen_info(): array {
		return array(
			'name'        => $this->screen_name,
			'type'        => $this->screen_type,
			'current_tab' => $this->current_tab,
		);
	}

	/**
	 * Check if the current tab is the given tab.
	 *
	 * @param string $tab_name The name of the tab to check.
	 *
	 * @return bool True if the current tab is the given tab, false otherwise.
	 */
	public function is_tab( string $tab_name ): bool {
		return $this->current_tab === $tab_name;
	}

	/**
	 * Get the tab URL.
	 *
	 * @param string $tab_name The name of the tab to get the URL for.
	 *
	 * @return string The tab URL.
	 */
	public function get_tab_url( string $tab_name ): string {
		return add_query_arg( 'tab', $tab_name );
	}

	/**
	 * Get the controller.
	 *
	 * @return mixed The controller.
	 */
	public function get_controller() {
		return $this->controller;
	}
}
