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

		if ( ! empty( $config['description'] ) ) {
			echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
		}

		if ( 'single' === $type ) {
			$this->render_simple_screen( $screen_name, $controller );
		} else {
			$this->render_tabbed_screen( $screen_name, $controller, $config );
		}

		echo '</div>';
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
		$screen_folder = $this->screens_path . $screen_name;

		// Auto-discover tabs with configuration support.
		$tabs = $this->discover_tabs( $screen_folder, $config );

		// Filter tabs based on user capabilities.
		$tabs = $this->filter_tabs_by_capability( $tabs );

		if ( empty( $tabs ) ) {
			echo '<div class="notice notice-error"><p>No accessible tabs found in: ' . esc_html( $screen_name ) . '/</p></div>';
			return;
		}

		// Get active tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for tab navigation, not form processing.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : array_key_first( $tabs );

		// Validate tab exists and user has access.
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = array_key_first( $tabs );
		}

		// Render tab navigation.
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

		// Render active tab content.
		if ( isset( $tabs[ $active_tab ] ) ) {
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
	}

	/**
	 * Auto-discover tabs from folder with configuration support.
	 *
	 * @param string               $folder_path The path to the folder.
	 * @param array<string, mixed> $config      The configuration array.
	 * @return array<string, mixed> The tabs.
	 */
	private function discover_tabs( string $folder_path, array $config = array() ): array {
		$tabs = array();

		if ( ! is_dir( $folder_path ) ) {
			return $tabs;
		}

		// Get tab configuration if available.
		$tab_config = isset( $config['tabs'] ) && is_array( $config['tabs'] ) ? $config['tabs'] : array();

		$files = glob( $folder_path . '/*.php' );
		if ( ! is_array( $files ) ) {
			return $tabs;
		}

		foreach ( $files as $file ) {
			$filename = basename( $file );

			// Skip files starting with _ (like _config.php).
			if ( strpos( $filename, '_' ) === 0 ) {
				continue;
			}

			$tab_name = pathinfo( $filename, PATHINFO_FILENAME );

			// Start with auto-generated defaults.
			$tab_info = array(
				'name'       => $tab_name,
				'title'      => $this->generate_title( $tab_name ),
				'slug'       => $this->generate_slug( $tab_name ),
				'file'       => $file,
				'capability' => isset( $config['capability'] ) ? $config['capability'] : 'manage_options',
				'order'      => 10, // Default order.
				'controller' => null, // Will be set from config or auto-discovery.
			);

			// Merge with custom configuration if available..
			if ( isset( $tab_config[ $tab_name ] ) && is_array( $tab_config[ $tab_name ] ) ) {
				$custom_config = $tab_config[ $tab_name ];

				// Override with custom settings.
				if ( isset( $custom_config['label'] ) ) {
					$tab_info['title'] = $custom_config['label'];
				}
				if ( isset( $custom_config['capability'] ) ) {
					$tab_info['capability'] = $custom_config['capability'];
				}
				if ( isset( $custom_config['order'] ) ) {
					$tab_info['order'] = intval( $custom_config['order'] );
				}
				if ( isset( $custom_config['description'] ) ) {
					$tab_info['description'] = $custom_config['description'];
				}
				if ( isset( $custom_config['controller'] ) ) {
					$tab_info['controller'] = $custom_config['controller'];
				}
			}

			$tabs[ $tab_name ] = $tab_info;
		}

		// Sort tabs by order.
		uasort(
			$tabs,
			function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
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
