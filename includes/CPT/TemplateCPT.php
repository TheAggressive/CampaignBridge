<?php
/**
 * CampaignBridge Email Template custom post type.
 *
 * Registers the `cb_template` custom post type used for email templates.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace CampaignBridge\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Registers the `cb_template` custom post type used for email templates.
 */
class TemplateCPT {
	/**
	 * Register the `cb_template` post type used for email templates.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type(
			'cb_template',
			array(
				'labels'            => array(
					'name'          => __( 'Email Templates', 'campaignbridge' ),
					'singular_name' => __( 'Email Template', 'campaignbridge' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_admin_bar' => false,
				'supports'          => array( 'editor' ),
				'show_in_rest'      => true,
			)
		);

		// Hide the title field in the block editor UI when designing templates.
		add_action(
			'enqueue_block_editor_assets',
			function () {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( $screen && isset( $screen->post_type ) && 'cb_template' === $screen->post_type ) {
					$css  = 'body.post-type-cb_template .editor-post-title__block,body.post-type-cb_template .editor-post-title,body.post-type-cb_template .block-editor-post-title,body.post-type-cb_template .editor-post-title__input{display:none!important;}';
					$css .= 'body.post-type-cb_template .edit-post-visual-editor__post-title-wrapper{display:none!important;margin:0!important;padding:0!important;height:0!important;}';
					wp_add_inline_style( 'wp-edit-post', $css );
					// Also attach to block editor handle for reliability across versions.
					wp_add_inline_style( 'wp-block-editor', $css );
				}
			}
		);

		// In the cb_template editor, hide the admin toolbar for a cleaner embedded experience.
		add_action(
			'current_screen',
			function ( $screen ) {
				if ( $screen && isset( $screen->post_type ) && 'cb_template' === $screen->post_type ) {
					add_filter( 'show_admin_bar', '__return_false' );
					add_action(
						'admin_head',
						function () {
							echo '<style>
							html.wp-toolbar{padding-top:0!important}
							#wpadminbar{display:none!important}
							body.wp-admin #wpcontent{padding-top:0!important}
							/* Extra safety for title inputs */
							body.post-type-cb_template .editor-post-title__block,body.post-type-cb_template .editor-post-title,body.post-type-cb_template .block-editor-post-title,body.post-type-cb_template .editor-post-title__input{display:none!important}
							/* Remove post title wrapper spacing */
							body.post-type-cb_template .edit-post-visual-editor__post-title-wrapper{display:none!important;margin:0!important;padding:0!important;height:0!important}
							</style>';
						}
					);
				}
			}
		);
	}
}
