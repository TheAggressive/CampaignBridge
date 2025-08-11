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
	$slot_id = isset( $attributes['slotId'] ) ? sanitize_key( (string) $attributes['slotId'] ) : '';
	if ( '' === $slot_id ) {
		$slot_id = 'slot_' . wp_generate_password( 6, false, false );
	}
	// Persist the designed inner layout as part of the placeholder so we can token-replace later.
	$wrapped = '<!--CB_SLOT:' . esc_html( $slot_id ) . '-->';
	if ( is_string( $content ) && '' !== trim( $content ) ) {
		$wrapped .= '<div class="cb-slot-layout">' . $content . '</div>';
	}
	return $wrapped;
};
