<?php
/**
 * Server render for email-post-excerpt.
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content, $block ) {
	$ctx     = isset( $block->context ) && is_array( $block->context ) ? $block->context : array();
	$post_id = isset( $ctx['campaignbridge:postId'] ) ? absint( $ctx['campaignbridge:postId'] ) : 0;
	// showExcerpt context removed; always render when postId is present.
	if ( $post_id <= 0 ) {
		return '';
	}
	$max_words                  = isset( $attributes['maxWords'] ) ? absint( $attributes['maxWords'] ) : 50;
	$show_more                  = ! empty( $attributes['showMore'] );
	$more_style                 = isset( $attributes['moreStyle'] ) ? (string) $attributes['moreStyle'] : 'link';
	$more_label                 = isset( $attributes['moreLabel'] ) ? (string) $attributes['moreLabel'] : 'Read more';
	$link_to                    = isset( $attributes['linkTo'] ) ? (string) $attributes['linkTo'] : 'post';
	$more_prefix                = isset( $attributes['morePrefix'] ) ? (string) $attributes['morePrefix'] : '';
	$add_space_before_link      = isset( $attributes['addSpaceBeforeLink'] ) ? (bool) $attributes['addSpaceBeforeLink'] : true;
	$enable_separator           = isset( $attributes['enableSeparator'] ) ? (bool) $attributes['enableSeparator'] : false;
	$separator_type             = isset( $attributes['separatorType'] ) ? (string) $attributes['separatorType'] : 'custom';
	$custom_separator           = isset( $attributes['customSeparator'] ) ? (string) $attributes['customSeparator'] : '';
	$add_space_before_separator = isset( $attributes['addSpaceBeforeSeparator'] ) ? (bool) $attributes['addSpaceBeforeSeparator'] : false;
	$button_layout              = isset( $attributes['buttonLayout'] ) ? (string) $attributes['buttonLayout'] : 'new-line';
	$button_alignment           = isset( $attributes['buttonAlignment'] ) ? (string) $attributes['buttonAlignment'] : 'left';
	$button_bg                  = isset( $attributes['buttonBg'] ) ? (string) $attributes['buttonBg'] : '#111111';
	$button_color               = isset( $attributes['buttonColor'] ) ? (string) $attributes['buttonColor'] : '#ffffff';
	$button_radius              = isset( $attributes['buttonRadius'] ) ? (int) $attributes['buttonRadius'] : 4;
	$button_padding_x           = isset( $attributes['buttonPaddingX'] ) ? (int) $attributes['buttonPaddingX'] : 16;
	$button_padding_y           = isset( $attributes['buttonPaddingY'] ) ? (int) $attributes['buttonPaddingY'] : 10;
	$button_margin_top          = isset( $attributes['buttonMarginTop'] ) ? (int) $attributes['buttonMarginTop'] : 12;
	$button_margin_bottom       = isset( $attributes['buttonMarginBottom'] ) ? (int) $attributes['buttonMarginBottom'] : 0;
	$button_margin_left         = isset( $attributes['buttonMarginLeft'] ) ? (int) $attributes['buttonMarginLeft'] : 0;
	$button_margin_right        = isset( $attributes['buttonMarginRight'] ) ? (int) $attributes['buttonMarginRight'] : 0;
	$raw_excerpt                = (string) get_post_field( 'post_excerpt', $post_id );
	$raw_content                = (string) get_post_field( 'post_content', $post_id );
	$raw                        = '' !== $raw_excerpt ? $raw_excerpt : $raw_content;
	$decoded                    = html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' );
	$plain                      = trim( preg_replace( '/<[^>]*>/', ' ', $decoded ) );

	// Split into words and limit by word count.
	$words         = preg_split( '/\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY );
	$limited_words = array_slice( $words, 0, max( 0, $max_words ) );
	$excerpt       = esc_html( implode( ' ', $limited_words ) );

	// Remove trailing period if show more link is enabled.
	if ( $show_more && substr( $excerpt, -1 ) === '.' ) {
		$excerpt = rtrim( substr( $excerpt, 0, -1 ) );
	}

	$link = '';
	if ( $show_more ) {
		if ( 'parent' === $link_to ) {
			$post_type = get_post_type( $post_id );
			if ( $post_type ) {
				$archive = get_post_type_archive_link( $post_type );
				if ( $archive ) {
					$link = $archive;
				}
			}
		}
		if ( '' === $link ) {
			$link = get_permalink( $post_id );
		}
	}

	$more_html = '';
	if ( $show_more && $link ) {
		$label = esc_html( $more_label ? $more_label : 'Read more' );
		// Build the separator.
		$separator_text = '';
		if ( $enable_separator && '' !== $custom_separator ) {
			$separator_text = esc_html( $custom_separator );
			if ( $add_space_before_separator ) {
				$separator_text = ' ' . $separator_text;
			}
		}
		// Add space before link if no separator or if separator doesn't have space
		if ( $add_space_before_link && '' === $separator_text ) {
			$separator_text = ' ';
		}

		if ( 'button' === $more_style ) {
			if ( 'inline' === $button_layout ) {
				// Inline button at the end of excerpt
				$button_style = sprintf(
					'display:inline-block;background:%s;color:%s;text-decoration:none;padding:%dpx %dpx;border-radius:%dpx;margin-left:%dpx;',
					esc_attr( $button_bg ),
					esc_attr( $button_color ),
					$button_padding_y,
					$button_padding_x,
					$button_radius,
					$add_space_before_link ? 8 : 0
				);
				$more_html    = sprintf( '%s<a href="%s" style="%s">%s</a>', $separator_text, esc_url( $link ), $button_style, $label );
			} else {
				// New line or Full-width button with custom margins
				$button_style = sprintf(
					'display:%s;width:%s;background:%s;color:%s;text-decoration:none;padding:%dpx %dpx;border-radius:%dpx;',
					'full-width' === $button_layout ? 'block' : 'inline-block',
					'full-width' === $button_layout ? '100%' : 'auto',
					esc_attr( $button_bg ),
					esc_attr( $button_color ),
					$button_padding_y,
					$button_padding_x,
					$button_radius
				);
				$more_html    = sprintf(
					'<div style="margin:%dpx %dpx %dpx %dpx;text-align:%s;">%s<a href="%s" style="%s">%s</a></div>',
					$button_margin_top,
					$button_margin_right,
					$button_margin_bottom,
					$button_margin_left,
					esc_attr( $button_alignment ),
					$separator_text,
					esc_url( $link ),
					$button_style,
					$label
				);
			}
		} else {
			$more_html = sprintf( '%s<a href="%s">%s</a>', $separator_text, esc_url( $link ), $label );
		}
	}

	return sprintf( '<div style="font-size:14px;line-height:1.5;color:#333;">%s%s</div>', $excerpt, $more_html );
};
