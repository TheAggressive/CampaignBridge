<?php
/**
 * Email Template custom post type registration.
 *
 * @package CampaignBridge
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class CB_Template_CPT {
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
