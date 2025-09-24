<?php
/**
 * Server render for post-excerpt (preserve inner markup/styles).
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content, $block ) {
	$ctx     = isset( $block->context ) && is_array( $block->context ) ? $block->context : array();
	$post_id = isset( $ctx['campaignbridge:postId'] ) ? absint( $ctx['campaignbridge:postId'] ) : 0;
	if ( $post_id <= 0 ) {
		return '';
	}

	$max_words = isset( $attributes['maxWords'] ) ? absint( $attributes['maxWords'] ) : 50;
	$show_more = ! empty( $attributes['showMore'] );
	$link_to   = isset( $attributes['linkTo'] ) ? (string) $attributes['linkTo'] : 'post';

	// Separator (support legacy misspelling keys just in case).
	$enable_separator           = isset( $attributes['enableSeparator'] )
		? (bool) $attributes['enableSeparator']
		: ( isset( $attributes['enableSeperator'] ) ? (bool) $attributes['enableSeperator'] : false );
	$custom_separator           = isset( $attributes['customSeparator'] )
		? (string) $attributes['customSeparator']
		: ( isset( $attributes['customSeperator'] ) ? (string) $attributes['customSeperator'] : '' );
	$add_space_before_separator = isset( $attributes['addSpaceBeforeSeparator'] )
		? (bool) $attributes['addSpaceBeforeSeparator']
		: ( isset( $attributes['addSpaceBeforeSeperator'] ) ? (bool) $attributes['addSpaceBeforeSeperator'] : false );
	$add_space_before_link      = isset( $attributes['addSpaceBeforeLink'] ) ? (bool) $attributes['addSpaceBeforeLink'] : true;

	// Excerpt from post.
	$raw_excerpt = (string) get_post_field( 'post_excerpt', $post_id );
	$raw_content = (string) get_post_field( 'post_content', $post_id );
	$raw         = '' !== $raw_excerpt ? $raw_excerpt : $raw_content;
	$decoded     = html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' );
	$plain       = trim( preg_replace( '/<[^>]*>/', ' ', $decoded ) );

	$words         = preg_split( '/\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY );
	$limited_words = array_slice( $words, 0, max( 0, $max_words ) );
	$excerpt       = esc_html( implode( ' ', $limited_words ) );
	if ( $show_more && substr( $excerpt, -1 ) === '.' ) {
		$excerpt = rtrim( substr( $excerpt, 0, -1 ) );
	}

	// Compute link.
	$link = '';
	if ( $show_more ) {
		if ( 'postType' === $link_to ) {
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

	// Separator text.
	$separator_text = '';
	if ( $enable_separator && '' !== $custom_separator ) {
		$separator_text = esc_html( $custom_separator ) . ' ';
		if ( $add_space_before_separator ) {
			$separator_text = ' ' . $separator_text;
		}
	}
	if ( $add_space_before_link && '' === $separator_text && $show_more ) {
		$separator_text = ' ';
	}

	// Use saved inner markup; swap href="#" to real link.
	$more_html = '';
	if ( $show_more && $content ) {
		$more_html = $content;

		if ( $link && class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$p = new WP_HTML_Tag_Processor( $more_html );
			while ( $p->next_tag( 'a' ) ) {
				$href = $p->get_attribute( 'href' );
				if ( $href === '#' || $href === '' ) {
					$p->set_attribute( 'href', esc_url( $link ) );
				}
			}
			$more_html = $p->get_updated_html();
		} elseif ( $link ) {
			$more_html = preg_replace(
				'#href=(["\'])\#\1#i',
				'href="' . esc_url( $link ) . '"',
				$more_html
			);
		}

		if ( $separator_text ) {
			$more_html = $separator_text . $more_html;
		}
	}

	return sprintf(
		'<div style="font-size:14px;line-height:1.5;color:#333;">%s%s</div>',
		$excerpt,
		$more_html
	);
};
