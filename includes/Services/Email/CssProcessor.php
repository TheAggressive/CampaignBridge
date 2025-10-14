<?php
/**
 * CSS Processor for CampaignBridge Email Generation.
 *
 * Handles CSS inlining and responsive design processing for email compatibility.
 * Ensures email-safe CSS that works across different email clients.
 *
 * @package CampaignBridge\Services\Email
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSS Processor
 *
 * Processes CSS for email compatibility including inlining and responsive design.
 */
class CssProcessor {
	/**
	 * Inline CSS styles for email compatibility.
	 *
	 * @param string $html HTML content with CSS.
	 * @return string HTML with inline CSS.
	 */
	public function inline_css( string $html ): string {
		// Simple CSS inlining - move styles from <style> tags to inline attributes
		// For a production system, consider using a dedicated CSS inliner library.

		// Remove <style> tags and extract CSS.
		preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $html, $style_matches );
		$css_rules = '';

		foreach ( $style_matches[1] as $css ) {
			$css_rules .= $css;
		}

		// Remove style tags from HTML.
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );

		// Basic CSS inlining for common email styles.
		$inline_styles = array(
			'color'            => '/color:\s*([^;]+);/i',
			'font-size'        => '/font-size:\s*([^;]+);/i',
			'font-family'      => '/font-family:\s*([^;]+);/i',
			'font-weight'      => '/font-weight:\s*([^;]+);/i',
			'text-align'       => '/text-align:\s*([^;]+);/i',
			'padding'          => '/padding:\s*([^;]+);/i',
			'margin'           => '/margin:\s*([^;]+);/i',
			'background-color' => '/background-color:\s*([^;]+);/i',
			'border'           => '/border:\s*([^;]+);/i',
			'width'            => '/width:\s*([^;]+);/i',
			'height'           => '/height:\s*([^;]+);/i',
		);

		// Apply inline styles to common elements.
		foreach ( $inline_styles as $property => $pattern ) {
			if ( preg_match_all( $pattern, $css_rules, $matches ) ) {
				foreach ( $matches[1] as $value ) {
					// This is a simplified approach - a full CSS inliner would be more complex.
					$inline_attr    = sprintf( '%s: %s;', $property, trim( $value ) );
					$search_pattern = '/<([a-zA-Z][^>]*)>/';
					$replace_string = '<$1 style="' . esc_attr( $inline_attr ) . '">';
					$replacement    = preg_replace(
						$search_pattern,
						$replace_string,
						(string) $html,
						1 // Only replace the first occurrence per element type.
					);
					$html           = is_string( $replacement ) ? $replacement : $html;
				}
			}
		}

		return $html ? $html : '';
	}

	/**
	 * Make HTML responsive for mobile devices.
	 *
	 * @param string               $html HTML content.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Responsive HTML.
	 */
	public function make_responsive( string $html, array $options ): string {
		$width = $options['email_width'] ?? 600;

		// Add responsive meta tag and CSS if not already present.
		if ( strpos( $html, 'width=device-width' ) === false ) {
			$html = str_replace(
				'<head>',
				'<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">',
				$html
			);
		}

		// Add responsive CSS for email containers.
		if ( strpos( $html, '@media only screen and (max-width:' ) === false ) {
			$responsive_css = sprintf(
				'
	<style type="text/css">
		@media only screen and (max-width: %dpx) {
			.email-container { width: 100%% !important; max-width: 100%% !important; }
			.email-content { padding: 16px !important; }
			.mobile-stack { display: block !important; width: 100%% !important; }
			.mobile-hide { display: none !important; }
		}
	</style>',
				$width
			);

			// Insert responsive CSS before closing head tag.
			$html = str_replace( '</head>', $responsive_css . '</head>', $html );
		}

		return $html;
	}
}
