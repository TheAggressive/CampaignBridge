<?php
/**
 * Trusted client-address resolution.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a request address without trusting attacker-controlled headers.
 */
final class Client_Address {
	/**
	 * Resolve the address supplied by the web server or a trusted integration.
	 *
	 * @return string Valid IP address.
	 */
	public static function get(): string {
		$raw_remote_address = wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__ -- The server address is required for security throttling and is validated as an IP immediately below.
		$remote_address     = is_string( $raw_remote_address ) && false !== filter_var( $raw_remote_address, FILTER_VALIDATE_IP )
			? $raw_remote_address
			: '127.0.0.1';

		/**
		 * Filters the validated address used for throttling and security logs.
		 *
		 * A trusted reverse-proxy integration may replace REMOTE_ADDR here after
		 * validating the proxy hop. Request headers are deliberately not trusted.
		 *
		 * @param string               $remote_address Server-provided address.
		 * @param array<string, mixed> $server         Server variables for trusted integrations.
		 */
		$client_address = apply_filters( 'campaignbridge_client_ip', $remote_address, $_SERVER );
		if ( is_string( $client_address ) && false !== filter_var( $client_address, FILTER_VALIDATE_IP ) ) {
			return $client_address;
		}

		return '127.0.0.1';
	}
}
