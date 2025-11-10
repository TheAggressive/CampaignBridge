<?php
/**
 * Server render for email-post-image.
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content, $block ) {
	$ctx     = isset( $block->context ) && is_array( $block->context ) ? $block->context : array();
	$post_id = isset( $ctx['campaignbridge:postId'] ) ? absint( $ctx['campaignbridge:postId'] ) : 0;
	$show    = isset( $ctx['campaignbridge:showImage'] ) ? (bool) $ctx['campaignbridge:showImage'] : true;
	if ( $post_id <= 0 || ! $show ) {
		return '';
	}
	$url = get_the_post_thumbnail_url( $post_id, 'medium' );
	if ( ! $url ) {
		return '';
	}
	return sprintf( '<img src="%s" alt="" style="display:block;width:100%%;height:auto;border:0;" />', esc_url( $url ) );
};
