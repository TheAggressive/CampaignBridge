<?php
/**
 * CampaignBridge Email Template custom post type.
 *
 * Registers the `cb_template` custom post type used for email templates.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
				'labels'                => array(
					'name'          => __( 'Email Templates', 'campaignbridge' ),
					'singular_name' => __( 'Email Template', 'campaignbridge' ),
				),
				'public'                => false,
				'show_ui'               => true,
				'show_in_menu'          => false,
				'show_in_admin_bar'     => false,
				'supports'              => array( 'title', 'editor' ),
				'show_in_rest'          => true,
				'rest_base'             => 'cb_template',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'       => 'post',
				'map_meta_cap'          => true,
			)
		);

		// Ensure REST API works properly for cb_template.
		add_action(
			'rest_api_init',
			function () {
				// Add custom field for raw content.
				register_rest_field(
					'cb_template',
					'content_raw',
					array(
						'get_callback' => function ( $post ) {
							return $post->post_content;
						},
						'schema'       => array(
							'description' => 'Raw post content for editing.',
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					)
				);
			}
		);

		// Ensure proper REST API permissions.
		add_filter(
			'rest_prepare_cb_template',
			function ( $response, $post, $request ) {
				// Allow access to all authenticated users for cb_template.
				if ( is_user_logged_in() ) {
					return $response;
				}
				return new \WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to access this template.', array( 'status' => 403 ) );
			},
			10,
			3
		);
	}
}
