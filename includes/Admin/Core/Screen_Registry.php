<?php
/**
 * Screen Registry - Auto-discovers and registers screens with full tabs support
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Screen Registry class - Auto-discovers and registers admin screens.
 *
 * Handles both simple screens (single PHP files) and tabbed screens (folders).
 * Supports controller auto-discovery and configuration overrides.
 *
 * @package CampaignBridge\Admin\Core
 */
class Screen_Registry {

	/**
	 * Path to the screens directory.
	 *
	 * @var string
	 */
	private string $screens_path;


	/**
	 * Parent menu slug.
	 *
	 * @var string
	 */
	private string $parent_slug;


	/**
	 * Constructor - Initialize screen registry.
	 *
	 * @param string $screens_path Path to the screens directory.
	 * @param string $parent_slug  Parent menu slug.
	 */
	public function __construct( string $screens_path, string $parent_slug = 'campaignbridge' ) {
		$this->screens_path = rtrim( $screens_path, '/' ) . '/';
		$this->parent_slug  = $parent_slug;
	}

	/**
	 * Initialize the screen registry by hooking into admin_menu.
	 *
	 * @return void
	 */
	public function init(): void {
		\add_action( 'admin_menu', array( $this, 'discover_and_register_screens' ), 20 );
	}

	/**
	 * Scan Screens directory and auto-discover all screens
	 *
	 * @return void
	 */
	public function discover_and_register_screens(): void {
		if ( ! is_dir( $this->screens_path ) ) {
			return;
		}

		foreach ( scandir( $this->screens_path ) as $item ) {
			// Skip special files.
			if ( '.' === $item || '..' === $item || strpos( $item, '_' ) === 0 || strpos( $item, '.' ) === 0 ) {
				continue;
			}

			$path = $this->screens_path . $item;

			if ( is_file( $path ) && pathinfo( $path, PATHINFO_EXTENSION ) === 'php' ) {
				// Single file = Simple screen.
				$this->register_simple_screen( $item );
			} elseif ( is_dir( $path ) ) {
				// Folder = Tabbed screen.
				$this->register_tabbed_screen( $item );
			}
		}
	}

	/**
	 * Register simple screen (single PHP file)
	 *
	 * @param string $filename The filename of the screen.
	 * @return void
	 */
	private function register_simple_screen( string $filename ): void {
		$screen_name = pathinfo( $filename, PATHINFO_FILENAME );
		$slug        = $this->generate_slug( $screen_name );

		// Load optional config (future: could support dashboard_config.php).
		$config = array();

		// Auto-discover controller.
		$config['controller'] = $this->discover_controller( $screen_name );

		// Merge with defaults.
		$config = array_merge(
			array(
				'menu_title' => $this->generate_title( $screen_name ),
				'page_title' => $this->generate_title( $screen_name ),
				'capability' => 'manage_options',
			),
			$config
		);

		$this->register_screen( $screen_name, $slug, $config, 'single' );
	}

	/**
	 * Register tabbed screen (folder-based)
	 *
	 * @param string $folder_name The name of the folder.
	 * @return void
	 */
	private function register_tabbed_screen( string $folder_name ): void {
		$slug        = $this->generate_slug( $folder_name );
		$folder_path = $this->screens_path . $folder_name;

		// Check for optional _config.php.
		$config_file = $folder_path . '/_config.php';
		$config      = file_exists( $config_file ) ? require $config_file : array();

		// Auto-discover controller if not specified.
		if ( ! isset( $config['controller'] ) ) {
			$config['controller'] = $this->discover_controller( $folder_name );
		}

		// Merge with defaults.
		$config = array_merge(
			array(
				'menu_title' => $this->generate_title( $folder_name ),
				'page_title' => $this->generate_title( $folder_name ),
				'capability' => 'manage_options',
			),
			$config
		);

		$this->register_screen( $folder_name, $slug, $config, 'tabbed' );
	}

	/**
	 * Register screen with WordPress.
	 *
	 * @param string               $screen_name The name of the screen.
	 * @param string               $slug The screen slug.
	 * @param array<string, mixed> $config The screen configuration.
	 * @param string               $type The screen type (single or tabbed).
	 * @return void
	 */
	private function register_screen( string $screen_name, string $slug, array $config, string $type ): void {
		$full_slug = $this->parent_slug . '-' . $slug;

		// Initialize controller if found.
		$controller = null;
		if ( $config['controller'] && class_exists( $config['controller'] ) ) {
			$controller = new $config['controller']();
		}

		// Register WordPress submenu page.
		$hook = \add_submenu_page(
			$this->parent_slug,
			$config['page_title'],
			$config['menu_title'],
			$config['capability'],
			$full_slug,
			fn() => $this->render_screen( $screen_name, $type, $controller, $config ),
			$config['position'] ?? null
		);

		// Screen registered successfully.

		// Hook: on page load (for form handling).
		\add_action(
			"load-{$hook}",
			function () use ( $controller ) {
				if ( $controller && method_exists( $controller, 'handle_request' ) ) {
					$controller->handle_request();
				}
			}
		);

		// Hook: enqueue assets.
		\add_action(
			'admin_enqueue_scripts',
			function ( $hook_suffix ) use ( $hook, $screen_name, $type, $config ) {
				if ( $hook_suffix === $hook ) {
					$this->enqueue_screen_assets( $screen_name, $type, $config );
				}
			}
		);
	}

	/**
	 * Render screen (simple or tabbed).
	 *
	 * @param string               $screen_name The name of the screen.
	 * @param string               $type The type of screen.
	 * @param mixed                $controller The controller instance.
	 * @param array<string, mixed> $config The configuration array.
	 * @return void
	 */
	private function render_screen( string $screen_name, string $type, $controller, array $config ): void {
		echo '<div class="wrap campaignbridge-screen">';
		echo '<h1>' . esc_html( $config['page_title'] ) . '</h1>';

		// Start output buffering to capture screen content and process forms.
		ob_start();

		if ( ! empty( $config['description'] ) ) {
			echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
		}

		if ( 'single' === $type ) {
			$this->render_simple_screen( $screen_name, $controller );
		} else {
			$this->render_tabbed_screen( $screen_name, $controller, $config );
		}

		// Get the buffered screen content.
		$screen_content = ob_get_clean();

		// Now that forms have been processed, display notices seamlessly right after the h1.
		settings_errors( 'campaignbridge_form' );

		// Output the screen content. Since we control all HTML generation server-side and
		// properly escape all dynamic values, we can safely output without additional sanitization.
		// This avoids maintenance burden of maintaining HTML whitelists.
		echo $screen_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Render any notices that were added during screen processing (forms, etc.).
		\CampaignBridge\Notices::render();

		// Fire custom hook for individual screens to display notices after content processing.
		// Security: Only allow hooks for valid screen names to prevent abuse.
		if ( $this->is_valid_screen_name( $screen_name ) ) {
			do_action( 'campaignbridge_form_notices', $screen_name );
		}

		echo '</div>';
	}

	/**
	 * Validate if a screen name is legitimate to prevent hook abuse.
	 *
	 * @param string $screen_name The screen name to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_screen_name( string $screen_name ): bool {
		// Only allow alphanumeric characters, hyphens, and underscores.
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $screen_name ) ) {
			return false;
		}

		// Check if this screen name exists in our registered screens.
		$screen_file = $this->screens_path . $screen_name . '.php';
		return file_exists( $screen_file );
	}

	/**
	 * Render simple screen (no tabs).
	 *
	 * @param string $screen_name The name of the screen.
	 * @param mixed  $controller The controller instance.
	 * @return void
	 */
	private function render_simple_screen( string $screen_name, $controller ): void {
		$screen_file = $this->screens_path . $screen_name . '.php';

		if ( ! file_exists( $screen_file ) ) {
			echo '<div class="notice notice-error"><p>Screen file not found: ' . esc_html( $screen_name ) . '.php</p></div>';
			return;
		}

		// Create $screen context.
		global $screen;
		$screen = new Screen_Context( $screen_name, 'single', null, $controller );

		// Load data from controller.
		if ( $controller && is_object( $controller ) && method_exists( $controller, 'get_data' ) ) {
			foreach ( $controller->get_data() as $key => $value ) {
				$screen->set( $key, $value );
			}
		}

		// Include screen file.
		include $screen_file;
	}

	/**
	 * Render tabbed screen.
	 *
	 * @param string               $screen_name The name of the screen.
	 * @param mixed                $controller The controller instance.
	 * @param array<string, mixed> $config The configuration array.
	 * @return void
	 */
	private function render_tabbed_screen( string $screen_name, $controller, array $config = array() ): void {
		$tabs = $this->prepare_tabs_for_screen( $screen_name, $config );

		if ( empty( $tabs ) ) {
			$this->render_no_tabs_error( $screen_name );
			return;
		}

		$active_tab = $this->determine_active_tab( $tabs );
		$this->render_tab_navigation( $tabs, $active_tab );
		$this->render_active_tab_content( $tabs, $active_tab, $screen_name, $controller, $config );
	}

	/**
	 * Prepare tabs for rendering.
	 *
	 * @param string               $screen_name The name of the screen.
	 * @param array<string, mixed> $config The configuration array.
	 * @return array<string, mixed> Prepared tabs array.
	 */
	private function prepare_tabs_for_screen( string $screen_name, array $config ): array {
		$screen_folder = $this->screens_path . $screen_name;

		// Auto-discover tabs with configuration support.
		$tabs = $this->discover_tabs( $screen_folder, $config );

		// Filter tabs based on user capabilities.
		return $this->filter_tabs_by_capability( $tabs );
	}

	/**
	 * Render error when no tabs are available.
	 *
	 * @param string $screen_name The name of the screen.
	 * @return void
	 */
	private function render_no_tabs_error( string $screen_name ): void {
		echo '<div class="notice notice-error"><p>No accessible tabs found in: ' . esc_html( $screen_name ) . '/</p></div>';
	}

	/**
	 * Determine which tab should be active.
	 *
	 * @param array<string, mixed> $tabs Available tabs.
	 * @return string The active tab slug.
	 */
	private function determine_active_tab( array $tabs ): string {
		// Get active tab from GET parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for tab navigation, not form processing.
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : null;

		// Validate tab exists and user has access.
		if ( $requested_tab && isset( $tabs[ $requested_tab ] ) ) {
			return $requested_tab;
		}

		// Fallback to first tab.
		return array_key_first( $tabs );
	}

	/**
	 * Render tab navigation.
	 *
	 * @param array<string, mixed> $tabs Available tabs.
	 * @param string               $active_tab The active tab slug.
	 * @return void
	 */
	private function render_tab_navigation( array $tabs, string $active_tab ): void {
		echo '<nav class="nav-tab-wrapper wp-clearfix">';

		foreach ( $tabs as $tab_slug => $tab_info ) {
			$active_class = $active_tab === $tab_slug ? ' nav-tab-active' : '';
			$url          = add_query_arg( 'tab', $tab_slug );

			// Add description as tooltip if available.
			$title_attr = isset( $tab_info['description'] ) ? ' title="' . esc_attr( $tab_info['description'] ) . '"' : '';

			printf(
				'<a href="%s" class="nav-tab%s"%s>%s</a>',
				esc_url( $url ),
				esc_attr( $active_class ),
				esc_attr( $title_attr ),
				esc_html( $tab_info['title'] )
			);
		}

		echo '</nav>';
	}

	/**
	 * Render the content of the active tab.
	 *
	 * @param array<string, mixed> $tabs Available tabs.
	 * @param string               $active_tab The active tab slug.
	 * @param string               $screen_name The name of the screen.
	 * @param mixed                $controller The controller instance.
	 * @param array<string, mixed> $config The configuration array.
	 * @return void
	 */
	private function render_active_tab_content( array $tabs, string $active_tab, string $screen_name, $controller, array $config ): void {
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			return;
		}

		echo '<div class="tab-content">';

		// Determine which controller to use for this tab.
		$tab_controller = $this->get_tab_controller( $tabs[ $active_tab ], $controller );

		// Create $screen context for tab.
		global $screen;
		$screen = new Screen_Context( $screen_name, 'tabbed', $active_tab, $tab_controller );

		// Load data from tab controller first, then screen controller.
		$this->load_tab_data( $tab_controller, $controller, $screen );

		// Load custom data from _config.php.
		if ( isset( $config['data'] ) && is_array( $config['data'] ) ) {
			foreach ( $config['data'] as $key => $value ) {
				$screen->set( $key, $value );
			}
		}

		// Include tab file.
		include $tabs[ $active_tab ]['file'];

		echo '</div>';
	}

	/**
	 * Auto-discover tabs from folder with configuration support.
	 *
	 * @param string               $folder_path The path to the folder.
	 * @param array<string, mixed> $config      The configuration array.
	 * @return array<string, mixed> The tabs.
	 */
	private function discover_tabs( string $folder_path, array $config = array() ): array {
		if ( ! is_dir( $folder_path ) ) {
			return array();
		}

		$php_files  = $this->get_php_files_from_folder( $folder_path );
		$tab_config = $config['tabs'] ?? array();
		$tabs       = array();

		foreach ( $php_files as $file ) {
			$tab_name = $this->get_tab_name_from_file( $file );
			$tab_info = $this->create_tab_info( $tab_name, $file, $config, $tab_config );

			$tabs[ $tab_name ] = $tab_info;
		}

		return $this->sort_tabs_by_order( $tabs );
	}

	/**
	 * Get PHP files from a folder, excluding config files.
	 *
	 * @param string $folder_path The path to the folder.
	 * @return array<int, string> Array of PHP file paths.
	 */
	private function get_php_files_from_folder( string $folder_path ): array {
		$files = glob( $folder_path . '/*.php' );

		if ( ! is_array( $files ) ) {
			return array();
		}

		// Filter out files starting with underscore (config files).
		return array_filter(
			$files,
			function ( $file ) {
				$filename = basename( $file );
				return strpos( $filename, '_' ) !== 0;
			}
		);
	}

	/**
	 * Extract tab name from PHP file path.
	 *
	 * @param string $file_path The file path.
	 * @return string The tab name.
	 */
	private function get_tab_name_from_file( string $file_path ): string {
		$filename = basename( $file_path );
		return pathinfo( $filename, PATHINFO_FILENAME );
	}

	/**
	 * Create tab information array.
	 *
	 * @param string               $tab_name   The tab name.
	 * @param string               $file_path  The file path.
	 * @param array<string, mixed> $config     The screen config.
	 * @param array<string, mixed> $tab_config The tab-specific config.
	 * @return array<string, mixed> The tab information.
	 */
	private function create_tab_info( string $tab_name, string $file_path, array $config, array $tab_config ): array {
		// Start with auto-generated defaults.
		$tab_info = array(
			'name'       => $tab_name,
			'title'      => $this->generate_title( $tab_name ),
			'slug'       => $this->generate_slug( $tab_name ),
			'file'       => $file_path,
			'capability' => $config['capability'] ?? 'manage_options',
			'order'      => 10,
			'controller' => null,
		);

		// Apply custom configuration if available.
		if ( isset( $tab_config[ $tab_name ] ) && is_array( $tab_config[ $tab_name ] ) ) {
			$tab_info = $this->apply_custom_tab_config( $tab_info, $tab_config[ $tab_name ] );
		}

		return $tab_info;
	}

	/**
	 * Apply custom tab configuration.
	 *
	 * @param array<string, mixed> $tab_info      The default tab info.
	 * @param array<string, mixed> $custom_config The custom config.
	 * @return array<string, mixed> The updated tab info.
	 */
	private function apply_custom_tab_config( array $tab_info, array $custom_config ): array {
		$mappings = array(
			'label'       => 'title',
			'capability'  => 'capability',
			'order'       => 'order',
			'description' => 'description',
			'controller'  => 'controller',
		);

		foreach ( $mappings as $config_key => $tab_key ) {
			if ( isset( $custom_config[ $config_key ] ) ) {
				$value = $custom_config[ $config_key ];

				// Special handling for order (ensure it's an integer).
				if ( 'order' === $tab_key ) {
					$value = intval( $value );
				}

				$tab_info[ $tab_key ] = $value;
			}
		}

		return $tab_info;
	}

	/**
	 * Sort tabs by their order value.
	 *
	 * @param array<string, mixed> $tabs The tabs array.
	 * @return array<string, mixed> The sorted tabs.
	 */
	private function sort_tabs_by_order( array $tabs ): array {
		uasort(
			$tabs,
			function ( $a, $b ) {
				return ( $a['order'] ?? 10 ) <=> ( $b['order'] ?? 10 );
			}
		);

		return $tabs;
	}

	/**
	 * Filter tabs based on user capabilities.
	 *
	 * @param array<string, mixed> $tabs The tabs array.
	 * @return array<string, mixed> Filtered tabs that user has access to.
	 */
	private function filter_tabs_by_capability( array $tabs ): array {
		return array_filter(
			$tabs,
			function ( $tab_info ) {
				$capability = $tab_info['capability'] ?? 'manage_options';

				// Allow false to explicitly hide tabs.
				if ( false === $capability ) {
					return false;
				}

				// Check if user has required capability.
				return current_user_can( $capability );
			}
		);
	}

	/**
	 * Get the appropriate controller for a tab.
	 *
	 * @param array<string, mixed> $tab_info The tab information.
	 * @param mixed                $screen_controller The screen controller.
	 * @return mixed The controller to use for this tab.
	 */
	private function get_tab_controller( array $tab_info, $screen_controller ) {
		// Check if tab has its own controller.
		if ( ! empty( $tab_info['controller'] ) ) {
			$controller_class = $tab_info['controller'];

			if ( class_exists( $controller_class ) ) {
				$tab_controller = new $controller_class();

				// Inject screen controller reference if the tab controller supports it.
				if ( property_exists( $tab_controller, 'screen_controller' ) ) {
					$tab_controller->screen_controller = $screen_controller;
				}

				return $tab_controller;
			}
		}

		// Fallback to screen controller.
		return $screen_controller;
	}

	/**
	 * Load data from controllers into screen context.
	 *
	 * @param mixed          $tab_controller The tab controller.
	 * @param mixed          $screen_controller The screen controller.
	 * @param Screen_Context $screen The screen context.
	 */
	private function load_tab_data( $tab_controller, $screen_controller, Screen_Context $screen ): void {
		// Load data from tab controller first (higher priority).
		if ( $tab_controller && is_object( $tab_controller ) && method_exists( $tab_controller, 'get_data' ) ) {
			foreach ( $tab_controller->get_data() as $key => $value ) {
				$screen->set( $key, $value );
			}
		}

		// Then load data from screen controller (lower priority, won't override tab data).
		if ( $screen_controller && $screen_controller !== $tab_controller && is_object( $screen_controller ) && method_exists( $screen_controller, 'get_data' ) ) {
			foreach ( $screen_controller->get_data() as $key => $value ) {
				// Only set if not already set by tab controller.
				if ( ! $screen->has( $key ) ) {
					$screen->set( $key, $value );
				}
			}
		}
	}

	/**
	 * Auto-discover controller by convention
	 *
	 * @param string $name The name of the controller.
	 * @return ?string The controller class name.
	 *
	 * Examples:
	 * - dashboard.php → Dashboard_Controller
	 * - settings/ → Settings_Controller
	 * - email_templates/ → Email_Templates_Controller
	 */
	private function discover_controller( string $name ): ?string {
		$controller_class = $this->name_to_controller_class( $name );

		if ( class_exists( $controller_class ) ) {
			return $controller_class;
		}

		return null;
	}

	/**
	 * Convert name to controller class name
	 *
	 * @param string $name The name of the controller.
	 * @return string The controller class name.
	 */
	private function name_to_controller_class( string $name ): string {
		// Replace hyphens and underscores with spaces.
		$class_name = str_replace( array( '-', '_' ), ' ', $name );

		// Capitalize each word.
		$class_name = ucwords( $class_name );

		// Remove spaces.
		$class_name = str_replace( ' ', '_', $class_name );

		// Add Controller suffix and namespace.
		return "CampaignBridge\\Admin\\Controllers\\{$class_name}_Controller";
	}

	/**
	 * Enqueue screen-specific assets.
	 *
	 * @param string               $screen_name The name of the screen.
	 * @param string               $type The type of screen.
	 * @param array<string, mixed> $config The configuration array.
	 * @return void
	 */
	private function enqueue_screen_assets( string $screen_name, string $type, array $config ): void {
		global $screen;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for tab navigation, not form processing.
		$screen = new Screen_Context( $screen_name, $type, isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : null, null );

		// Traditional assets.
		if ( isset( $config['assets']['styles'] ) ) {
			foreach ( $config['assets']['styles'] as $handle => $src ) {
				$screen->enqueue_style( $handle, $src );
			}
		}

		if ( isset( $config['assets']['scripts'] ) ) {
			foreach ( $config['assets']['scripts'] as $handle => $script ) {
				$src  = is_array( $script ) ? $script['src'] : $script;
				$deps = is_array( $script ) && isset( $script['deps'] ) ? $script['deps'] : array( 'jquery' );
				$screen->enqueue_script( $handle, $src, $deps );
			}
		}

		// Built assets.
		if ( isset( $config['assets']['asset_styles'] ) ) {
			foreach ( $config['assets']['asset_styles'] as $handle => $asset_file ) {
				$screen->asset_enqueue_style( $handle, $asset_file );
			}
		}

		if ( isset( $config['assets']['asset_scripts'] ) ) {
			foreach ( $config['assets']['asset_scripts'] as $handle => $asset_data ) {
				if ( is_string( $asset_data ) ) {
					$screen->asset_enqueue_script( $handle, $asset_data );
				} elseif ( is_array( $asset_data ) ) {
					$asset_file = $asset_data['src'] ?? $asset_data['path'] ?? '';
					if ( $asset_file ) {
						$screen->asset_enqueue_script(
							$handle,
							$asset_file,
							$asset_data['deps'] ?? array(),
							$asset_data['in_footer'] ?? true
						);
					}
				}
			}
		}

		if ( isset( $config['assets']['asset_both'] ) ) {
			foreach ( $config['assets']['asset_both'] as $handle => $asset_file ) {
				$screen->asset_enqueue( $handle, $asset_file );
			}
		}
	}

	/**
	 * Generate a URL-friendly slug from a name.
	 *
	 * @param string $name The name to convert to a slug.
	 * @return string The generated slug.
	 */
	private function generate_slug( string $name ): string {
		return strtolower( str_replace( array( '_', ' ' ), '-', $name ) );
	}

	/**
	 * Generate a human-readable title from a name.
	 *
	 * @param string $name The name to convert to a title.
	 * @return string The generated title.
	 */
	private function generate_title( string $name ): string {
		return ucwords( str_replace( array( '_', '-' ), ' ', $name ) );
	}
}
