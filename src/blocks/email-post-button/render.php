<?php
/**
 * Server render for email-post-button.
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content, $block ) {
	$ctx     = isset( $block->context ) && is_array( $block->context ) ? $block->context : array();
	$post_id = isset( $ctx['campaignbridge:postId'] ) ? absint( $ctx['campaignbridge:postId'] ) : 0;
	$label   = isset( $ctx['campaignbridge:ctaLabel'] ) ? (string) $ctx['campaignbridge:ctaLabel'] : 'Read more';
	if ( $post_id <= 0 ) {
		return '';
	}
	$link = esc_url( get_permalink( $post_id ) );
	$cta  = esc_html( $label ? $label : 'Read more' );
	return sprintf( '<p><a href="%s" style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:10px 16px;border-radius:4px;">%s</a></p>', $link, $cta );
};
