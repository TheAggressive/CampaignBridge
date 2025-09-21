<?php
/**
 * Template Editor Page - Block editor interface for email template design.
 *
 * Provides a WordPress block editor interface for creating and editing
 * email campaign templates with drag-and-drop functionality.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\AdminPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Editor Page: handles the email template management interface.
 *
 * This class extends AdminPage to provide a block editor interface for
 * creating and managing email campaign templates.
 */
class TemplateEditorPage extends AdminPage {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-template-editor';

	/**
	 * WordPress core assets required for the block editor.
	 */
	private const WP_CORE_SCRIPT = 'wp-edit-post';
	private const WP_CORE_STYLE  = 'wp-edit-post';

	/**
	 * Plugin-specific asset handles.
	 */
	private const PLUGIN_SCRIPT = 'campaignbridge-block-editor-script';
	private const PLUGIN_STYLE  = 'campaignbridge-block-editor-style';

	/**
	 * Required user capability for accessing this page.
	 */
	private const REQUIRED_CAPABILITY = 'edit_posts';

	/**
	 * HTML element ID for the block editor root.
	 */
	private const EDITOR_ROOT_ID = 'cb-block-editor-root';

	/**
	 * CSS class for the block editor root element.
	 */
	private const EDITOR_ROOT_CLASS = 'cb-block-editor-root';

	/**
	 * Initialize the Template Editor page.
	 *
	 * Registers hooks for conditional asset loading.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_template_editor_assets' ) );
	}

	/**
	 * Conditionally enqueue assets for the Template Editor page.
	 *
	 * Only loads assets when viewing the Template Editor page.
	 * Leverages WordPress's automatic dependency resolution.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_template_editor_assets(): void {
		if ( ! self::should_load_assets() ) {
			return;
		}

		// Enqueue all template editor dependencies (error logging handled internally).
		self::enqueue_template_editor_dependencies();
	}

	/**
	 * Check if assets should be loaded for the current page.
	 *
	 * @return bool True if assets should be loaded.
	 */
	private static function should_load_assets(): bool {
		return \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() );
	}

	/**
	 * Enqueue all required dependencies for the Template Editor.
	 *
	 * This method enqueues all necessary assets including WordPress core assets,
	 * third-party block assets, plugin-specific assets, and global styles.
	 * Error logging is handled internally.
	 *
	 * @return bool True if all assets loaded successfully.
	 */
	private static function enqueue_template_editor_dependencies(): bool {
		$errors = array();

		// Enqueue all asset types.
		if ( ! self::enqueue_wordpress_core_assets() ) {
			$errors[] = 'WordPress core assets';
		}
		if ( ! self::enqueue_third_party_block_assets() ) {
			$errors[] = 'third-party block assets';
		}
		if ( ! self::enqueue_plugin_assets() ) {
			$errors[] = 'plugin assets';
		}
		if ( ! self::enqueue_global_styles() ) {
			$errors[] = 'global styles';
		}

		// Log errors if any occurred.
		if ( ! empty( $errors ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[CampaignBridge] Some assets failed to load in Template Editor: %s',
					implode( ', ', $errors )
				)
			);
		}

		return empty( $errors );
	}

	/**
	 * Enqueue WordPress core block editor assets.
	 *
	 * This provides the foundation for the block editor interface.
	 *
	 * @return bool True if assets were enqueued successfully, false on error.
	 */
	private static function enqueue_wordpress_core_assets(): bool {
		try {
			wp_enqueue_script( self::WP_CORE_SCRIPT );
			wp_enqueue_style( self::WP_CORE_STYLE );
			return true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Failed to enqueue WordPress core assets: %s',
						$e->getMessage()
					)
				);
			}
			return false;
		}
	}

	/**
	 * Enqueue third-party block assets and trigger related actions.
	 *
	 * Ensures compatibility with plugins that register blocks.
	 *
	 * @return bool True if assets were enqueued successfully, false on error.
	 */
	private static function enqueue_third_party_block_assets(): bool {
		try {
			if ( function_exists( 'wp_enqueue_registered_block_scripts_and_styles' ) ) {
				wp_enqueue_registered_block_scripts_and_styles( true );
			}

			// Trigger block editor assets action for third-party plugins.
			do_action( 'enqueue_block_editor_assets' );
			return true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Failed to enqueue third-party block assets: %s',
						$e->getMessage()
					)
				);
			}
			return false;
		}
	}

	/**
	 * Enqueue CampaignBridge-specific plugin assets.
	 *
	 * WordPress automatically handles dependencies declared in .asset.php
	 *
	 * @return bool True if assets were enqueued successfully, false on error.
	 */
	private static function enqueue_plugin_assets(): bool {
		try {
			wp_enqueue_style( self::PLUGIN_STYLE );
			wp_enqueue_script( self::PLUGIN_SCRIPT );
			return true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Failed to enqueue plugin assets: %s',
						$e->getMessage()
					)
				);
			}
			return false;
		}
	}

	/**
	 * Enqueue global styles for theme.json support.
	 *
	 * Provides CSS variables and block support styles for the editor.
	 *
	 * @return bool True if assets were enqueued successfully, false on error.
	 */
	private static function enqueue_global_styles(): bool {
		try {
			if ( function_exists( 'wp_enqueue_global_styles' ) ) {
				wp_enqueue_global_styles();
			}

			if ( function_exists( 'wp_enqueue_block_support_styles' ) ) {
				wp_enqueue_block_support_styles( '' );
			}
			return true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Failed to enqueue global styles: %s',
						$e->getMessage()
					)
				);
			}
			return false;
		}
	}

	/**
	 * Render the Template Editor page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! self::user_can_access() ) {
			self::handle_access_denied();
		}

		self::display_messages();
		self::render_page_html();
	}

	/**
	 * Check if current user has permission to access the Template Editor.
	 *
	 * @return bool True if user can access the page.
	 */
	private static function user_can_access(): bool {
		return current_user_can( self::REQUIRED_CAPABILITY );
	}

	/**
	 * Handle access denied for the Template Editor page.
	 *
	 * @return void
	 */
	private static function handle_access_denied(): void {
		wp_die(
			esc_html__( 'Sorry, you are not allowed to access this page.', 'campaignbridge' )
		);
	}

	/**
	 * Render the main page HTML structure.
	 *
	 * @return void
	 */
	private static function render_page_html(): void {
		?>
		<div class="wrap">
			<div id="<?php echo esc_attr( self::EDITOR_ROOT_ID ); ?>" class="<?php echo esc_attr( self::EDITOR_ROOT_CLASS ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Get the page title for the Template Editor.
	 *
	 * @since 0.1.0
	 * @return string The localized page title.
	 */
	public static function get_page_title(): string {
		return __( 'Template Editor', 'campaignbridge' );
	}
}
