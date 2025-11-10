<?php
/**
 * Rate Limiter for CampaignBridge REST API.
 *
 * Provides centralized rate limiting functionality for REST API endpoints
 * with configurable limits and time windows.
 *
 * @package CampaignBridge\REST
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter class.
 *
 * Handles rate limiting for REST API endpoints using WordPress transients
 * with user/IP-based identification.
 */
class Rate_Limiter {
	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (like X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				// Validate IP format.
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
	}

	/**
	 * Check rate limit for an endpoint.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param string $cache_prefix  Cache key prefix to use.
	 * @param int    $max_requests  Maximum requests allowed per time window.
	 * @param int    $time_window   Time window in seconds.
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_rate_limit(
		string $endpoint_name,
		string $cache_prefix = Rest_Constants::CACHE_KEY_PREFIX_GENERAL,
		int $max_requests = Rest_Constants::RATE_LIMIT_REQUESTS,
		int $time_window = Rest_Constants::RATE_LIMIT_WINDOW
	): bool|WP_Error {
		$user_id = get_current_user_id();

		// For better security, use both user ID and IP address.
		$ip_address = self::get_client_ip();
		$identifier = $user_id ? 'user_' . $user_id : 'ip_' . $ip_address;

		$cache_key = $cache_prefix . $endpoint_name . '_' . $identifier;
		$requests  = \CampaignBridge\Core\Storage::get_transient( $cache_key );

		if ( false === $requests ) {
			$requests = 0;
		}

		if ( $requests >= $max_requests ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: number of seconds until reset */
					__( 'Rate limit exceeded. Try again in %d seconds.', 'campaignbridge' ),
					$time_window
				),
				array( 'status' => Rest_Constants::HTTP_TOO_MANY_REQUESTS )
			);
		}

		\CampaignBridge\Core\Storage::set_transient( $cache_key, $requests + 1, $time_window );
		return true;
	}

	/**
	 * Check rate limit requiring authentication.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param string $cache_prefix  Cache key prefix to use.
	 * @param int    $max_requests  Maximum requests allowed per time window.
	 * @param int    $time_window   Time window in seconds.
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited or not authenticated.
	 */
	public static function check_rate_limit_authenticated(
		string $endpoint_name,
		string $cache_prefix = Rest_Constants::CACHE_KEY_PREFIX_GENERAL,
		int $max_requests = Rest_Constants::RATE_LIMIT_REQUESTS,
		int $time_window = Rest_Constants::RATE_LIMIT_WINDOW
	): bool|WP_Error {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error(
				'rate_limit_no_user',
				__( 'User not authenticated', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_UNAUTHORIZED )
			);
		}

		$cache_key = $cache_prefix . $endpoint_name . '_' . $user_id;
		$requests  = \CampaignBridge\Core\Storage::get_transient( $cache_key );

		if ( false === $requests ) {
			$requests = 0;
		}

		if ( $requests >= $max_requests ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: number of seconds until reset */
					__( 'Rate limit exceeded. Try again in %d seconds.', 'campaignbridge' ),
					$time_window
				),
				array( 'status' => Rest_Constants::HTTP_TOO_MANY_REQUESTS )
			);
		}

		\CampaignBridge\Core\Storage::set_transient( $cache_key, $requests + 1, $time_window );
		return true;
	}
}
