<?php
/**
 * Server render for email-post-title.
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
	$title = esc_html( get_the_title( $post_id ) );
	return sprintf( '<h3 style="margin:12px 0 8px 0;font-size:18px;line-height:1.3;color:#111;">%s</h3>', $title );
};
