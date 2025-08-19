<?php
/**
 * Template Manager for CampaignBridge.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Simple test to see if this file is loaded
error_log( 'CampaignBridge: TemplateManager.php file loaded!' );

/**
 * Template Manager class.
 */
class TemplateManager {
	/**
	 * Initialize the template manager.
	 *
	 * @return void
	 */
	public static function init() {
		// Debug: Check if this method is being called
		error_log( 'CampaignBridge: TemplateManager::init() called' );

		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		error_log( 'CampaignBridge: TemplateManager hooks added' );
	}

	/**
	 * Add the Template Manager admin menu.
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		error_log( 'CampaignBridge: TemplateManager::add_admin_menu() called' );

		// Add as submenu under CampaignBridge instead of separate top-level menu
		add_submenu_page(
			'campaignbridge', // Parent slug
			'Template Manager (NEW)',
			'Template Manager (NEW)',
			'edit_posts',
			'cb-template-manager',
			array( __CLASS__, 'render_admin_page' )
		);

		error_log( 'CampaignBridge: TemplateManager submenu page added' );
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		?>
		<div class="wrap">
			<h1>Template Manager</h1>
			<div id="cb-template-manager-app">
				<div class="cb-toolbar">
					<button type="button" class="button button-primary" id="cb-new-template">New Template</button>
					<button type="button" class="button" id="cb-load-template">Load Template</button>
				</div>
				<div class="cb-template-list">
					<select id="cb-template-select" class="cb-input-wide">
						<option value="">— Select a template —</option>
						<?php
						$templates = get_posts(
							array(
								'post_type'   => 'cb_template',
								'numberposts' => -1,
								'orderby'     => 'date',
								'order'       => 'DESC',
							)
						);
						foreach ( $templates as $template ) {
							printf(
								'<option value="%d">%s</option>',
								$template->ID,
								esc_html( $template->post_title )
							);
						}
						?>
					</select>
				</div>
				<div id="cb-editor-root" class="cb-editor-container"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue required assets for the template manager.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'campaignbridge_page_cb-template-manager' !== $hook ) {
			return;
		}

		error_log( 'CampaignBridge: TemplateManager::enqueue_assets() called for hook: ' . $hook );

		// Ensure editor environment is available.
		wp_enqueue_editor();

		// Core scripts/styles for native block editor app.
		wp_enqueue_script( 'wp-dom-ready' );
		wp_enqueue_script( 'wp-edit-post' );
		wp_enqueue_script( 'wp-editor' );
		wp_enqueue_script( 'wp-blocks' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-core-data' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-block-editor' );
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'wp-block-library' );

		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-format-library' );
		wp_enqueue_style( 'wp-block-editor' );
		wp_enqueue_style( 'wp-block-library' );

		// Unregister the Classic (freeform) block to avoid TinyMCE dependency errors in embedded context.
		wp_add_inline_script(
			'wp-edit-post',
			"wp.domReady(function(){try{if(window.wp&&wp.blocks&&wp.blocks.unregisterBlockType){wp.blocks.unregisterBlockType('core/freeform');}}catch(e){console.warn('CampaignBridge: could not unregister core/freeform',e);}});"
		);

		// Enqueue our template manager script.
		wp_enqueue_script(
			'cb-template-manager',
			plugins_url( 'dist/scripts/template-manager.js', dirname( __DIR__ ) ),
			array( 'wp-edit-post', 'wp-core-data', 'wp-data', 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-block-editor', 'wp-dom-ready' ),
			filemtime( plugin_dir_path( dirname( __DIR__ ) . 'dist/scripts/template-manager.js' ) ),
			true
		);

		// Settings and basic diagnostics.
		wp_add_inline_script(
			'cb-template-manager',
			'
			console.log("CampaignBridge: TemplateManager settings loaded");
			console.log("wp.editPost available:", !!window.wp?.editPost);
			console.log("wp.editPost.initializeEditor available:", !!window.wp?.editPost?.initializeEditor);

			window.CB_TMPL_SETTINGS=' . wp_json_encode(
				array(
					'cpt'      => 'cb_template',
					'restBase' => 'cb_template',
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'siteUrl'  => site_url(),
				)
			) . ';
			'
		);

		// Minimal admin styling for container.
		wp_add_inline_style(
			'wp-admin',
			'
			.cb-template-manager { margin: 20px 0; }
			.cb-toolbar { margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
			.cb-template-list { margin-bottom: 20px; }
			.cb-input-wide { width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; }
			.cb-editor-container { border: 1px solid #ccd0d4; border-radius: 4px; background: #fff; min-height: 600px; }
		'
		);
	}
}
