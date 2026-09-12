<?php
/**
 * Mailchimp Provider Implementation for CampaignBridge.
 *
 * Provides credential verification and template-section discovery through the
 * Mailchimp API.
 *
 * @package CampaignBridge
 * @since 0.2.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Encryption;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CampaignBridge\Domain\Campaign\Connection_Result;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;

/**
 * Mailchimp email service provider implementation.
 *
 * Implements only the operations CampaignBridge can call today.
 */
class Mailchimp_Provider extends Abstract_Provider {
	/**
	 * Mailchimp API base URL
	 */
	private const API_BASE_URL_FORMAT = 'https://%s.api.mailchimp.com/3.0';

	/**
	 * API endpoints
	 */
	private const ENDPOINT_PING      = '/ping';
	private const ENDPOINT_AUDIENCES = '/lists?count=1000&fields=lists.id,lists.name,total_items';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'mailchimp', __( 'Mailchimp', 'campaignbridge' ) );

		// Configure Mailchimp-specific capabilities.
		$this->capabilities = array(
			'verify_connection'          => true,
			'discover_template_sections' => true,
			'discover_audiences'         => true,
			'create_draft'               => false,
			'send_test'                  => false,
			'schedule'                   => false,
			'send'                       => false,
			'reconcile'                  => false,
			'reports'                    => false,
		);

		// Mailchimp API key pattern.
		$this->api_key_pattern = '/^[a-f0-9]{32}-us[0-9]+$/';
	}

	/**
	 * Check if provider is properly configured.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool
	 */
	public function is_configured( array $settings ): bool {
		$required_fields = array( 'api_key' );
		if ( ! $this->validate_required_settings( $settings, $required_fields ) ) {
			return false;
		}

		// Also validate API key format.
		return $this->is_valid_api_key( $settings['api_key'] );
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if valid format.
	 */
	public function is_valid_api_key( string $api_key ): bool {
		return preg_match( $this->api_key_pattern, $api_key ) === 1;
	}

	/**
	 * Verify credentials using Mailchimp's read-only ping endpoint.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 * @return Connection_Result Normalized verification outcome.
	 */
	public function verify_connection( array $settings ): Connection_Result {
		if ( ! $this->is_configured( $settings ) ) {
			return Connection_Result::failure(
				$this->build_error( Provider_Error_Category::VALIDATION, 'The Mailchimp API key format is invalid.' )
			);
		}

		$api_key  = (string) $settings['api_key'];
		$response = \CampaignBridge\Core\Http_Client::get(
			self::build_api_url( $api_key, self::ENDPOINT_PING ),
			array(
				'headers'              => array( 'Authorization' => 'Bearer ' . $api_key ),
				'campaignbridge_retry' => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			$category = $this->categorize_http_error( $response );
			return Connection_Result::failure(
				$this->build_error( $category, 'Mailchimp could not be reached.', $response->get_error_message() )
			);
		}
		if ( 200 !== ( $response['status_code'] ?? 0 ) ) {
			$status   = (int) ( $response['status_code'] ?? 0 );
			$category = $this->categorize_http_status( $status );
			return Connection_Result::failure(
				$this->build_error( $category, 'Mailchimp rejected the stored credentials.', '', $status )
			);
		}

		return Connection_Result::success(
			array(
				'provider' => $this->slug(),
				'verified' => true,
			)
		);
	}

	/**
	 * Map a WP_Error from an HTTP request to a provider error category.
	 *
	 * @param \WP_Error $error The HTTP error.
	 * @return string
	 */
	private function categorize_http_error( \WP_Error $error ): string {
		$code    = $error->get_error_code();
		$message = strtolower( $error->get_error_message() );

		if ( in_array( $code, array( 'http_request_failed', 'connect_timeout', 'timeout' ), true ) ) {
			return Provider_Error_Category::NETWORK;
		}
		if ( str_contains( $message, 'timed out' ) || str_contains( $message, 'timeout' ) ) {
			return Provider_Error_Category::TIMEOUT;
		}
		if ( str_contains( $message, 'ssl' ) || str_contains( $message, 'certificate' ) ) {
			return Provider_Error_Category::NETWORK;
		}
		return Provider_Error_Category::PROVIDER_ERROR;
	}

	/**
	 * Map an HTTP status code to a provider error category.
	 *
	 * @param int $status HTTP status code.
	 * @return string
	 */
	private function categorize_http_status( int $status ): string {
		switch ( true ) {
			case 400 === $status || 401 === $status || 403 === $status:
				return Provider_Error_Category::AUTHENTICATION;
			case 429 === $status:
				return Provider_Error_Category::RATE_LIMITED;
			case $status >= 500:
				return Provider_Error_Category::PROVIDER_ERROR;
			default:
				return Provider_Error_Category::PROVIDER_ERROR;
		}
	}

	/**
	 * Get settings schema for validation.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			'api_key' => array(
				'sensitive'  => true,
				'required'   => true,
				'pattern'    => $this->api_key_pattern,
				'min_length' => 32,
				'max_length' => 50,
			),
		);
	}

	/**
	 * Get the audiences available to the configured Mailchimp account.
	 *
	 * Provider response details are normalized here so they do not leak into
	 * the admin UI.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 * @return array<string, string>|WP_Error Audience IDs keyed to display names.
	 */
	public function get_audiences( array $settings ): array|WP_Error {
		if ( ! $this->is_configured( $settings ) ) {
			return $this->create_error( 'mailchimp_invalid_credentials', __( 'The Mailchimp API key format is invalid.', 'campaignbridge' ), 400 );
		}

		$api_key  = (string) $settings['api_key'];
		$response = \CampaignBridge\Core\Http_Client::get(
			self::build_api_url( $api_key, self::ENDPOINT_AUDIENCES ),
			array(
				'headers'              => array( 'Authorization' => 'Bearer ' . $api_key ),
				'campaignbridge_retry' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->create_error( 'mailchimp_audiences_unavailable', __( 'Mailchimp audiences could not be loaded.', 'campaignbridge' ), 503 );
		}

		$status_code = $response['status_code'] ?? 0;
		if ( ! is_int( $status_code ) || $status_code < 200 || $status_code >= 300 ) {
			return $this->create_error( 'mailchimp_audiences_error', __( 'Mailchimp audiences could not be loaded.', 'campaignbridge' ), is_int( $status_code ) ? $status_code : 500 );
		}

		$decoded = json_decode( (string) ( $response['body'] ?? '' ), true );
		$lists   = is_array( $decoded ) && isset( $decoded['lists'] ) && is_array( $decoded['lists'] ) ? $decoded['lists'] : array();
		$result  = array();
		foreach ( $lists as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['id'], $list['name'] ) || ! is_string( $list['id'] ) || ! is_string( $list['name'] ) ) {
				continue;
			}
			$result[ $list['id'] ] = $list['name'];
		}

		return $result;
	}


	/**
	 * Build a Mailchimp API URL from the data center encoded in the API key.
	 *
	 * @param string $api_key  Mailchimp API key.
	 * @param string $endpoint API endpoint beginning with a slash.
	 * @return string Fully qualified API URL.
	 * @throws \InvalidArgumentException When the key has no valid data center.
	 */
	private static function build_api_url( string $api_key, string $endpoint ): string {
		if ( 1 !== preg_match( '/-([a-z]{2}[0-9]+)$/', $api_key, $matches ) ) {
			throw new \InvalidArgumentException( 'Mailchimp API key does not contain a valid data center.' );
		}

		return sprintf( self::API_BASE_URL_FORMAT, $matches[1] ) . '/' . ltrim( $endpoint, '/' );
	}

	/**
	 * Handle API errors gracefully.
	 *
	 * @param mixed $error API error response.
	 * @return \WP_Error Processed error.
	 */
	public function handle_api_error( $error ): \WP_Error {
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		if ( is_array( $error ) && isset( $error['title'], $error['detail'] ) ) {
			// Mailchimp API error format.
			return new \WP_Error(
				'mailchimp_api_error',
				sprintf(
					/* translators: 1: error title, 2: error detail */
					__( 'Mailchimp API Error: %1$s - %2$s', 'campaignbridge' ),
					$error['title'],
					$error['detail']
				)
			);
		}

		// Generic error handling.
		return new \WP_Error(
			'mailchimp_api_error',
			__( 'An error occurred while communicating with Mailchimp.', 'campaignbridge' )
		);
	}
}
