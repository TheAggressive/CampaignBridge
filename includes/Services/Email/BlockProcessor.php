<?php
/**
 * Block Processor for CampaignBridge Email Generation.
 *
 * Handles conversion of WordPress blocks to email-safe HTML.
 * Processes both core WordPress blocks and custom CampaignBridge blocks.
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
 * Block Processor
 *
 * Converts WordPress blocks to email-safe HTML with proper structure and styling.
 */
class BlockProcessor {
	/**
	 * Block namespace prefixes
	 */
	private const CAMPAIGNBRIDGE_BLOCK_PREFIX = 'campaignbridge/';
	private const CORE_BLOCK_PREFIX           = 'core/';

	/**
	 * Extract container background color from first block if it's a container.
	 *
	 * @param array<array<string, mixed>> $blocks Array of block data.
	 * @return string|null Container background color or null if not applicable.
	 */
	public function extract_container_background( array $blocks ): ?string {
		if ( empty( $blocks ) || ! array_key_exists( 0, $blocks ) ) {
			return null;
		}

		$first_block = $blocks[0];
		$block_name  = $first_block['blockName'] ?? '';

		// Check if first block is a container.
		if ( 'campaignbridge/container' !== $block_name ) {
			return null;
		}

		$attributes = $first_block['attrs'] ?? array();
		if ( ! is_array( $attributes ) ) {
			return null;
		}

		$style = $attributes['style'] ?? array();
		if ( ! is_array( $style ) ) {
			return null;
		}

		$color_style = $style['color'] ?? array();
		if ( ! is_array( $color_style ) ) {
			return null;
		}

		$background = $color_style['background'] ?? null;

		return is_string( $background ) ? $background : null;
	}

	/**
	 * Convert WordPress blocks to HTML.
	 *
	 * @param array<string, mixed> $blocks Array of block data.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Converted HTML.
	 */
	public function convert_blocks_to_html( array $blocks, array $options ): string {
		$html = '';

		foreach ( $blocks as $block ) {
			$html .= $this->convert_block_to_html( $block, $options );
		}

		return $html;
	}

	/**
	 * Convert a single block to HTML.
	 *
	 * @param array<string, mixed> $block Block data.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Block HTML.
	 */
	public function convert_block_to_html( array $block, array $options ): string {
		$block_name = $block['blockName'] ?? '';

		if ( empty( $block_name ) ) {
			return '';
		}

		$attributes    = $block['attrs'] ?? array();
		$inner_content = $block['innerContent'] ?? array();
		$inner_blocks  = $block['innerBlocks'] ?? array();

		// Route to appropriate converter based on block namespace.
		if ( str_starts_with( $block_name, self::CAMPAIGNBRIDGE_BLOCK_PREFIX ) ) {
			return $this->convert_campaignbridge_block( $block_name, $attributes, $inner_content, $inner_blocks, $options );
		} elseif ( str_starts_with( $block_name, self::CORE_BLOCK_PREFIX ) ) {
			return $this->convert_core_block( $block_name, $attributes, $inner_content, $inner_blocks, $options );
		} else {
			return $this->convert_unknown_block( $block, $options );
		}
	}

	/**
	 * Convert core WordPress blocks to HTML.
	 *
	 * @param string               $block_type Block type.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string>        $inner_content Inner content.
	 * @param array<string, mixed> $inner_blocks Inner blocks.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Block HTML.
	 */
	public function convert_core_block( string $block_type, array $attributes, array $inner_content, array $inner_blocks, array $options ): string {
		$block_name = str_replace( self::CORE_BLOCK_PREFIX, '', $block_type );

		return match ( $block_name ) {
			'paragraph' => $this->convert_paragraph_block( $attributes, $inner_content ),
			'heading' => $this->convert_heading_block( $attributes, $inner_content ),
			'image' => $this->convert_image_block( $attributes ),
			'buttons' => $this->convert_buttons_block( $attributes, $inner_blocks ),
			'button' => $this->convert_button_block( $attributes ),
			'columns' => $this->convert_columns_block( $inner_blocks, $options ),
			'group' => $this->convert_group_block( $attributes, $inner_blocks ),
			'spacer' => $this->convert_spacer_block( $attributes ),
			'separator' => $this->convert_separator_block( $attributes ),
			default => $this->convert_unknown_block( array( 'blockName' => $block_type ), $options ),
		};
	}

	/**
	 * Convert CampaignBridge blocks to HTML.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string>        $inner_content Inner content.
	 * @param array<string, mixed> $inner_blocks Inner blocks.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Block HTML.
	 */
	public function convert_campaignbridge_block( string $block_name, array $attributes, array $inner_content, array $inner_blocks, array $options ): string {
		$block_type = str_replace( self::CAMPAIGNBRIDGE_BLOCK_PREFIX, '', $block_name );

		return match ( $block_type ) {
			'container' => $this->convert_container_block( $attributes, $inner_content, $inner_blocks ),
			'post-card' => $this->convert_post_card_block( $attributes ),
			'post-title' => $this->convert_post_title_block( $attributes ),
			'post-excerpt' => $this->convert_post_excerpt_block( $attributes ),
			'post-image' => $this->convert_post_image_block( $attributes ),
			'post-button' => $this->convert_post_button_block( $attributes ),
			default => $this->convert_unknown_block( array( 'blockName' => $block_name ), $options ),
		};
	}

	/**
	 * Convert container block to HTML.
	 *
	 * @param array<string, mixed> $attributes    Block attributes.
	 * @param array<int, string>   $inner_content Inner content.
	 * @param array<string, mixed> $inner_blocks Inner blocks.
	 * @return string Block HTML.
	 */
	private function convert_container_block( array $attributes, array $inner_content, array $inner_blocks ): string {
		$bg_color = $attributes['backgroundColor'] ?? '#ffffff';
		$content  = '';

		// Process inner blocks.
		foreach ( $inner_blocks as $inner_block ) {
			$content .= $this->convert_block_to_html( $inner_block, array() );
		}

		return sprintf(
			'<table width="100%%" cellspacing="0" cellpadding="0" border="0" style="background-color: %s;">
				<tr>
					<td style="padding: 20px;">
						%s
					</td>
				</tr>
			</table>',
			esc_attr( $bg_color ),
			$content
		);
	}

	/**
	 * Convert paragraph block to HTML.
	 *
	 * @param array<string, mixed> $attributes    Block attributes.
	 * @param array<int, string>   $inner_content Inner content.
	 * @return string Block HTML.
	 */
	private function convert_paragraph_block( array $attributes, array $inner_content ): string {
		$align   = $attributes['align'] ?? 'left';
		$content = implode( '', $inner_content );

		return sprintf(
			'<p style="margin: 0 0 16px 0; text-align: %s; line-height: 1.6;">%s</p>',
			esc_attr( $align ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Convert heading block to HTML.
	 *
	 * @param array<string, mixed> $attributes    Block attributes.
	 * @param array<int, string>   $inner_content Inner content.
	 * @return string Block HTML.
	 */
	private function convert_heading_block( array $attributes, array $inner_content ): string {
		$level   = $attributes['level'] ?? 2;
		$align   = $attributes['align'] ?? 'left';
		$content = implode( '', $inner_content );

		$font_size = match ( $level ) {
			1 => '28px',
			2 => '24px',
			3 => '20px',
			4 => '18px',
			5 => '16px',
			6 => '14px',
			default => '24px',
		};

		$tag = "h{$level}";

		return sprintf(
			'<%1$s style="margin: 0 0 16px 0; text-align: %2$s; font-size: %3$s; font-weight: bold; line-height: 1.2;">%4$s</%1$s>',
			$tag,
			esc_attr( $align ),
			$font_size,
			wp_kses_post( $content )
		);
	}

	/**
	 * Convert image block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_image_block( array $attributes ): string {
		$url    = $attributes['url'] ?? '';
		$alt    = $attributes['alt'] ?? '';
		$width  = $attributes['width'] ?? '';
		$height = $attributes['height'] ?? '';

		if ( empty( $url ) ) {
			return '';
		}

		$style = 'max-width: 100%; height: auto;';
		if ( $width ) {
			$style .= " width: {$width}px;";
		}
		if ( $height ) {
			$style .= " height: {$height}px;";
		}

		return sprintf(
			'<img src="%s" alt="%s" style="%s" border="0" />',
			esc_url( $url ),
			esc_attr( $alt ),
			$style
		);
	}

	/**
	 * Convert buttons block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string, mixed> $inner_blocks Inner blocks.
	 * @return string Block HTML.
	 */
	private function convert_buttons_block( array $attributes, array $inner_blocks ): string {
		$layout      = $attributes['layout'] ?? array();
		$orientation = $layout['orientation'] ?? 'horizontal';

		$buttons_html = '';
		foreach ( $inner_blocks as $button_block ) {
			if ( ( $button_block['blockName'] ?? '' ) === 'core/button' ) {
				$buttons_html .= $this->convert_button_block( $button_block['attrs'] ?? array() );
			}
		}

		if ( 'vertical' === $orientation ) {
			return sprintf(
				'<table width="100%%" cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td style="padding-bottom: 8px;">%s</td>
					</tr>
				</table>',
				$buttons_html
			);
		}

		return $buttons_html;
	}

	/**
	 * Convert button block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_button_block( array $attributes ): string {
		$text             = $attributes['text'] ?? '';
		$url              = $attributes['url'] ?? '';
		$background_color = $attributes['backgroundColor'] ?? '#0073aa';
		$text_color       = $attributes['textColor'] ?? '#ffffff';
		$width            = $attributes['width'] ?? 100;

		if ( empty( $text ) || empty( $url ) ) {
			return '';
		}

		// VML fallback for Outlook.
		$vml_fallback = sprintf(
			'<!--[if mso]>
			<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="%s" style="height:40px;v-text-anchor:middle;width:%dpx;" arcsize="10%%" stroke="f" fillcolor="%s">
				<w:anchorlock/>
				<center style="color:%s;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;">%s</center>
			</v:roundrect>
			<![endif]-->',
			esc_url( $url ),
			$width,
			esc_attr( $background_color ),
			esc_attr( $text_color ),
			esc_html( $text )
		);

		return sprintf(
			'%s
			<table width="%d" cellspacing="0" cellpadding="0" border="0" style="border-collapse: collapse;">
				<tr>
					<td style="background-color: %s; border-radius: 4px; text-align: center; padding: 12px 24px;">
						<a href="%s" style="color: %s; text-decoration: none; font-weight: bold; display: inline-block;">%s</a>
					</td>
				</tr>
			</table>',
			$vml_fallback,
			$width,
			esc_attr( $background_color ),
			esc_url( $url ),
			esc_attr( $text_color ),
			esc_html( $text )
		);
	}

	/**
	 * Convert columns block to HTML.
	 *
	 * @param array<string, mixed> $inner_blocks Inner blocks.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Block HTML.
	 */
	private function convert_columns_block( array $inner_blocks, array $options ): string {
		$columns_html = '';
		$column_count = count( $inner_blocks );

		foreach ( $inner_blocks as $column_block ) {
			if ( ( $column_block['blockName'] ?? '' ) === 'core/column' ) {
				$inner_html = '';
				foreach ( $column_block['innerBlocks'] ?? array() as $inner_block ) {
					$inner_html .= $this->convert_block_to_html( $inner_block, $options );
				}

				$width         = $column_count > 0 ? round( 100 / $column_count ) : 100;
				$columns_html .= sprintf(
					'<td width="%d%%" valign="top" style="padding: 0 10px;">%s</td>',
					$width,
					$inner_html
				);
			}
		}

		return sprintf(
			'<table width="100%%" cellspacing="0" cellpadding="0" border="0">
				<tr>%s</tr>
			</table>',
			$columns_html
		);
	}

	/**
	 * Convert group block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string, mixed> $inner_blocks Inner blocks.
	 * @return string Block HTML.
	 */
	private function convert_group_block( array $attributes, array $inner_blocks ): string {
		$bg_color = $attributes['backgroundColor'] ?? 'transparent';
		$content  = '';

		foreach ( $inner_blocks as $inner_block ) {
			$content .= $this->convert_block_to_html( $inner_block, array() );
		}

		return sprintf(
			'<table width="100%%" cellspacing="0" cellpadding="0" border="0" style="background-color: %s;">
				<tr>
					<td style="padding: 20px;">%s</td>
				</tr>
			</table>',
			esc_attr( $bg_color ),
			$content
		);
	}

	/**
	 * Convert spacer block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_spacer_block( array $attributes ): string {
		$height = $attributes['height'] ?? 100;

		return sprintf(
			'<table width="100%%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td height="%d" style="font-size: 0; line-height: 0;">&nbsp;</td>
				</tr>
			</table>',
			intval( $height )
		);
	}

	/**
	 * Convert separator block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_separator_block( array $attributes ): string {
		$color = $attributes['color'] ?? '#ddd';
		$style = $attributes['style'] ?? 'solid';

		return sprintf(
			'<table width="100%%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td style="border-top: 1px %s %s; font-size: 0; line-height: 0;">&nbsp;</td>
				</tr>
			</table>',
			$style,
			esc_attr( $color )
		);
	}

	/**
	 * Convert post card block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_post_card_block( array $attributes ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// This would integrate with WordPress posts - simplified for now.
		return '<table width="100%" cellspacing="0" cellpadding="0" border="0">
			<tr>
				<td style="padding: 20px; border: 1px solid #ddd;">
					<h3 style="margin: 0 0 10px 0;">Post Title</h3>
					<p style="margin: 0 0 10px 0;">Post excerpt...</p>
					<a href="#" style="color: #0073aa;">Read more</a>
				</td>
			</tr>
		</table>';
	}

	/**
	 * Convert post title block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_post_title_block( array $attributes ): string {
		$level = $attributes['level'] ?? 2;
		$tag   = "h{$level}";

		return sprintf(
			'<%1$s style="margin: 0 0 16px 0; font-size: 24px; font-weight: bold; line-height: 1.2;">{post_title}</%1$s>',
			$tag
		);
	}

	/**
	 * Convert post excerpt block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_post_excerpt_block( array $attributes ): string {
		$word_count = $attributes['wordCount'] ?? 55;

		return sprintf(
			'<p style="margin: 0 0 16px 0; line-height: 1.6;">{post_excerpt:%d}</p>',
			intval( $word_count )
		);
	}

	/**
	 * Convert post image block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_post_image_block( array $attributes ): string {
		$size = $attributes['sizeSlug'] ?? 'large';

		return sprintf(
			'<img src="{post_image:%s}" alt="{post_title}" style="max-width: 100%%; height: auto;" border="0" />',
			esc_attr( $size )
		);
	}

	/**
	 * Convert post button block to HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Block HTML.
	 */
	private function convert_post_button_block( array $attributes ): string {
		$text             = $attributes['text'] ?? 'Read More';
		$background_color = $attributes['backgroundColor'] ?? '#0073aa';
		$text_color       = $attributes['textColor'] ?? '#ffffff';

		return sprintf(
			'<table width="100" cellspacing="0" cellpadding="0" border="0" style="border-collapse: collapse;">
				<tr>
					<td style="background-color: %s; border-radius: 4px; text-align: center; padding: 12px 24px;">
						<a href="{post_link}" style="color: %s; text-decoration: none; font-weight: bold; display: inline-block;">%s</a>
					</td>
				</tr>
			</table>',
			esc_attr( $background_color ),
			esc_attr( $text_color ),
			esc_html( $text )
		);
	}

	/**
	 * Convert unknown block to HTML.
	 *
	 * @param array<string, mixed> $block Block data.
	 * @param array<string, mixed> $options Generation options.
	 * @return string Block HTML.
	 */
	private function convert_unknown_block( array $block, array $options ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$block_name = $block['blockName'] ?? 'unknown';

		// Log unknown block for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\CampaignBridge\Core\Error_Handler::info(
				'CampaignBridge: Unknown block type encountered',
				array( 'block_name' => $block_name )
			);
		}

		return sprintf(
			'<!-- Unknown block: %s -->',
			esc_html( $block_name )
		);
	}
}
