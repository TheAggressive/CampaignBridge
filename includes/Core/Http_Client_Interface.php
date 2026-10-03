<?php
/**
 * HTTP Client Interface for dependency injection and testing.
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
 * HTTP Client Interface for dependency injection and testing.
 */
interface Http_Client_Interface {
	/**
	 * Make a POST request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function post( string $url, array $args = array() );

	/**
	 * Make a GET request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function get( string $url, array $args = array() );

	/**
	 * Make a PUT request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function put( string $url, array $args = array() );

	/**
	 * Make a PATCH request.
	 *
	 * PATCH fails closed like POST: it is retried only when the caller opts in
	 * with `campaignbridge_retry` because the exact update is idempotent.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function patch( string $url, array $args = array() );

	/**
	 * Make a DELETE request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function delete( string $url, array $args = array() );
}
