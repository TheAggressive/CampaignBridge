<?php
/**
 * Server-side render for email post slot.
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content ) {
	// Optionally wrap entire slot in a link to the post or post type archive.
	$slot_link_enabled = ! empty( $attributes['slotLinkEnabled'] );
	$slot_link_to      = isset( $attributes['slotLinkTo'] ) ? (string) $attributes['slotLinkTo'] : 'post';
	$post_id           = isset( $attributes['postId'] ) ? absint( $attributes['postId'] ) : 0;
	$url               = '';
	if ( $slot_link_enabled && $post_id > 0 ) {
		if ( 'postType' === $slot_link_to ) {
			$pt = get_post_type( $post_id );
			if ( $pt ) {
				$archive = get_post_type_archive_link( $pt );
				if ( $archive ) {
					$url = $archive;
				}
			}
		}
		if ( '' === $url ) {
			$url = get_permalink( $post_id );
		}
	}

	$html = (string) $content;
	if ( $url ) {
		// Table-based link wrapper for email safety.
		$html = sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"><tr><td><a href="%s" style="text-decoration:none;color:inherit;display:block;">%s</a></td></tr></table>',
			esc_url( $url ),
			$html
		);
	}
	return $html;
};
