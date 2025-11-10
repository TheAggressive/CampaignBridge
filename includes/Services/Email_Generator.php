<?php
/**
 * Email Generation Service for CampaignBridge.
 *
 * Converts WordPress blocks to email-safe HTML with CSS inlining
 * and responsive design for professional email campaigns.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Services;

use CampaignBridge\Services\Email\BlockProcessor;
use CampaignBridge\Services\Email\CssProcessor;
use CampaignBridge\Services\Email\EmailStructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Generator Service
 *
 * Main orchestrator for email HTML generation, coordinating
 * block processing, CSS handling, and email structure building.
 */
class Email_Generator {
	/**
	 * Default email generation options
	 */
	private const DEFAULT_OPTIONS = array(
		'email_width'      => 600,
		'max_width'        => 600,
		'background_color' => '#ffffff',
		'text_color'       => '#333333',
		'font_family'      => 'Arial, sans-serif',
		'css_inline'       => true,
		'responsive'       => true,
		'email_client'     => 'universal',
	);


	/**
	 * Convert blocks to email-safe HTML.
	 *
	 * @param array<string, mixed> $blocks Array of block data.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Email-safe HTML.
	 */
	public static function generate_email_html( array $blocks, array $options = array() ): string {
		$options = wp_parse_args( $options, self::DEFAULT_OPTIONS );

		// Use specialized classes for email generation logic.
		$email_structure = new EmailStructure();
		$block_processor = new BlockProcessor();
		$css_processor   = new CssProcessor();

		// Check if first block is a container and extract its background for global email background.
		$container_bg = $block_processor->extract_container_background( $blocks );

		// Start building the email HTML with container background if available.
		$header_options = $container_bg ? array_merge( $options, array( 'background_color' => $container_bg ) ) : $options;
		$html           = $email_structure->build_header( $header_options );
		$html          .= $block_processor->convert_blocks_to_html( $blocks, $options );
		$html          .= $email_structure->build_footer( $options );

		// Process the HTML for email compatibility.
		if ( $options['css_inline'] ) {
			$html = $css_processor->inline_css( $html );
		}

		if ( $options['responsive'] ) {
			$html = $css_processor->make_responsive( $html, $options );
		}

		return $html;
	}
}
