<?php
/**
 * HTTP Client Instance class.
 *
 * Provides an instance-based HTTP client that implements Http_Client_Interface
 * for dependency injection and testing. Internally delegates to static Http_Client methods.
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
 * HTTP Client Instance class.
 *
 * Provides an instance-based HTTP client that implements Http_Client_Interface
 * for dependency injection and testing. Internally delegates to static Http_Client methods.
 */
class Http_Client_Instance implements Http_Client_Interface {
	/**
	 * Make a POST request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function post( string $url, array $args = array() ) {
		return Http_Client::post( $url, $args );
	}

	/**
	 * Make a GET request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function get( string $url, array $args = array() ) {
		return Http_Client::get( $url, $args );
	}

	/**
	 * Make a PUT request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function put( string $url, array $args = array() ) {
		return Http_Client::put( $url, $args );
	}

	/**
	 * Make a DELETE request.
	 *
	 * @param string               $url     The URL to request.
	 * @param array<string, mixed> $args    Request arguments.
	 * @return array<string, mixed>|\WP_Error Response data or WP_Error on failure.
	 */
	public function delete( string $url, array $args = array() ) {
		return Http_Client::delete( $url, $args );
	}
}
