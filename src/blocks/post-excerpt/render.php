<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

return function ( $attributes, $content, $block ) {
	// ... your existing post/attributes/excerpt code ...

	// Build $link (match JS: 'post'|'postType')
	$link = '';
	if ( ! empty( $attributes['showMore'] ) ) {
		if ( isset( $attributes['linkTo'] ) && 'postType' === $attributes['linkTo'] ) {
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

	// Use saved inner markup exactly; swap href="#" -> $link
	$more_html = '';
	if ( ! empty( $attributes['showMore'] ) && $content ) {
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
			$more_html = preg_replace( '#href=(["\'])\#\1#i', 'href="' . esc_url( $link ) . '"', $more_html );
		}

		// optional: prepend your separator text if you have it
		// $more_html = $separator_text . $more_html; // if applicable
	}

	return sprintf(
		'<div style="font-size:14px;line-height:1.5;color:#333;">%s%s</div>',
		$excerpt,
		$more_html
	);
};
