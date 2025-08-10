<?php
/**
 * Server-side render for email post slot.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return function ( $attributes, $content ) {
	$slot_id = isset( $attributes['slotId'] ) ? sanitize_key( (string) $attributes['slotId'] ) : '';
	if ( '' === $slot_id ) {
		$slot_id = 'slot_' . wp_generate_password( 6, false, false );
	}
	return '<!--CB_SLOT:' . esc_html( $slot_id ) . '-->';
};
