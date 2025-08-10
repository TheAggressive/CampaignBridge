<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CampaignBridge {
	private $option_name = 'campaignbridge_settings';
	private $providers   = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'init', array( 'CB_Template_CPT', 'register' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( 'CB_Blocks', 'register' ) );
		add_action( 'admin_enqueue_scripts', array( 'CB_Admin_UI', 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_posts', array( 'CB_Ajax', 'fetch_posts' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_sections', array( $this, 'ajax_fetch_sections' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_mailchimp_audiences', array( $this, 'ajax_fetch_mailchimp_audiences' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_mailchimp_templates', array( $this, 'ajax_fetch_mailchimp_templates' ) );
		add_action( 'wp_ajax_campaignbridge_verify_mailchimp', array( $this, 'ajax_verify_mailchimp' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_template_slots', array( $this, 'ajax_fetch_template_slots' ) );
		add_action( 'wp_ajax_campaignbridge_render_preview', array( $this, 'ajax_render_preview' ) );

		// Register providers.
		require_once __DIR__ . '/providers/interface-campaignbridge-provider.php';
		require_once __DIR__ . '/providers/class-campaignbridge-provider-mailchimp.php';
		require_once __DIR__ . '/providers/class-campaignbridge-provider-html.php';
		// Refactored modules
		require_once __DIR__ . '/class-cb-template-cpt.php';
		require_once __DIR__ . '/class-cb-blocks.php';
		require_once __DIR__ . '/class-cb-ajax.php';
		require_once __DIR__ . '/class-cb-render.php';
		require_once __DIR__ . '/class-cb-admin-ui.php';
		$this->providers = array(
			'mailchimp' => new CampaignBridge_Provider_Mailchimp(),
			'html'      => new CampaignBridge_Provider_HTML(),
		);
	}

	public function add_admin_menu() {
		CB_Admin_UI::init( $this->option_name, $this->providers );
		add_menu_page( 'CampaignBridge', 'CampaignBridge', 'manage_options', 'mailchimp-post-blast', array( 'CB_Admin_UI', 'render_page' ) );
	}

	public function register_template_cpt() {
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

	public function register_shortcodes() {
		add_shortcode(
			'cb_slot',
			function ( $atts ) {
				$atts = shortcode_atts( array( 'key' => '' ), $atts, 'cb_slot' );
				$key  = sanitize_key( $atts['key'] );
				if ( '' === $key ) {
					return '';
				}
				return '<!--CB_SLOT:' . esc_html( $key ) . '-->';
			}
		);
	}

	// Block registration handled by CB_Blocks

	public function register_settings() {
		register_setting(
			'campaignbridge',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$clean             = array();
		$previous          = get_option( $this->option_name, array() );
		$clean['provider'] = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : 'mailchimp';
		$posted_api_key    = isset( $input['api_key'] ) ? (string) $input['api_key'] : '';
		if ( '' === $posted_api_key && isset( $previous['api_key'] ) ) {
			$clean['api_key'] = $previous['api_key'];
		} else {
			$clean['api_key'] = sanitize_text_field( $posted_api_key );
		}
		$clean['audience_id'] = isset( $input['audience_id'] ) ? sanitize_text_field( $input['audience_id'] ) : '';
		$clean['template_id'] = isset( $input['template_id'] ) ? absint( $input['template_id'] ) : 0;
		// Convert included_post_types to exclude_post_types for storage.
		$clean['exclude_post_types'] = array();
		if ( isset( $input['included_post_types'] ) && is_array( $input['included_post_types'] ) ) {
			$included = array();
			foreach ( $input['included_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( post_type_exists( $pt ) ) {
					$included[] = $pt;
				}
			}
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			foreach ( $public_types as $pt ) {
				if ( ! in_array( $pt, $included, true ) ) {
					$clean['exclude_post_types'][] = $pt;
				}
			}
		}
		return $clean;
	}

	public function plugin_page() {
		CB_Admin_UI::init( $this->option_name, $this->providers );
		CB_Admin_UI::render_page();
	}

	public function ajax_fetch_sections() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		$provider = isset( $settings['provider'] ) && isset( $this->providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $provider ) {
			wp_send_json_error( array( 'message' => 'Only supported for Mailchimp.' ), 400 );
		}
		$sections = $this->providers['mailchimp']->get_section_keys( $settings );
		if ( is_wp_error( $sections ) ) {
			wp_send_json_error( array( 'message' => $sections->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'sections' => $sections ) );
	}

	public function ajax_fetch_template_slots() {
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
		$content = (string) $post->post_content;
		$slots   = array();
		// Parse blocks and discover email-post-slot blocks
		$blocks = function_exists( 'parse_blocks' ) ? parse_blocks( $content ) : array();
		$walk   = function ( $nodes ) use ( &$walk, &$slots ) {
			foreach ( $nodes as $b ) {
				if ( ! is_array( $b ) ) {
					continue;
				}
				$name  = isset( $b['blockName'] ) ? (string) $b['blockName'] : '';
				$attrs = isset( $b['attrs'] ) && is_array( $b['attrs'] ) ? $b['attrs'] : array();
				if ( 'campaignbridge/email-post-slot' === $name ) {
					$slot_id      = isset( $attrs['slotId'] ) ? sanitize_key( (string) $attrs['slotId'] ) : '';
					$slot_id      = '' === $slot_id ? 'slot_' . wp_generate_password( 6, false, false ) : $slot_id;
					$show_image   = isset( $attrs['showImage'] ) ? (bool) $attrs['showImage'] : true;
					$show_excerpt = isset( $attrs['showExcerpt'] ) ? (bool) $attrs['showExcerpt'] : true;
					$cta_label    = isset( $attrs['ctaLabel'] ) ? (string) $attrs['ctaLabel'] : 'Read more';
					$slots[]      = array(
						'key'         => $slot_id,
						'showImage'   => $show_image,
						'showExcerpt' => $show_excerpt,
						'ctaLabel'    => $cta_label,
					);
				}
				if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
					$walk( $b['innerBlocks'] );
				}
			}
		};
		$walk( $blocks );

		// Fallback: support legacy marker comments
		if ( empty( $slots ) ) {
			$matches = array();
			preg_match_all( '/<!--CB_SLOT:([a-z0-9_\-]+)-->/i', $content, $matches );
			if ( ! empty( $matches[1] ) ) {
				$keys = array_values( array_unique( array_map( 'sanitize_key', $matches[1] ) ) );
				foreach ( $keys as $k ) {
					$slots[] = array(
						'key'         => $k,
						'showImage'   => true,
						'showExcerpt' => true,
						'ctaLabel'    => 'Read more',
					);
				}
			}
		}
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	public function ajax_render_preview() {
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
			$raw_map = $_POST['slots_map'];
			if ( is_string( $raw_map ) ) {
				$decoded = json_decode( wp_unslash( $raw_map ), true );
				if ( is_array( $decoded ) ) {
					$map = array_map( 'absint', $decoded );
				}
			} elseif ( is_array( $raw_map ) ) {
				$map = array_map( 'absint', $raw_map );
			}
		}
		if ( $template_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Missing template.' ), 400 );
		}
		$html = $this->render_email_template_html( $template_id, $map );
		if ( '' === $html ) {
			wp_send_json_error( array( 'message' => 'Failed to render preview.' ), 400 );
		}
		wp_send_json_success( array( 'html' => $html ) );
	}

	private function render_email_template_html( $template_id, $slots_map ) {
		$post = get_post( $template_id );
		if ( ! $post || 'cb_template' !== $post->post_type ) {
			return '';
		}
		$content = (string) $post->post_content;
		$blocks  = function_exists( 'parse_blocks' ) ? parse_blocks( $content ) : array();
		$render  = function ( $node ) use ( &$render, $slots_map ) {
			if ( ! is_array( $node ) ) {
				return '';
			}
			$name  = isset( $node['blockName'] ) ? (string) $node['blockName'] : '';
			$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			if ( 'campaignbridge/email-post-slot' === $name ) {
				$slot_id      = isset( $attrs['slotId'] ) ? sanitize_key( (string) $attrs['slotId'] ) : '';
				$slot_id      = '' === $slot_id ? 'slot_' . wp_generate_password( 6, false, false ) : $slot_id;
				$post_id      = isset( $slots_map[ $slot_id ] ) ? absint( $slots_map[ $slot_id ] ) : 0;
				$show_image   = isset( $attrs['showImage'] ) ? (bool) $attrs['showImage'] : true;
				$show_excerpt = isset( $attrs['showExcerpt'] ) ? (bool) $attrs['showExcerpt'] : true;
				$cta_label    = isset( $attrs['ctaLabel'] ) ? (string) $attrs['ctaLabel'] : 'Read more';
				return $this->generate_email_post_card( $post_id, $show_image, $show_excerpt, $cta_label );
			}
			// Render other blocks normally.
			return function_exists( 'render_block' ) ? render_block( $node ) : '';
		};
		$html    = '';
		foreach ( $blocks as $b ) {
			$html .= $render( $b );
		}
		return "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width'></head><body>" . $html . '</body></html>';
	}

	private function generate_email_post_card( $post_id, $show_image, $show_excerpt, $cta_label ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$title   = esc_html( get_the_title( $post ) );
		$link    = esc_url( get_permalink( $post ) );
		$image   = $show_image ? get_the_post_thumbnail_url( $post, 'medium' ) : '';
		$excerpt = '';
		if ( $show_excerpt ) {
			$raw     = (string) get_post_field( 'post_content', $post );
			$excerpt = wp_kses_post( wpautop( wp_trim_words( wp_strip_all_tags( $raw ), 40 ) ) );
		}
		$cta      = esc_html( $cta_label ? $cta_label : 'Read more' );
		$img_html = $image ? sprintf( '<img src="%s" alt="" style="display:block;width:100%%;height:auto;border:0;" />', esc_url( $image ) ) : '';
		return "<table role='presentation' width='100%%' cellpadding='0' cellspacing='0' style='max-width:600px;margin:0 auto;'><tr><td style='padding:16px;font-family:Arial, sans-serif;'>$img_html<h3 style='margin:12px 0 8px 0;font-size:18px;line-height:1.3;color:#111;'>$title</h3><div style='font-size:14px;line-height:1.5;color:#333;'>$excerpt</div><p style='margin:16px 0 0;'><a href='$link' style='display:inline-block;background:#111;color:#fff;text-decoration:none;padding:10px 16px;border-radius:4px;'>$cta</a></p></td></tr></table>";
	}

	public function ajax_fetch_mailchimp_audiences() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing API key.' ), 400 );
		}
		$items = $this->providers['mailchimp']->get_audiences( $settings );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	public function ajax_fetch_mailchimp_templates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing API key.' ), 400 );
		}
		$items = $this->providers['mailchimp']->get_templates( $settings );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	public function ajax_verify_mailchimp() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		$prov     = isset( $settings['provider'] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $prov ) {
			wp_send_json_error( array( 'message' => 'Provider is not Mailchimp.' ), 400 );
		}
		// If an API key is provided in the request, prefer it for verification (unsaved form input)
		if ( isset( $_POST['api_key'] ) ) {
			$settings['api_key'] = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
		}
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing API key.' ), 400 );
		}
		$items = $this->providers['mailchimp']->get_audiences( $settings );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'ok' => true ) );
	}

	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_mailchimp-post-blast' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'campaignbridge-admin',
			plugins_url( 'dist/scripts/campaignbridge.js', __DIR__ ),
			array(),
			'1.0.0',
			true
		);

		wp_enqueue_style(
			'campaignbridge-admin',
			plugins_url( 'dist/styles/styles.css', __DIR__ ),
			array(),
			'1.0.0'
		);

		wp_localize_script(
			'campaignbridge-admin',
			'CampaignBridge',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'campaignbridge_ajax' ),
			)
		);
	}

	public function ajax_fetch_posts() {
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

		$settings       = get_option( $this->option_name );
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

	private function generate_and_send_campaign( $post_ids, $settings, $sections_map = array() ) {
		return CB_Dispatcher::generate_and_send_campaign( $post_ids, $settings, $sections_map, $this->providers );
	}

	private function send_to_mailchimp( $blocks, $settings ) {
		/* legacy stub, unused after provider refactor */ }
}
