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

use CampaignBridge\PostTypes\EmailTemplate;

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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_template_manager_assets' ) );
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
	public static function enqueue_template_manager_assets(): void {
		// Only load assets on the specific Template Manager page.
		if ( ! \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		// Editor foundations.
		wp_enqueue_script( 'wp-block-editor' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-core-data' );
		wp_enqueue_script( 'wp-blocks' );
		wp_enqueue_script( 'wp-keycodes' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-compose' );
		wp_enqueue_script( 'wp-primitives' ); // icons, etc.

		// Default formatting tools & common editor UX bits.
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_style( 'wp-format-library' );

		// Canvas styles (how blocks look in the editor).
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_style( 'wp-block-library-theme' );

		// Media modal for image blocks.
		wp_enqueue_media();

		// CRUCIAL: actually load scripts/styles for all registered blocks in editor context.
		if ( function_exists( 'wp_enqueue_registered_block_scripts_and_styles' ) ) {
			wp_enqueue_registered_block_scripts_and_styles( true );
		}

		// Enqueue template-manager-specific assets only.
		wp_enqueue_style( 'campaignbridge-block-editor' );
		wp_enqueue_script( 'campaignbridge-block-editor' );

		$current_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_add_inline_script(
			'campaignbridge-template-manager',
			sprintf(
				'window.CB_TM = %s;',
				wp_json_encode(
					array(
						'postType'      => EmailTemplate::POST_TYPE,
						'nonce'         => wp_create_nonce( 'wp_rest' ),
						'apiRoot'       => esc_url_raw( rest_url() ),
						'locale'        => get_user_locale(),
						'defaultTitle'  => __( 'Untitled template', 'campaignbridge' ),
						'currentPostId' => $current_id ? $current_id : null,
						'settings'      => get_block_editor_settings( array(), $post ),
						'debug'         => array(
							'wpVersion'       => get_bloginfo( 'version' ),
							'minVersion'      => '5.8.0',
							'hasBlockEditor'  => wp_script_is( 'wp-block-editor', 'enqueued' ),
							'hasBlocks'       => wp_script_is( 'wp-blocks', 'enqueued' ),
							'hasBlockLibrary' => wp_script_is( 'wp-block-library', 'enqueued' ),
							'hasEditPost'     => wp_script_is( 'wp-edit-post', 'enqueued' ),
							'hasElement'      => wp_script_is( 'wp-element', 'enqueued' ),
							'hasComponents'   => wp_script_is( 'wp-components', 'enqueued' ),
							'hasData'         => wp_script_is( 'wp-data', 'enqueued' ),
							'hasApiFetch'     => wp_script_is( 'wp-api-fetch', 'enqueued' ),
							'hasI18n'         => wp_script_is( 'wp-i18n', 'enqueued' ),
						),
					)
				)
			),
			'before'
		);
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
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'campaignbridge' ) );
		}

		self::display_messages();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>
			<hr class="wp-header-end">
			<div id="cb-template-manager-root" class="cb-tm-root"></div>
		</div>
		<?php
	}

	/**
	 * Get the localized page title for the Template Manager page.
	 *
	 * This method returns the human-readable title that will be displayed
	 * at the top of the Template Manager page. The title is localized for internationalization
	 * support and provides clear identification of the page's purpose.
	 *
	 * @since 0.1.0
	 * @return string The localized page title "Template Manager".
	 */
	public static function get_page_title(): string {
		return __( 'Template Manager', 'campaignbridge' );
	}
}
