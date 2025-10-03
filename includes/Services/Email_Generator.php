<?php // phpcs:ignoreFile WordPress.Files.FileName
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Generator Service
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
	 * Block namespace prefixes
	 */
	private const CAMPAIGNBRIDGE_BLOCK_PREFIX = 'campaignbridge/';
	private const CORE_BLOCK_PREFIX           = 'core/';

	/**
	 * Extract container background color from first block if it's a container.
	 *
	 * @param array $blocks Array of block data.
	 * @return string|null Container background color or null if not applicable.
	 */
	private static function extract_container_background( array $blocks ): ?string {
		if ( empty( $blocks ) ) {
			return null;
		}

		$first_block = $blocks[0];
		$block_name  = $first_block['blockName'] ?? '';

		// Check if first block is a container
		if ( $block_name !== 'campaignbridge/container' ) {
			return null;
		}

		$attributes  = $first_block['attrs'] ?? array();
		$style       = $attributes['style'] ?? array();
		$color_style = $style['color'] ?? array();
		$background  = $color_style['background'] ?? null;

		return $background ?: null;
	}

	/**
	 * Convert blocks to email-safe HTML.
	 *
	 * @param array $blocks Array of block data.
	 * @param array $options Generation options.
	 * @return string Email-safe HTML.
	 */
	public static function generate_email_html( array $blocks, array $options = array() ): string {
		$options = wp_parse_args( $options, self::DEFAULT_OPTIONS );

		// Check if first block is a container and extract its background for global email background
		$container_bg = self::extract_container_background( $blocks );

		// Start building the email HTML with container background if available.
		$header_options = $container_bg ? array_merge( $options, array( 'background_color' => $container_bg ) ) : $options;
		$html           = self::build_email_header( $header_options );
		$html          .= self::convert_blocks_to_html( $blocks, $options );
		$html          .= self::build_email_footer( $options );

		// Process the HTML for email compatibility.
		if ( $options['css_inline'] ) {
			$html = self::inline_css( $html );
		}

		if ( $options['responsive'] ) {
			$html = self::make_responsive( $html, $options );
		}

		return $html;
	}

	/**
	 * Build email header with proper DOCTYPE and meta tags.
	 *
	 * @param array $options Generation options.
	 * @return string Email header HTML.
	 */
	private static function build_email_header( array $options ): string {
		$width       = $options['email_width'];
		$max_width   = $options['max_width'];
		$bg_color    = $options['background_color'];
		$text_color  = $options['text_color'];
		$font_family = $options['font_family'];

		return sprintf(
			'<!DOCTYPE html>
<html lang="%s">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>%s</title>
	<!--[if mso]>
	<noscript>
		<xml>
			<o:OfficeDocumentSettings>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	</noscript>
	<![endif]-->
	<style type="text/css">
		/* Reset styles for email clients */
		body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%%; -ms-text-size-adjust: 100%%; }
		table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

		/* Responsive design */
		@media only screen and (max-width: %dpx) {
			.email-container { width: 100%% !important; max-width: 100%% !important; }
			.email-content { padding: 16px !important; }
			.mobile-stack { display: block !important; width: 100%% !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: %s; font-family: %s; color: %s;">
	<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background-color: %s;">
		<tr>
			<td align="center" style="padding: 20px 0;">
				<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="%d" class="email-container" style="max-width: %dpx; margin: 0 auto; background-color: %s; border-radius: 8px; overflow: hidden;">
					<tr>
						<td class="email-content" style="padding: 20px;">',
			esc_attr( get_locale() ),
			esc_html( get_bloginfo( 'name' ) ),
			$width,
			esc_attr( $bg_color ),
			esc_attr( $font_family ),
			esc_attr( $text_color ),
			esc_attr( $bg_color ),
			$width,
			$max_width,
			esc_attr( $bg_color )
		);
	}

	/**
	 * Convert WordPress blocks to HTML.
	 *
	 * @param array $blocks Array of block data.
	 * @param array $options Generation options.
	 * @return string Converted HTML.
	 */
	private static function convert_blocks_to_html( array $blocks, array $options ): string {
		$html = '';

		foreach ( $blocks as $block ) {
			$html .= self::convert_block_to_html( $block, $options );
		}

		return $html;
	}

	/**
	 * Convert a single block to HTML.
	 *
	 * @param array $block Block data.
	 * @param array $options Generation options.
	 * @return string Converted HTML.
	 */
	private static function convert_block_to_html( array $block, array $options ): string {
		$block_name    = $block['blockName'] ?? '';
		$attributes    = $block['attrs'] ?? array();
		$inner_content = $block['innerContent'] ?? array();
		$inner_blocks  = $block['innerBlocks'] ?? array();

		// Handle CampaignBridge blocks.
		if ( strpos( $block_name, self::CAMPAIGNBRIDGE_BLOCK_PREFIX ) === 0 ) {
			return self::convert_campaignbridge_block( $block_name, $attributes, $inner_content, $inner_blocks, $options );
		}

		// Handle core WordPress blocks.
		if ( strpos( $block_name, self::CORE_BLOCK_PREFIX ) === 0 ) {
			$core_block_type = str_replace( self::CORE_BLOCK_PREFIX, '', $block_name );
			return self::convert_core_block( $core_block_type, $attributes, $inner_content, $inner_blocks, $options );
		}

		// Fallback for unknown blocks.
		return self::convert_unknown_block( $block, $options );
	}

	/**
	 * Convert core WordPress blocks to HTML.
	 *
	 * @param string $block_type Block type (without namespace).
	 * @param array  $attributes Block attributes.
	 * @param array  $inner_content Inner content.
	 * @param array  $inner_blocks Inner blocks.
	 * @param array  $options Generation options.
	 * @return string Converted HTML.
	 */
	private static function convert_core_block( string $block_type, array $attributes, array $inner_content, array $inner_blocks, array $options ): string {
		switch ( $block_type ) {
			case 'paragraph':
				return self::convert_paragraph_block( $attributes, $inner_content );

			case 'heading':
				return self::convert_heading_block( $attributes, $inner_content );

			case 'image':
				return self::convert_image_block( $attributes );

			case 'buttons':
				return self::convert_buttons_block( $attributes, $inner_blocks );

			case 'columns':
				return self::convert_columns_block( $attributes, $inner_blocks, $options );

			case 'group':
				return self::convert_group_block( $attributes, $inner_blocks );

			case 'spacer':
				return self::convert_spacer_block( $attributes );

			case 'separator':
				return self::convert_separator_block( $attributes );

			default:
				// Fallback for unknown core blocks.
				return self::convert_unknown_block(
					array(
						'blockName'    => 'core/' . $block_type,
						'attrs'        => $attributes,
						'innerContent' => $inner_content,
						'innerBlocks'  => $inner_blocks,
					),
					$options
				);
		}
	}

	/**
	 * Convert CampaignBridge blocks to HTML.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attributes Block attributes.
	 * @param array  $inner_content Inner content.
	 * @param array  $inner_blocks Inner blocks.
	 * @param array  $options Generation options.
	 * @return string Converted HTML.
	 */
	private static function convert_campaignbridge_block( string $block_name, array $attributes, array $inner_content, array $inner_blocks, array $options ): string {
		switch ( $block_name ) {
			case 'campaignbridge/container':
				return self::convert_container_block( $attributes, $inner_content, $inner_blocks );

			case 'campaignbridge/post-card':
				return self::convert_post_card_block( $attributes );

			case 'campaignbridge/post-title':
				return self::convert_post_title_block( $attributes );

			case 'campaignbridge/post-excerpt':
				return self::convert_post_excerpt_block( $attributes, $options );

			case 'campaignbridge/post-image':
				return self::convert_post_image_block( $attributes, $options );

			case 'campaignbridge/post-cta':
				return self::convert_post_button_block( $attributes );

			default:
				return '';
		}
	}

	/**
	 * Convert container block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $inner_content Inner content.
	 * @param array $inner_blocks Inner blocks.
	 * @return string Converted HTML.
	 */
	private static function convert_container_block( array $attributes, array $inner_content, array $inner_blocks ): string {
		$max_width      = $attributes['maxWidth'] ?? 600;
		$background_hex = '#ffffff';
		$text_hex       = '#000000';

		// Extract colors from attributes
		$style       = $attributes['style'] ?? array();
		$color_style = $style['color'] ?? array();
		if ( isset( $color_style['background'] ) ) {
			$background_hex = $color_style['background'];
		}
		if ( isset( $color_style['text'] ) ) {
			$text_hex = $color_style['text'];
		}

		// Get padding from attributes
		$padding = $attributes['padding'] ?? array(
			'top'    => 0,
			'right'  => 24,
			'bottom' => 0,
			'left'   => 24,
		);

		$inner_td_style = sprintf(
			'padding: %dpx %dpx %dpx %dpx; mso-line-height-rule: exactly; color: %s;',
			$padding['top'] ?? 0,
			$padding['right'] ?? 24,
			$padding['bottom'] ?? 0,
			$padding['left'] ?? 24,
			esc_attr( $text_hex )
		);

		// Generate the HTML structure for the global container
		$content = self::convert_blocks_to_html( $inner_blocks, array() );

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;">
				<tbody>
				<tr>
					<td align="center">
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="%d" style="max-width: 100%%; background: %s; color: %s; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse;">
							<tbody>
							<tr>
								<td align="left" style="%s">
									%s
								</td>
							</tr>
							</tbody>
						</table>
					</td>
				</tr>
				</tbody>
			</table>',
			(int) $max_width,
			esc_attr( $background_hex ),
			esc_attr( $text_hex ),
			$inner_td_style,
			$content
		);
	}

	/**
	 * Convert paragraph block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $inner_content Inner content.
	 * @return string Converted HTML.
	 */
	private static function convert_paragraph_block( array $attributes, array $inner_content ): string {
		$content     = implode( '', $inner_content );
		$align       = $attributes['align'] ?? 'left';
		$font_size   = $attributes['fontSize'] ?? '14px';
		$line_height = $attributes['lineHeight'] ?? '1.6';

		$style = sprintf(
			'margin: 0 0 16px 0; font-size: %s; line-height: %s; text-align: %s;',
			esc_attr( $font_size ),
			esc_attr( $line_height ),
			esc_attr( $align )
		);

		return sprintf( '<p style="%s">%s</p>', $style, $content );
	}

	/**
	 * Convert heading block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $inner_content Inner content.
	 * @return string Converted HTML.
	 */
	private static function convert_heading_block( array $attributes, array $inner_content ): string {
		$content   = implode( '', $inner_content );
		$level     = $attributes['level'] ?? 2;
		$align     = $attributes['align'] ?? 'left';
		$font_size = $attributes['fontSize'] ?? ( 1 === $level ? '24px' : '20px' );

		$style = sprintf(
			'margin: 0 0 16px 0; font-size: %s; font-weight: 600; line-height: 1.3; text-align: %s;',
			esc_attr( $font_size ),
			esc_attr( $align )
		);

		$tag = 'h' . $level;
		return sprintf( '<%1$s style="%2$s">%3$s</%1$s>', $tag, $style, $content );
	}

	/**
	 * Convert image block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_image_block( array $attributes ): string {
		$url    = $attributes['url'] ?? '';
		$alt    = $attributes['alt'] ?? '';
		$width  = $attributes['width'] ?? '';
		$height = $attributes['height'] ?? '';
		$align  = $attributes['align'] ?? 'center';

		if ( empty( $url ) ) {
			return '';
		}

		$style = sprintf(
			'display: block; max-width: 100%%; height: auto; margin: 16px auto; text-align: %s;',
			esc_attr( $align )
		);

		if ( $width ) {
			$style .= sprintf( ' width: %spx;', esc_attr( $width ) );
		}

		if ( $height ) {
			$style .= sprintf( ' height: %spx;', esc_attr( $height ) );
		}

		return sprintf(
			'<img src="%s" alt="%s" style="%s" />',
			esc_url( $url ),
			esc_attr( $alt ),
			$style
		);
	}

	/**
	 * Convert buttons block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $inner_blocks Inner blocks.
	 * @return string Converted HTML.
	 */
	private static function convert_buttons_block( array $attributes, array $inner_blocks ): string {
		$align = $attributes['align'] ?? 'left';
		$style = sprintf( 'margin: 20px 0; text-align: %s;', esc_attr( $align ) );

		$html = sprintf( '<div style="%s">', $style );

		foreach ( $inner_blocks as $button_block ) {
			if ( 'core/button' === $button_block['blockName'] ) {
				$html .= self::convert_button_block( $button_block['attrs'] );
			}
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Convert button block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_button_block( array $attributes ): string {
		$text             = $attributes['text'] ?? '';
		$url              = $attributes['url'] ?? '#';
		$background_color = $attributes['backgroundColor'] ?? '#3b82f6';
		$text_color       = $attributes['textColor'] ?? '#ffffff';
		$width            = $attributes['width'] ?? 'auto';

		$style = sprintf(
			'display: inline-block; padding: 12px 24px; background-color: %s; color: %s !important; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 14px; line-height: 1.4; text-align: center; min-width: 120px;',
			esc_attr( $background_color ),
			esc_attr( $text_color )
		);

		if ( 'auto' !== $width ) {
			$style .= sprintf( ' width: %s;', esc_attr( $width ) );
		}

		return sprintf(
			'<a href="%s" style="%s">%s</a>',
			esc_url( $url ),
			$style,
			esc_html( $text )
		);
	}

	/**
	 * Convert columns block to HTML.
	 *
	 * @param array $inner_blocks Inner blocks.
	 * @param array $options Generation options.
	 * @return string Converted HTML.
	 */
	private static function convert_columns_block( array $inner_blocks, array $options ): string {
		$columns = count( $inner_blocks );
		if ( 0 === $columns ) {
			return '';
		}

		// Use table layout for email compatibility.
		$html  = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">';
		$html .= '<tr>';

		$column_width = 100 / $columns;

		foreach ( $inner_blocks as $column_block ) {
			if ( 'core/column' === $column_block['blockName'] ) {
				$html .= sprintf(
					'<td class="mobile-stack" style="width: %1$s%%; vertical-align: top; padding: 0 10px;">',
					$column_width
				);
				$html .= self::convert_blocks_to_html( $column_block['innerBlocks'] ?? array(), $options );
				$html .= '</td>';
			}
		}

		$html .= '</tr></table>';
		return $html;
	}

	/**
	 * Convert group block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $inner_blocks Inner blocks.
	 * @return string Converted HTML.
	 */
	private static function convert_group_block( array $attributes, array $inner_blocks ): string {
		$background_color = $attributes['backgroundColor'] ?? '';
		$text_color       = $attributes['textColor'] ?? '';
		$padding          = $attributes['padding'] ?? array();

		$style = 'margin: 20px 0; padding: 20px;';

		if ( $background_color ) {
			$style .= sprintf( ' background-color: %s;', esc_attr( $background_color ) );
		}

		if ( $text_color ) {
			$style .= sprintf( ' color: %s;', esc_attr( $text_color ) );
		}

		if ( ! empty( $padding ) ) {
			$style .= sprintf(
				' padding: %dpx %dpx %dpx %dpx;',
				$padding['top'] ?? 20,
				$padding['right'] ?? 20,
				$padding['bottom'] ?? 20,
				$padding['left'] ?? 20
			);
		}

		$html  = sprintf( '<div style="%s">', $style );
		$html .= self::convert_blocks_to_html( $inner_blocks, array() );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Convert spacer block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_spacer_block( array $attributes ): string {
		$height = $attributes['height'] ?? 20;
		return sprintf( '<div style="height: %dpx; line-height: %dpx; font-size: 0;">&nbsp;</div>', $height, $height );
	}

	/**
	 * Convert separator block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_separator_block( array $attributes ): string {
		$color = $attributes['color'] ?? '#e5e7eb';
		$style = sprintf( 'border: 0; height: 1px; background-color: %s; margin: 20px 0;', esc_attr( $color ) );
		return sprintf( '<hr style="%s" />', $style );
	}

	/**
	 * Convert email template block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $inner_blocks Inner blocks.
	 * @param array $options Generation options.
	 * @return string Converted HTML.
	 */

	/**
	 * Convert post card block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_post_card_block( array $attributes ): string {
		$post_id      = $attributes['postId'] ?? 0;
		$display_mode = $attributes['displayMode'] ?? 'title_excerpt';

		if ( ! $post_id ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'No post selected', 'campaignbridge' ) . '</p>';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'Post not found', 'campaignbridge' ) . '</p>';
		}

		$html = '<div class="cb-post-slot" style="margin: 20px 0; padding: 20px; border: 1px solid #e5e7eb; border-radius: 4px;">';

		switch ( $display_mode ) {
			case 'title_only':
				$html .= sprintf(
					'<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600;">%s</h3>',
					esc_html( $post->post_title )
				);
				break;

			case 'title_excerpt':
				$html .= sprintf(
					'<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600;">%s</h3>',
					esc_html( $post->post_title )
				);
				$html .= sprintf(
					'<p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6;">%s</p>',
					esc_html( wp_trim_words( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 30 ) )
				);
				break;

			case 'title_excerpt_image':
				if ( has_post_thumbnail( $post_id ) ) {
					$thumbnail_id  = get_post_thumbnail_id( $post_id );
					$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'medium' );
					if ( $thumbnail_url ) {
						$html .= sprintf(
							'<img src="%s" alt="%s" style="display: block; max-width: 100%%; height: auto; margin: 0 0 16px 0; border-radius: 4px;" />',
							esc_url( $thumbnail_url ),
							esc_attr( $post->post_title )
						);
					}
				}
				$html .= sprintf(
					'<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600;">%s</h3>',
					esc_html( $post->post_title )
				);
				$html .= sprintf(
					'<p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6;">%s</p>',
					esc_html( wp_trim_words( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 30 ) )
				);
				break;

			case 'full_content':
				$html .= sprintf(
					'<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600;">%s</h3>',
					esc_html( $post->post_title )
				);
				$html .= sprintf(
					'<div style="font-size: 14px; line-height: 1.6;">%s</div>',
					wp_kses_post( apply_filters( 'the_content', $post->post_content ) )
				);
				break;
		}

		if ( $attributes['showReadMore'] ?? true ) {
			$html .= sprintf(
				'<a href="%s" style="display: inline-block; padding: 8px 16px; background-color: #3b82f6; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 500;">%s</a>',
				esc_url( get_permalink( $post_id ) ),
				esc_html__( 'Read More', 'campaignbridge' )
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Convert post title block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_post_title_block( array $attributes ): string {
		$post_id   = $attributes['postId'] ?? 0;
		$level     = $attributes['level'] ?? 2;
		$align     = $attributes['align'] ?? 'left';
		$font_size = $attributes['fontSize'] ?? ( 2 === $level ? '24px' : '20px' );

		if ( ! $post_id ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'No post selected', 'campaignbridge' ) . '</p>';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'Post not found', 'campaignbridge' ) . '</p>';
		}

		$style = sprintf(
			'margin: 0 0 16px 0; font-size: %s; font-weight: 600; line-height: 1.3; text-align: %s;',
			esc_attr( $font_size ),
			esc_attr( $align )
		);

		$tag = 'h' . $level;
		return sprintf( '<%1$s style="%2$s">%3$s</%1$s>', $tag, $style, esc_html( $post->post_title ) );
	}

	/**
	 * Convert post excerpt block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_post_excerpt_block( array $attributes ): string {
		$post_id     = $attributes['postId'] ?? 0;
		$align       = $attributes['align'] ?? 'left';
		$font_size   = $attributes['fontSize'] ?? '14px';
		$line_height = $attributes['lineHeight'] ?? '1.6';
		$word_count  = $attributes['wordCount'] ?? 30;

		if ( ! $post_id ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'No post selected', 'campaignbridge' ) . '</p>';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'Post not found', 'campaignbridge' ) . '</p>';
		}

		$excerpt = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$excerpt = wp_trim_words( $excerpt, $word_count );

		$style = sprintf(
			'margin: 0 0 16px 0; font-size: %s; line-height: %s; text-align: %s;',
			esc_attr( $font_size ),
			esc_attr( $line_height ),
			esc_attr( $align )
		);

		return sprintf( '<p style="%s">%s</p>', $style, esc_html( $excerpt ) );
	}

	/**
	 * Convert post image block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_post_image_block( array $attributes ): string {
		$post_id = $attributes['postId'] ?? 0;
		$size    = $attributes['size'] ?? 'medium';
		$align   = $attributes['align'] ?? 'center';
		$width   = $attributes['width'] ?? '';
		$height  = $attributes['height'] ?? '';

		if ( ! $post_id ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'No post selected', 'campaignbridge' ) . '</p>';
		}

		if ( ! has_post_thumbnail( $post_id ) ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'No featured image', 'campaignbridge' ) . '</p>';
		}

		$thumbnail_id  = get_post_thumbnail_id( $post_id );
		$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, $size );

		if ( ! $thumbnail_url ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'Image not found', 'campaignbridge' ) . '</p>';
		}

		$post = get_post( $post_id );
		$alt  = $post ? $post->post_title : '';

		$style = sprintf(
			'display: block; max-width: 100%%; height: auto; margin: 16px auto; text-align: %s;',
			esc_attr( $align )
		);

		if ( $width ) {
			$style .= sprintf( ' width: %spx;', esc_attr( $width ) );
		}

		if ( $height ) {
			$style .= sprintf( ' height: %spx;', esc_attr( $height ) );
		}

		return sprintf(
			'<img src="%s" alt="%s" style="%s" />',
			esc_url( $thumbnail_url ),
			esc_attr( $alt ),
			$style
		);
	}

	/**
	 * Convert post button block to HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Converted HTML.
	 */
	private static function convert_post_button_block( array $attributes ): string {
		$post_id          = $attributes['postId'] ?? 0;
		$text             = $attributes['text'] ?? __( 'Read More', 'campaignbridge' );
		$align            = $attributes['align'] ?? 'left';
		$background_color = $attributes['backgroundColor'] ?? '#3b82f6';
		$text_color       = $attributes['textColor'] ?? '#ffffff';
		$width            = $attributes['width'] ?? 'auto';

		if ( ! $post_id ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'No post selected', 'campaignbridge' ) . '</p>';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">' . esc_html__( 'Post not found', 'campaignbridge' ) . '</p>';
		}

		$style = sprintf(
			'display: inline-block; padding: 12px 24px; background-color: %s; color: %s !important; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 14px; line-height: 1.4; text-align: center; min-width: 120px;',
			esc_attr( $background_color ),
			esc_attr( $text_color )
		);

		if ( 'auto' !== $width ) {
			$style .= sprintf( ' width: %s;', esc_attr( $width ) );
		}

		$container_style = sprintf( 'margin: 20px 0; text-align: %s;', esc_attr( $align ) );

		return sprintf(
			'<div style="%s"><a href="%s" style="%s">%s</a></div>',
			$container_style,
			esc_url( get_permalink( $post_id ) ),
			$style,
			esc_html( $text )
		);
	}

	/**
	 * Convert unknown block to HTML.
	 *
	 * @param array $block Block data.
	 * @param array $options Generation options.
	 * @return string Converted HTML.
	 */
	private static function convert_unknown_block( array $block, array $options ): string {
		// For unknown blocks, try to extract content and render as basic HTML.
		$content = '';

		if ( ! empty( $block['innerContent'] ) ) {
			$content = implode( '', $block['innerContent'] );
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$content .= self::convert_blocks_to_html( $block['innerBlocks'], $options );
		}

		if ( empty( $content ) ) {
			return '';
		}

		return sprintf( '<div style="margin: 16px 0;">%s</div>', $content );
	}

	/**
	 * Build email footer.
	 *
	 * @return string Email footer HTML.
	 */
	private static function build_email_footer(): string {
		return '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
	}

	/**
	 * Inline CSS for email compatibility.
	 *
	 * @param string $html HTML content.
	 * @return string HTML with inlined CSS.
	 */
	private static function inline_css( string $html ): string {
		// This is a simplified version. In production, you'd want to use a proper CSS inliner.
		// For now, we'll just remove any <style> tags and rely on inline styles.
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );
		return $html;
	}

	/**
	 * Make HTML responsive for mobile devices.
	 *
	 * @param string $html HTML content.
	 * @return string Responsive HTML.
	 */
	private static function make_responsive( string $html ): string {
		// Add responsive meta tag and CSS if not already present.
		if ( strpos( $html, 'width=device-width' ) === false ) {
			$html = str_replace(
				'<head>',
				'<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">',
				$html
			);
		}

		return $html;
	}
}
