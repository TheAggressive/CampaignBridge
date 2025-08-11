<?php
/**
 * CampaignBridge HTML provider.
 *
 * Outputs the generated email as a downloadable HTML file.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * HTML provider: outputs the generated email as a downloadable HTML file.
 */
class HtmlProvider implements ProviderInterface {
	/**
	 * Unique slug for this provider.
	 *
	 * @return string
	 */
	public function slug() {
		return 'html';
	}

	/**
	 * Human-readable label for this provider.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'HTML Export (download)', 'campaignbridge' );
	}

	/**
	 * Whether the provider has sufficient settings to operate.
	 *
	 * @param array $settings Plugin settings array.
	 * @return bool
	 */
	public function is_configured( $settings ) {
		return true;
	}

	/**
	 * Render provider-specific settings fields.
	 *
	 * @param array  $settings    Plugin settings array.
	 * @param string $option_name Root option name used for field names.
	 * @return void
	 */
	public function render_settings_fields( $settings, $option_name ) {
		/* no specific fields */
	}

	/**
	 * Return a list of template section keys supported by this provider.
	 *
	 * @param array $settings Plugin settings array.
	 * @return array
	 */
	public function get_section_keys( $settings ) {
		return array();
	}

	/**
	 * Output the generated email as a downloadable HTML file.
	 *
	 * @param array $blocks   Associative array of section_key => HTML string.
	 * @param array $settings Plugin settings array.
	 * @return void
	 */
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
