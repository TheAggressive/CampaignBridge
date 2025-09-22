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
	$max_words   = isset( $attributes['maxWords'] ) ? absint( $attributes['maxWords'] ) : 50;
	$show_more   = ! empty( $attributes['showMore'] );
	$more_style  = isset( $attributes['moreStyle'] ) ? (string) $attributes['moreStyle'] : 'link';
	$more_label  = isset( $attributes['moreLabel'] ) ? (string) $attributes['moreLabel'] : 'Read more';
	$link_to     = isset( $attributes['linkTo'] ) ? (string) $attributes['linkTo'] : 'post';
	$more_prefix = isset( $attributes['morePrefix'] ) ? (string) $attributes['morePrefix'] : '';
	$raw_excerpt = (string) get_post_field( 'post_excerpt', $post_id );
	$raw_content = (string) get_post_field( 'post_content', $post_id );
	$raw         = '' !== $raw_excerpt ? $raw_excerpt : $raw_content;
	$decoded     = html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' );
	$plain       = trim( preg_replace( '/<[^>]*>/', ' ', $decoded ) );

	// Split into words and limit by word count.
	$words         = preg_split( '/\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY );
	$limited_words = array_slice( $words, 0, max( 0, $max_words ) );
	$excerpt       = esc_html( implode( ' ', $limited_words ) );

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
		if ( 'button' === $more_style ) {
			$prefix    = '' !== $more_prefix ? esc_html( $more_prefix ) . ' ' : '';
			$more_html = sprintf(
				'<p style="margin:12px 0 0;">%s<a href="%s" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:10px 16px;border-radius:4px;">%s</a></p>',
				$prefix,
				esc_url( $link ),
				$label
			);
		} else {
			$prefix    = '' !== $more_prefix ? esc_html( $more_prefix ) . ' ' : '';
			$more_html = sprintf( '%s<a href="%s">%s</a>', $prefix, esc_url( $link ), $label );
		}
	}

	return sprintf( '<div style="font-size:14px;line-height:1.5;color:#333;">%s%s</div>', $excerpt, $more_html );
};
