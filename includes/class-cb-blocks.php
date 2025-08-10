<?php
/**
 * Custom blocks registration.
 *
 * @package CampaignBridge
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class CB_Blocks {
	/**
	 * Register block types used by CampaignBridge.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		// Let block.json handle render via "render": "file:./render.php"
		register_block_type( __DIR__ . '/../src/blocks/email-post-slot' );
	}
}
