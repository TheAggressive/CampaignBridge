<?php
// Legacy class kept temporarily for backward-compat bootstrap; new code uses \CampaignBridge\Plugin.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CampaignBridge {
	private $option_name = 'campaignbridge_settings';
	private $providers   = array();

	public function __construct() {
		// Load modules first
		require_once __DIR__ . '/providers/interface-campaignbridge-provider.php';
		require_once __DIR__ . '/providers/class-campaignbridge-provider-mailchimp.php';
		require_once __DIR__ . '/providers/class-campaignbridge-provider-html.php';
		require_once __DIR__ . '/class-cb-template-cpt.php';
		require_once __DIR__ . '/class-cb-blocks.php';
		require_once __DIR__ . '/class-cb-ajax.php';
		require_once __DIR__ . '/class-cb-render.php';
		require_once __DIR__ . '/class-cb-dispatcher.php';
		require_once __DIR__ . '/class-cb-admin-ui.php';

		// Build providers map
		$this->providers = array(
			'mailchimp' => new \CampaignBridge\Providers\MailchimpProvider(),
			'html'      => new \CampaignBridge\Providers\HtmlProvider(),
		);

		// Wire hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'init', array( '\\CampaignBridge\\CPT\\TemplateCPT', 'register' ) );
		add_action( 'init', array( '\\CampaignBridge\\Blocks\\Blocks', 'register_blocks' ) );
		add_action( 'admin_enqueue_scripts', array( 'CB_Admin_UI', 'enqueue_admin_assets' ) );

		// Centralize AJAX wiring in CB_Ajax
		\CampaignBridge\AJAX\Ajax::init( $this->option_name, $this->providers );
		\CampaignBridge\AJAX\Ajax::register();
	}

	public function add_admin_menu() {
		\CampaignBridge\Admin\UI::init( $this->option_name, $this->providers );
		add_menu_page( 'CampaignBridge', 'CampaignBridge', 'manage_options', 'mailchimp-post-blast', array( '\\CampaignBridge\\Admin\\UI', 'render_page' ) );
	}

	// Legacy: CPT handled by CB_Template_CPT; Blocks handled by CB_Blocks

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

	// Asset loading handled by CB_Admin_UI; AJAX handled by CB_Ajax; Dispatching by CB_Dispatcher
}
