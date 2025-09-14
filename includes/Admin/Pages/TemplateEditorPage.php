<?php
/**
 * Template Manager Admin Page for CampaignBridge Admin Interface.
 *
 * This class handles the Template Manager page, providing administrators
 * with a comprehensive interface for creating, editing, and managing
 * email campaign templates. It integrates with the WordPress block editor
 * and media library for visual template design.
 *
 * This page serves as the central hub for email template management,
 * enabling users to design professional email campaigns using blocks
 * and media assets.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Manager Page: handles the email template management interface.
 */
class TemplateEditorPage extends AdminPage {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-template-editor';

	/**
	 * Initialize the Template Manager page and set up asset management.
	 *
	 * This method sets up the Template Manager page by registering the necessary WordPress
	 * hooks for conditional asset loading. It ensures that page-specific CSS and
	 * JavaScript files are only loaded when viewing the Template Manager page, optimizing
	 * performance across the admin interface.
	 *
	 * Asset Management:
	 * - Hooks into admin_enqueue_scripts for conditional asset loading
	 * - Only loads template-manager-specific assets on the Template Manager page
	 * - Prevents unnecessary asset loading on other admin pages
	 * - Maintains optimal WordPress admin performance
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Hook into admin_enqueue_scripts to conditionally load assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_template_editor_assets' ) );
	}

	/**
	 * Conditionally enqueue Template Manager page-specific CSS and JavaScript assets.
	 *
	 * This method ensures that Template Manager page assets are only loaded when viewing
	 * the Template Manager page. It uses the PageUtils helper to check if the current
	 * page matches this class's page_slug property.
	 *
	 * Asset Management:
	 * - Hooks into admin_enqueue_scripts for conditional asset loading
	 * - Only loads template-manager-specific assets on the Template Manager page
	 * - Prevents unnecessary asset loading on other admin pages
	 * - Maintains optimal WordPress admin performance
	 * - Ensures WordPress version compatibility
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_template_editor_assets(): void {
		// Only load assets on the specific Template Manager page.
		if ( ! \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		// Core editor CSS.
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-block-editor' );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-format-library' );
		wp_enqueue_style( 'wp-edit-post' );
		wp_enqueue_style( 'wp-icons' );
		wp_enqueue_style( 'wp-interface' );

		// Core editor JS and registries.
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-dom-ready' );
		wp_enqueue_script( 'wp-icons' );
		wp_enqueue_script( 'wp-blocks' );
		wp_enqueue_script( 'wp-editor' );
		wp_enqueue_script( 'wp-block-editor' );
		wp_enqueue_script( 'wp-edit-post' );
		wp_enqueue_script( 'wp-keyboard-shortcuts' );
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'wp-block-library' );

		// Enqueue all assets declared by register_block_type() / block.json.
		if ( function_exists( 'wp_enqueue_registered_block_scripts_and_styles' ) ) {
			wp_enqueue_registered_block_scripts_and_styles( true );
		}

		// 🔑 IMPORTANT for 3rd-party blocks:
		// Many plugins enqueue their editor bundles on the 'enqueue_block_editor_assets' action.
		// That hook only runs on post.php/post-new.php, so trigger it for your page too:
		do_action( 'enqueue_block_editor_assets' );

		// Enqueue template-manager-specific assets only.
		wp_enqueue_style( 'campaignbridge-block-editor-style' );
		wp_enqueue_script( 'campaignbridge-block-editor-script' );

		// Load theme.json CSS variables & support CSS so color/spacing/etc. actually style the canvas.
		if ( function_exists( 'wp_enqueue_global_styles' ) ) {
			wp_enqueue_global_styles(); // theme.json variables.
		}
		if ( function_exists( 'wp_enqueue_block_support_styles' ) ) {
			wp_enqueue_block_support_styles( '' ); // generated support CSS (spacing/border/etc.).
		}
	}

	/**
	 * Render the complete Template Manager page with template management interface.
	 *
	 * This method generates the full Template Manager page HTML, providing administrators
	 * with a comprehensive interface for creating, editing, and managing email campaign
	 * templates. It integrates with the WordPress block editor and media library.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page . ', 'campaignbridge' ) );
		}

		self::display_messages();
		?>
		<div class="wrap">
			<div id="cb-block-editor-root" class="cb-block-editor-root"></div>
		</div>
		<?php
	}

	/**
	 * Get the localized page title for the Template Manager page.
	 *
	 * This method returns the human-readable title that will be displayed
	 * at the top of the Template Manager page. The title is localized for internationalization
	 * support and provides clear identification of the page's purpose .
	 *
	 * @since 0.1.0
	 * @return string The localized page title 'Template Manager' .
	 */
	public static function get_page_title(): string {
		return __( 'Template Editor', 'campaignbridge' );
	}
}
