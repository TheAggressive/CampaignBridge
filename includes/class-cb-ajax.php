<?php
/**
 * AJAX endpoints for CampaignBridge.
 *
 * @package CampaignBridge
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class CB_Ajax {
	/**
	 * Register all wp_ajax actions.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_campaignbridge_fetch_posts', array( __CLASS__, 'fetch_posts' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_template_slots', array( __CLASS__, 'fetch_template_slots' ) );
		add_action( 'wp_ajax_campaignbridge_render_preview', array( __CLASS__, 'render_preview' ) );
	}

	/**
	 * Fetch posts for a given post type.
	 *
	 * @return void Outputs JSON.
	 */
	public static function fetch_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post';
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post type' ), 400 );
		}

		$settings       = get_option( 'campaignbridge_settings' );
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
		if ( in_array( $post_type, $excluded_types, true ) ) {
			wp_send_json_error( array( 'message' => 'Post type excluded' ), 400 );
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $posts as $p ) {
			$title_raw     = (string) get_post_field( 'post_title', $p->ID );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$items[]       = array(
				'id'    => (int) $p->ID,
				'label' => $title_decoded,
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * Discover template slots for a template ID using parse_blocks.
	 *
	 * @return void Outputs JSON.
	 */
	public static function fetch_template_slots() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		if ( $template_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Missing template.' ), 400 );
		}
		$post = get_post( $template_id );
		if ( ! $post || 'cb_template' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => 'Invalid template.' ), 400 );
		}
		$slots = CB_Render::discover_slots_from_content( (string) $post->post_content );
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * Render a live HTML preview for a template and slots map.
	 *
	 * @return void Outputs JSON.
	 */
	public static function render_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$map         = array();
		if ( isset( $_POST['slots_map'] ) ) {
			$raw = $_POST['slots_map'];
			if ( is_string( $raw ) ) {
				$decoded = json_decode( wp_unslash( $raw ), true );
				if ( is_array( $decoded ) ) {
					$map = array_map( 'absint', $decoded );
				}
			} elseif ( is_array( $raw ) ) {
				$map = array_map( 'absint', $raw );
			}
		}
		if ( $template_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Missing template.' ), 400 );
		}
		$html = CB_Render::render_template_html( $template_id, $map );
		if ( '' === $html ) {
			wp_send_json_error( array( 'message' => 'Failed to render preview.' ), 400 );
		}
		wp_send_json_success( array( 'html' => $html ) );
	}
}
