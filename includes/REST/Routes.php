<?php
/**
 * CampaignBridge REST routes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Render\Render as CB_Render;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * REST API routes for CampaignBridge admin operations.
 */
class Routes {
	/**
	 * Option key used to store plugin settings.
	 *
	 * @var string
	 */
	private static $option_name = 'campaignbridge_settings';

	/**
	 * Registered providers map indexed by slug.
	 *
	 * @var array<string,object>
	 */
	private static $providers = array();

	/**
	 * Initialize shared state.
	 *
	 * @param string $option_name Options key used by the plugin.
	 * @param array  $providers   Registered providers map.
	 * @return void
	 */
	public static function init( $option_name, $providers ) {
		self::$option_name = (string) $option_name;
		self::$providers   = is_array( $providers ) ? $providers : array();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = 'campaignbridge/v1';

		register_rest_route(
			$ns,
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_posts' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'post_type' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/templates/(?P<id>\\d+)/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_template_slots' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/templates/(?P<id>\\d+)/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'r_template_preview' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'slots_map' => array(
						'type'     => 'object',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/sections',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_mc_sections' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/audiences',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_mc_audiences' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/templates',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_mc_templates' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'r_mc_verify' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'api_key' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Whether current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /posts endpoint.
	 *
	 * @param \WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_posts( WP_REST_Request $req ) {
		$post_type = $req->get_param( 'post_type' ) ? sanitize_key( $req->get_param( 'post_type' ) ) : 'post';
		if ( ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'bad_post_type', 'Invalid post type', array( 'status' => 400 ) );
		}
		$settings       = get_option( self::$option_name );
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
		if ( in_array( $post_type, $excluded_types, true ) ) {
			return new \WP_Error( 'excluded_post_type', 'Post type excluded', array( 'status' => 400 ) );
		}
		$post_ids = get_posts(
			array(
				'post_type'              => $post_type,
				'posts_per_page'         => 100,
				'post_status'            => 'publish',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true,
			)
		);
		$items    = array();
		foreach ( (array) $post_ids as $pid ) {
			$title_raw     = (string) get_post_field( 'post_title', $pid );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$items[]       = array(
				'id'    => (int) $pid,
				'label' => $title_decoded,
			);
		}
		return \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * GET /templates/{id}/slots endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_template_slots( WP_REST_Request $req ) {
		$template_id = absint( $req->get_param( 'id' ) );
		if ( $template_id <= 0 ) {
			return new \WP_Error( 'missing_template', 'Missing template', array( 'status' => 400 ) );
		}
		$post = get_post( $template_id );
		if ( ! $post || 'cb_template' !== $post->post_type ) {
			return new \WP_Error( 'bad_template', 'Invalid template', array( 'status' => 400 ) );
		}
		$slots = CB_Render::discover_slots_from_content( (string) $post->post_content );
		return rest_ensure_response( array( 'slots' => $slots ) );
	}

	/**
	 * POST /templates/{id}/preview endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_template_preview( WP_REST_Request $req ) {
		$template_id = absint( $req->get_param( 'id' ) );
		$map         = array();
		$raw         = $req->get_param( 'slots_map' );
		if ( is_array( $raw ) ) {
			$map = array_map( 'absint', $raw );
		}
		if ( $template_id <= 0 ) {
			return new \WP_Error( 'missing_template', 'Missing template', array( 'status' => 400 ) );
		}
		$html = CB_Render::render_template_html( $template_id, $map );
		if ( '' === $html ) {
			return new \WP_Error( 'render_failed', 'Failed to render preview', array( 'status' => 400 ) );
		}
		return \rest_ensure_response( array( 'html' => $html ) );
	}

	/**
	 * GET /mailchimp/sections endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_mc_sections( WP_REST_Request $req ) {
		$settings = get_option( self::$option_name );
		$provider = isset( $settings['provider'] ) && isset( self::$providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $provider || ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'unsupported', 'Only supported for Mailchimp', array( 'status' => 400 ) );
		}
		$refresh  = (bool) $req->get_param( 'refresh' );
		$sections = self::$providers['mailchimp']->get_section_keys( $settings, $refresh );
		if ( \is_wp_error( $sections ) ) {
			return $sections;
		}
		return \rest_ensure_response( array( 'sections' => $sections ) );
	}

	/**
	 * GET /mailchimp/audiences endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_mc_audiences( WP_REST_Request $req ) {
		$settings = get_option( self::$option_name );
		if ( empty( $settings['api_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Missing API key', array( 'status' => 400 ) );
		}
		if ( ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'not_available', 'Mailchimp provider not available', array( 'status' => 400 ) );
		}
		$refresh = (bool) $req->get_param( 'refresh' );
		$items   = self::$providers['mailchimp']->get_audiences( $settings, $refresh );
		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * GET /mailchimp/templates endpoint.
	 *
	 * @param \WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_mc_templates( WP_REST_Request $req ) {
		$settings = get_option( self::$option_name );
		if ( empty( $settings['api_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Missing API key', array( 'status' => 400 ) );
		}
		if ( ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'not_available', 'Mailchimp provider not available', array( 'status' => 400 ) );
		}
		$refresh = (bool) $req->get_param( 'refresh' );
		$items   = self::$providers['mailchimp']->get_templates( $settings, $refresh );
		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * POST /mailchimp/verify endpoint.
	 *
	 * @param \WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_mc_verify( WP_REST_Request $req ) {
		$settings = get_option( self::$option_name );
		$prov     = isset( $settings['provider'] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $prov ) {
			return new \WP_Error( 'bad_provider', 'Provider is not Mailchimp', array( 'status' => 400 ) );
		}
		$api_key = $req->get_param( 'api_key' );
		if ( $api_key ) {
			$settings['api_key'] = (string) $api_key;
		}
		if ( empty( $settings['api_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Missing API key', array( 'status' => 400 ) );
		}
		if ( ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'not_available', 'Mailchimp provider not available', array( 'status' => 400 ) );
		}
		$items = self::$providers['mailchimp']->get_audiences( $settings );
		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'ok' => true ) );
	}
}
