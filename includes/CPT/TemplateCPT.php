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
				'supports'          => array( 'title', 'editor' ),
				'show_in_rest'      => true,
			)
		);
	}
}
