<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface CampaignBridge_Provider_Interface {
	public function slug();
	public function label();
	public function is_configured( $settings );
	public function render_settings_fields( $settings, $option_name );
	public function send_campaign( $blocks, $settings );
	public function get_section_keys( $settings );
}
