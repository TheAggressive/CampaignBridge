<?php
/**
 * Email Template Block Render.
 *
 * @package CampaignBridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the email template block.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block content.
 * @return string Rendered HTML.
 */
function campaignbridge_render_email_template_block( $attributes, $content ) {
	// Extract attributes with defaults.
	$template_name    = $attributes['templateName'] ?? 'Email Template';
	$email_width      = $attributes['emailWidth'] ?? 600;
	$background_color = $attributes['backgroundColor'] ?? '#ffffff';
	$text_color       = $attributes['textColor'] ?? '#333333';
	$font_family      = $attributes['fontFamily'] ?? 'Arial, sans-serif';
	$max_width        = $attributes['maxWidth'] ?? 600;
	$padding          = $attributes['padding'] ?? array(
		'top'    => 20,
		'right'  => 20,
		'bottom' => 20,
		'left'   => 20,
	);

	// Build inline styles for email compatibility.
	$container_styles = sprintf(
		'width: %dpx; max-width: 100%%; margin: 0 auto; background-color: %s; color: %s; font-family: %s;',
		$email_width,
		esc_attr( $background_color ),
		esc_attr( $text_color ),
		esc_attr( $font_family )
	);

	$content_styles = sprintf(
		'padding: %dpx %dpx %dpx %dpx;',
		$padding['top'],
		$padding['right'],
		$padding['bottom'],
		$padding['left']
	);

	// Build the HTML structure.
	$html = sprintf(
		'<div class="cb-email-template" style="%s">',
		$container_styles
	);

	// Add email header if in admin.
	if ( is_admin() ) {
		$html .= sprintf(
			'<div class="cb-email-template-header" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 16px; text-align: center; font-size: 12px; color: #475569;">
				<strong>%s</strong> (%dpx × Responsive)
			</div>',
			esc_html( $template_name ),
			$email_width
		);
	}

	// Add content wrapper.
	$html .= sprintf(
		'<div class="cb-email-content-wrapper" style="%s">',
		$content_styles
	);

	// Add the block content.
	$html .= $content;

	// Close content wrapper.
	$html .= '</div>';

	// Add email footer if in admin.
	if ( is_admin() ) {
		$html .= sprintf(
			'<div class="cb-email-template-footer" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-top: 16px; text-align: center;">
				<div class="cb-email-template-actions" style="display: flex; gap: 8px; justify-content: center;">
					<button type="button" class="button button-primary cb-export-html" style="background: #3b82f6; border-color: #3b82f6; color: #ffffff; padding: 6px 16px; border-radius: 4px; font-size: 13px; cursor: pointer;">
						%s
					</button>
					<button type="button" class="button button-secondary cb-preview-email" style="background: #ffffff; border-color: #d1d5db; color: #374151; padding: 6px 16px; border-radius: 4px; font-size: 13px; cursor: pointer;">
						%s
					</button>
				</div>
			</div>',
			esc_html__( 'Export HTML', 'campaignbridge' ),
			esc_html__( 'Preview Email', 'campaignbridge' )
		);
	}

	// Close main container.
	$html .= '</div>';

	return $html;
}

// Register the render callback.
register_block_type(
	'campaignbridge/email-template',
	array(
		'render_callback' => 'campaignbridge_render_email_template_block',
	)
);
