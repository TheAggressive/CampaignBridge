<?php
/**
 * HTTP Client Wrapper for CampaignBridge.
 *
 * Provides consistent HTTP request handling with proper error handling,
 * logging, and retry logic for all external API communications.
 *
 * @package CampaignBridge\Core
 * @since 0.3.3
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP Client wrapper class.
 *
 * Provides consistent HTTP request handling with error handling and logging.
 */
class Http_Client {
	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 30;

	/**
	 * Maximum number of retries for failed requests.
	 */
	private const MAX_RETRIES = 2;

	/**
	 * Maximum retry delay in seconds (exponential backoff).
	 */
	private const MAX_RETRY_DELAY = 5;

	/**
	 * Metrics storage for performance tracking.
	 *
	 * @var array<string, mixed>
	 */
	private static array $metrics = array(
		'requests_total'      => 0,
		'requests_success'    => 0,
		'requests_error'      => 0,
		'requests_retry'      => 0,
		'total_response_time' => 0.0,
	);

	/**
	 * Make a POST request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public static function post( string $url, array $args = array() ): array|\WP_Error {
		return self::request( 'post', $url, $args );
	}

	/**
	 * Make a POST request with JSON body (explicit JSON mode).
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $data    Data to send as JSON.
	 * @param array<string, mixed> $args    Additional request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public static function post_json( string $url, array $data, array $args = array() ): array|\WP_Error {
		$args['body']                    = wp_json_encode( $data );
		$args['headers']['Content-Type'] = 'application/json';

		return self::request( 'post', $url, $args );
	}

	/**
	 * Make a GET request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public static function get( string $url, array $args = array() ): array|\WP_Error {
		return self::request( 'get', $url, $args );
	}

	/**
	 * Make a PUT request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public static function put( string $url, array $args = array() ): array|\WP_Error {
		return self::request( 'put', $url, $args );
	}

	/**
	 * Make a DELETE request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public static function delete( string $url, array $args = array() ): array|\WP_Error {
		return self::request( 'delete', $url, $args );
	}

	/**
	 * Create an instance of the HTTP client for dependency injection.
	 *
	 * @return Http_Client_Instance An instance that implements Http_Client_Interface.
	 */
	public static function create_instance(): Http_Client_Instance {
		return new Http_Client_Instance();
	}

	/**
	 * Make an HTTP request with retry logic and error handling.
	 *
	 * @param string               $method The HTTP method.
	 * @param string               $url    The URL to request.
	 * @param array<string, mixed> $args   Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	private static function request( string $method, string $url, array $args = array() ): array|\WP_Error {
		$start_time = microtime( true );
		++self::$metrics['requests_total'];

		$args = wp_parse_args(
			$args,
			array(
				'timeout' => self::DEFAULT_TIMEOUT,
				'headers' => array(),
			)
		);

		// Handle body encoding based on Content-Type or explicit JSON mode.
		if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$content_type = $args['headers']['Content-Type'] ?? '';

			// Auto-encode arrays as JSON only if no Content-Type is set.
			if ( empty( $content_type ) ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $args['body'] );
			} elseif ( 'application/json' === $content_type ) {
				$args['body'] = wp_json_encode( $args['body'] );
			}
		}

		$attempts   = 0;
		$last_error = null;

		while ( $attempts <= self::MAX_RETRIES ) {
			++$attempts;

			try {
				$function = "wp_remote_{$method}";
				if ( ! function_exists( $function ) ) {
					return new \WP_Error( 'invalid_method', 'Unsupported HTTP method: ' . $method );
				}

				$response = $function( $url, $args );

				if ( is_wp_error( $response ) ) {
					$last_error = $response;
					if ( $attempts <= self::MAX_RETRIES && self::is_retryable_error( $response ) ) {
						++self::$metrics['requests_retry'];
						self::exponential_backoff_delay( $attempts );
						self::log_retry( $method, $url, $attempts, $response );
						continue;
					}
					break;
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				if ( $status_code >= 500 && $attempts <= self::MAX_RETRIES ) {
					// Retry on server errors.
					++self::$metrics['requests_retry'];
					self::exponential_backoff_delay( $attempts );
					self::log_retry( $method, $url, $attempts, "HTTP {$status_code}" );
					continue;
				}

				// Success - update metrics and return parsed response.
				$end_time = microtime( true );
				++self::$metrics['requests_success'];
				self::$metrics['total_response_time'] += ( $end_time - $start_time );

				return array(
					'body'        => wp_remote_retrieve_body( $response ),
					'headers'     => wp_remote_retrieve_headers( $response ),
					'status_code' => $status_code,
					'response'    => $response,
				);

			} catch ( \Throwable $e ) {
				$last_error = new \WP_Error( 'http_exception', 'HTTP request failed' ); // Don't expose internal error details.
				if ( $attempts <= self::MAX_RETRIES ) {
					self::exponential_backoff_delay( $attempts );
					self::log_retry( $method, $url, $attempts, 'Exception occurred' ); // Log without exposing details.
					continue;
				}
			}
		}

		// Update error metrics.
		++self::$metrics['requests_error'];

		// Log final failure.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Error_Handler::error(
				'HTTP request failed after retries',
				array(
					'method'   => $method,
					'url'      => $url,
					'attempts' => $attempts,
					'error'    => $last_error ? $last_error->get_error_message() : 'Unknown error',
				)
			);
		}

		return $last_error ? $last_error : new \WP_Error( 'http_request_failed', 'HTTP request failed' );
	}

	/**
	 * Check if an error is retryable.
	 *
	 * @param \WP_Error $error The error to check.
	 * @return bool True if the error is retryable.
	 */
	private static function is_retryable_error( \WP_Error $error ): bool {
		$error_codes = array( 'http_request_failed', 'connect_timeout', 'read_timeout' );
		return in_array( $error->get_error_code(), $error_codes, true );
	}

	/**
	 * Implement exponential backoff delay for retries.
	 *
	 * @param int $attempt The current attempt number.
	 * @return void
	 */
	private static function exponential_backoff_delay( int $attempt ): void {
		// Calculate delay: base delay * 2^(attempt-1), capped at MAX_RETRY_DELAY.
		$base_delay = 0.5; // Start with 500ms.
		$delay      = min( $base_delay * pow( 2, $attempt - 1 ), self::MAX_RETRY_DELAY );

		// Add jitter to prevent thundering herd.
		$jitter = $delay * 0.1 * ( wp_rand( 0, 20 ) - 10 ) / 10; // ±10% jitter.
		$delay  = max( 0.1, $delay + $jitter ); // Minimum 100ms delay.

		usleep( (int) ( $delay * 1000000 ) ); // Convert to microseconds.
	}

	/**
	 * Log retry attempts.
	 *
	 * @param string           $method   The HTTP method.
	 * @param string           $url      The URL.
	 * @param int              $attempt  The attempt number.
	 * @param string|\WP_Error $reason  The reason for retry.
	 * @return void
	 */
	private static function log_retry( string $method, string $url, int $attempt, $reason ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$reason_msg = is_string( $reason ) ? $reason : $reason->get_error_message();
			Error_Handler::info(
				'HTTP request retry',
				array(
					'method'  => $method,
					'url'     => $url,
					'attempt' => $attempt,
					'reason'  => $reason_msg,
				)
			);
		}
	}

	/**
	 * Get HTTP client metrics (for monitoring/debugging).
	 *
	 * SECURITY: Only returns metrics when WP_DEBUG is enabled to prevent information leakage.
	 *
	 * @return array<string, mixed> Metrics data or empty array if debug disabled.
	 */
	public static function get_metrics(): array {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return array(); // Don't expose metrics in production.
		}

		$metrics = self::$metrics;

		// Calculate derived metrics.
		if ( $metrics['requests_total'] > 0 ) {
			$metrics['success_rate']          = round( ( $metrics['requests_success'] / $metrics['requests_total'] ) * 100, 2 );
			$metrics['average_response_time'] = round( $metrics['total_response_time'] / $metrics['requests_total'], 4 );
		}

		return $metrics;
	}

	/**
	 * Reset metrics (for testing or manual reset).
	 *
	 * SECURITY: Only allows reset when WP_DEBUG is enabled.
	 *
	 * @return bool True if reset was allowed, false otherwise.
	 */
	public static function reset_metrics(): bool {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		self::$metrics = array(
			'requests_total'      => 0,
			'requests_success'    => 0,
			'requests_error'      => 0,
			'requests_retry'      => 0,
			'total_response_time' => 0.0,
		);

		return true;
	}
}
