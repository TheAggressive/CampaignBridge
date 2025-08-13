<?php
/**
 * CampaignBridge render helpers.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
namespace CampaignBridge\Render;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Rendering helpers for block templates and email-safe HTML.
 */
class Render {
	/**
	 * Running index for implicit slot keys so mapping remains stable across requests.
	 *
	 * @var int
	 */
	private static $slot_index = 0;
	/**
	 * Parse blocks to discover post slots in the template content.
	 *
	 * @param string $content Raw post_content of the template.
	 * @return array[] Array of slot descriptors { key, showImage, showExcerpt, ctaLabel }.
	 */
	public static function discover_slots_from_content( $content ) {
		$slots  = array();
		$blocks = function_exists( 'parse_blocks' ) ? parse_blocks( (string) $content ) : array();
		$walk   = function ( $nodes ) use ( &$walk, &$slots ) {
			foreach ( $nodes as $b ) {
				if ( ! is_array( $b ) ) {
					continue; }
				$name  = isset( $b['blockName'] ) ? (string) $b['blockName'] : '';
				$attrs = isset( $b['attrs'] ) && is_array( $b['attrs'] ) ? $b['attrs'] : array();
				if ( 'campaignbridge/email-post-slot' === $name ) {
					$slot_id = isset( $attrs['slotId'] ) ? sanitize_key( (string) $attrs['slotId'] ) : '';
					if ( '' === $slot_id ) {
						++self::$slot_index;
						$slot_id = 'slot_' . self::$slot_index;
					}
					$show_image   = isset( $attrs['showImage'] ) ? (bool) $attrs['showImage'] : true;
					$show_excerpt = isset( $attrs['showExcerpt'] ) ? (bool) $attrs['showExcerpt'] : true;
					$cta_label    = isset( $attrs['ctaLabel'] ) ? (string) $attrs['ctaLabel'] : 'Read more';
					$slots[]      = array(
						'key'         => $slot_id,
						'showImage'   => $show_image,
						'showExcerpt' => $show_excerpt,
						'ctaLabel'    => $cta_label,
					);
				}
				if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
					$walk( $b['innerBlocks'] );
				}
			}
		};
		$walk( $blocks );
		return $slots;
	}

	/**
	 * Render full HTML for a given template and slots map.
	 *
	 * @param int   $template_id Template post ID.
	 * @param array $slots_map   Map of slotId => postId.
	 * @return string Rendered HTML document.
	 */
	public static function render_template_html( $template_id, $slots_map ) {
		$post = get_post( $template_id );
		if ( ! $post || 'cb_template' !== $post->post_type ) {
			return ''; }
		$content          = (string) $post->post_content;
		$blocks           = function_exists( 'parse_blocks' ) ? parse_blocks( $content ) : array();
		$html             = '';
		self::$slot_index = 0;
		foreach ( $blocks as $b ) {
			$html .= self::render_node( $b, $slots_map );
		}
		$final = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width'></head><body>" . $html . '</body></html>';
		// Optionally inline CSS if Emogrifier is available.
		if ( class_exists( '\Pelago\Emogrifier\CssInliner' ) ) {
			$css   = (string) apply_filters( 'campaignbridge_email_inline_css', '' );
			$cls   = '\Pelago\Emogrifier\CssInliner';
			$final = $cls::fromHtml( $final )->inlineCss( $css )->render();
		}
		return $final;
	}

	/**
	 * Render a parsed block node into HTML.
	 *
	 * @param array $node      Parsed block array.
	 * @param array $slots_map Slot map used to fill post slots.
	 * @return string HTML
	 */
	private static function render_node( $node, $slots_map ) {
		if ( ! is_array( $node ) ) {
			return ''; }
		$name  = isset( $node['blockName'] ) ? (string) $node['blockName'] : '';
		$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
		if ( 'campaignbridge/email-post-slot' === $name ) {
			$slot_id = isset( $attrs['slotId'] ) ? sanitize_key( (string) $attrs['slotId'] ) : '';
			if ( '' === $slot_id ) {
				++self::$slot_index;
				$slot_id = 'slot_' . self::$slot_index;
			}
			$post_id_attr = isset( $attrs['postId'] ) ? absint( $attrs['postId'] ) : 0;
			$post_id      = isset( $slots_map[ $slot_id ] ) ? absint( $slots_map[ $slot_id ] ) : $post_id_attr;
			$show_image   = isset( $attrs['showImage'] ) ? (bool) $attrs['showImage'] : true;
			$show_excerpt = isset( $attrs['showExcerpt'] ) ? (bool) $attrs['showExcerpt'] : true;
			$cta_label    = isset( $attrs['ctaLabel'] ) ? (string) $attrs['ctaLabel'] : 'Read more';
			// If the slot contains a custom layout (InnerBlocks), render it directly (block-based only).
			if ( ! empty( $node['innerBlocks'] ) && is_array( $node['innerBlocks'] ) ) {
				$layout = '';
				if ( function_exists( 'render_block' ) ) {
					foreach ( $node['innerBlocks'] as $child ) {
						$layout .= render_block( $child );
					}
				}
				if ( is_string( $layout ) && '' !== $layout ) {
					return sprintf( '<div data-cb-slot="%s">%s</div>', esc_attr( $slot_id ), $layout );
				}
			}
			// Fallback to default card layout.
			$fallback = self::render_post_card( $post_id, $show_image, $show_excerpt, $cta_label );
			return sprintf( '<div data-cb-slot="%s">%s</div>', esc_attr( $slot_id ), $fallback );
		}
		return function_exists( 'render_block' ) ? render_block( $node ) : '';
	}

	// Deprecated token builder removed (block-only rendering).

	/**
	 * Render a single post card suitable for email.
	 *
	 * @param int     $post_id      Post ID to render.
	 * @param boolean $show_image   Whether to output featured image.
	 * @param boolean $show_excerpt Whether to output excerpt.
	 * @param string  $cta_label    Button label.
	 * @return string HTML table block
	 */
	private static function render_post_card( $post_id, $show_image, $show_excerpt, $cta_label ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return ''; }
		$title   = esc_html( get_the_title( $post ) );
		$link    = esc_url( get_permalink( $post ) );
		$image   = $show_image ? get_the_post_thumbnail_url( $post, 'full' ) : '';
		$excerpt = '';
		if ( $show_excerpt ) {
			$raw     = (string) get_post_field( 'post_content', $post );
			$excerpt = wp_kses_post( wpautop( wp_trim_words( wp_strip_all_tags( $raw ), 40 ) ) );
		}
		$cta      = esc_html( $cta_label ? $cta_label : 'Read more' );
		$img_html = $image ? sprintf( '<img src="%s" alt="" style="display:block;width:100%%;height:auto;border:0;" />', esc_url( $image ) ) : '';
		return "<table role='presentation' width='100%%' cellpadding='0' cellspacing='0' style='max-width:600px;margin:0 auto;'><tr><td style='padding:16px;font-family:Arial, sans-serif;'>$img_html<h3 style='margin:12px 0 8px 0;font-size:18px;line-height:1.3;color:#111;'>$title</h3><div style='font-size:14px;line-height:1.5;color:#333;'>$excerpt</div><p style='margin:16px 0 0;'><a href='$link' style='display:inline-block;background:#111;color:#fff;text-decoration:none;padding:10px 16px;border-radius:4px;'>$cta</a></p></td></tr></table>";
	}
}
