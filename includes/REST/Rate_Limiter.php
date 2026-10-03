<?php
/**
 * Rate Limiter for CampaignBridge REST API.
 *
 * Provides centralized, atomic rate limiting for REST API and admin-ajax
 * endpoints with configurable limits and fixed time windows.
 *
 * @package CampaignBridge\REST
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Client_Address;
use CampaignBridge\Repository\Rate_Limit_Repository;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter class.
 *
 * The single request limiter for CampaignBridge REST and admin-ajax
 * endpoints. Each check claims one request atomically in a fixed window, so
 * concurrent requests cannot exceed the limit, and it fails closed when the
 * counter cannot be established. Durable per-campaign delivery quotas and
 * idempotency belong to the campaign workflows, not to this limiter.
 */
class Rate_Limiter {
	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		return Client_Address::get();
	}

	/**
	 * Check rate limit for an endpoint.
	 *
	 * Authenticated requests are counted per user; anonymous requests per
	 * trusted client address.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param string $cache_prefix  Counter namespace.
	 * @param int    $max_requests  Maximum requests allowed per time window.
	 * @param int    $time_window   Time window in seconds.
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited or unavailable.
	 */
	public static function check_rate_limit(
		string $endpoint_name,
		string $cache_prefix = Rest_Constants::CACHE_KEY_PREFIX_GENERAL,
		int $max_requests = Rest_Constants::RATE_LIMIT_REQUESTS,
		int $time_window = Rest_Constants::RATE_LIMIT_WINDOW
	): bool|WP_Error {
		$user_id    = get_current_user_id();
		$identifier = $user_id ? 'user_' . $user_id : 'ip_' . self::get_client_ip();

		return self::claim( $cache_prefix . $endpoint_name . '_' . $identifier, $max_requests, $time_window );
	}

	/**
	 * Check rate limit requiring authentication.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param string $cache_prefix  Counter namespace.
	 * @param int    $max_requests  Maximum requests allowed per time window.
	 * @param int    $time_window   Time window in seconds.
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited, unavailable, or not authenticated.
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

		return self::claim( $cache_prefix . $endpoint_name . '_user_' . $user_id, $max_requests, $time_window );
	}

	/**
	 * Claim one request for a scope.
	 *
	 * @param string $scope        Counter scope.
	 * @param int    $max_requests Maximum requests per window.
	 * @param int    $time_window  Window length in seconds.
	 * @return bool|WP_Error True if admitted.
	 */
	private static function claim( string $scope, int $max_requests, int $time_window ): bool|WP_Error {
		$now     = time();
		$claimed = ( new Rate_Limit_Repository() )->claim( $scope, $max_requests, $time_window, $now );

		if ( null === $claimed ) {
			return new WP_Error(
				'rate_limit_unavailable',
				__( 'Request limits are temporarily unavailable. Try again shortly.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_SERVICE_UNAVAILABLE )
			);
		}

		if ( ! $claimed ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: number of seconds until reset */
					__( 'Rate limit exceeded. Try again in %d seconds.', 'campaignbridge' ),
					$time_window - ( $now % max( 1, $time_window ) )
				),
				array( 'status' => Rest_Constants::HTTP_TOO_MANY_REQUESTS )
			);
		}

		return true;
	}
}
