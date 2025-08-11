<?php
namespace CampaignBridge\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * HTML provider: outputs the generated email as a downloadable HTML file.
 */
class HtmlProvider implements ProviderInterface {
	public function slug() {
		return 'html'; }
	public function label() {
		return __( 'HTML Export (download)', 'campaignbridge' ); }

	public function is_configured( $settings ) {
		return true; }
	public function render_settings_fields( $settings, $option_name ) {
		/* no specific fields */ }

	public function get_section_keys( $settings ) {
		return array();
	}

	public function send_campaign( $blocks, $settings ) {
		// Combine blocks into a simple HTML export for download.
		$html  = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width'><title>Email Export</title></head><body>";
		$html .= '<div class="campaignbridge-export">';
		foreach ( $blocks as $key => $block_html ) {
			$html .= '<div class="campaignbridge-section" style="margin-bottom:24px;">' . $block_html . '</div>';
		}
		$html .= '</div></body></html>';

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="campaignbridge-export.html"' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
