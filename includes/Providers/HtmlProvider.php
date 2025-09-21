<?php
/**
 * HTML Export Provider for CampaignBridge.
 *
 * This class implements the CampaignBridge provider interface to provide
 * HTML export functionality for email campaigns. It generates downloadable
 * HTML files that can be used for manual email sending, testing, or
 * integration with other email systems.
 *
 * This provider is ideal for users who prefer manual control over
 * email delivery or need to integrate with custom email systems.
 *
 * @package CampaignBridge
 * @since 0.1.0
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
	public function slug(): string {
		return 'html';
	}

	/**
	 * Human-readable label for this provider.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'HTML Export (download)', 'campaignbridge' );
	}

	/**
	 * Whether the provider has sufficient settings to operate.
	 *
	 * @param array $settings Plugin settings array.
	 * @return bool
	 */
	public function is_configured( array $settings ): bool {
		return true;
	}

	/**
	 * Render provider-specific settings fields.
	 *
	 * @param array  $settings    Plugin settings array.
	 * @param string $option_name Root option name used for field names.
	 * @return void
	 */
	public function render_settings_fields( array $settings, string $option_name ): void {
		/* no specific fields */
	}

	/**
	 * Return a list of template section keys supported by this provider.
	 *
	 * @param array $settings Plugin settings array.
	 * @return array
	 */
	public function get_section_keys( array $settings ) {
		return array();
	}

	/**
	 * Output the generated email as a downloadable HTML file.
	 *
	 * @param array $blocks   Associative array of section_key => HTML string.
	 * @param array $settings Plugin settings array.
	 * @return void
	 */
	public function send_campaign( array $blocks, array $settings ) {
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
