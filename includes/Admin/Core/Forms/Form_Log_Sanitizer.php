<?php
/**
 * Sanitizes submitted form values before structured logging.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Prevents secrets and unbounded values from entering application logs. */
final class Form_Log_Sanitizer {
	/**
	 * Sanitize a form payload for logging.
	 *
	 * @param array<string, mixed> $form_data Submitted form data.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $form_data ): array {
		$sanitized = array();

		foreach ( $form_data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_string( $value ) ) {
				$value = substr( $value, 0, 100 );
				if ( 100 === strlen( $value ) ) {
					$value .= '...';
				}

				if ( self::is_sensitive_key( $key ) ) {
					$value = '[REDACTED]';
				}
			} elseif ( is_array( $value ) ) {
				$value = '[ARRAY]';
			} elseif ( ! is_scalar( $value ) ) {
				$value = '[' . gettype( $value ) . ']';
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Determine whether a normalized key describes a secret.
	 *
	 * @param string $key Normalized form key.
	 */
	private static function is_sensitive_key( string $key ): bool {
		return str_contains( $key, 'password' )
			|| str_contains( $key, 'api_key' )
			|| str_contains( $key, 'secret' );
	}
}
