<?php
/**
 * Mailchimp Provider Implementation for CampaignBridge.
 *
 * Provides credential verification and audience discovery through the
 * Mailchimp API.
 *
 * @package CampaignBridge
 * @since 0.2.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Domain\Campaign\Connection_Result;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp email service provider implementation.
 *
 * Implements the Provider_Interface port for Mailchimp.
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
	 * Mailchimp API key pattern
	 */
	private const API_KEY_PATTERN = '/^[a-f0-9]{32}-us[0-9]+$/';

	/**
	 * Constructor.
	 *
	 * @param Http_Client_Interface $http_client HTTP client for API calls.
	 */
	public function __construct(
		Http_Client_Interface $http_client = new Http_Client_Instance()
	) {
		parent::__construct( 'mailchimp', __( 'Mailchimp', 'campaignbridge' ), $http_client );
	}

	/**
	 * Check if provider is properly configured.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool
	 */
	public function is_configured( array $settings ): bool {
		$api_key = $settings['api_key'] ?? null;
		if ( ! is_string( $api_key ) ) {
			return false;
		}

		return 1 === preg_match( self::API_KEY_PATTERN, $api_key );
	}

	/**
	 * Verify credentials using Mailchimp's read-only ping endpoint.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 * @return Connection_Result Domain-typed result wrapping success or a Provider_Error.
	 */
	public function verify_connection( array $settings ): Connection_Result {
		if ( ! $this->is_configured( $settings ) ) {
			return Connection_Result::failure(
				Provider_Error::authentication(
					'mailchimp_invalid_credentials',
					__( 'The Mailchimp API key format is invalid.', 'campaignbridge' ),
					$this->slug()
				)
			);
		}

		$api_key  = (string) $settings['api_key'];
		$response = $this->http_client->get(
			self::build_api_url( $api_key, self::ENDPOINT_PING ),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
			)
		);

		if ( $response instanceof WP_Error ) {
			return Connection_Result::failure(
				Provider_Error::from_category(
					Provider_Error_Category::NETWORK,
					'mailchimp_connection_unavailable',
					__( 'Mailchimp could not be reached.', 'campaignbridge' ),
					$this->slug()
				)
			);
		}

		if ( 200 !== (int) ( $response['status_code'] ?? 0 ) ) {
			return Connection_Result::failure(
				Provider_Error::authentication(
					'mailchimp_connection_rejected',
					__( 'Mailchimp rejected the stored credentials.', 'campaignbridge' ),
					$this->slug()
				)
			);
		}

		return Connection_Result::success();
	}

	/**
	 * Fetch the Mailchimp audiences (lists) for the admin UI.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 * @return array<string, string>|WP_Error Normalized id => label map, or WP_Error.
	 */
	public function get_audiences( array $settings ): array|WP_Error {
		if ( ! $this->is_configured( $settings ) ) {
			return new WP_Error( 'mailchimp_invalid_credentials', __( 'The Mailchimp API key format is invalid.', 'campaignbridge' ) );
		}

		$api_key  = (string) $settings['api_key'];
		$response = $this->http_client->get(
			self::build_api_url( $api_key, self::ENDPOINT_AUDIENCES ),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
			)
		);

		if ( $response instanceof WP_Error ) {
			return $response;
		}

		if ( 200 !== (int) ( $response['status_code'] ?? 0 ) ) {
			return new WP_Error( 'mailchimp_audiences_failed', __( 'Could not load Mailchimp audiences.', 'campaignbridge' ) );
		}

		$body = $response['body'] ?? '';
		$data = is_string( $body ) ? json_decode( $body, true ) : (array) $body;

		$audiences = array();
		foreach ( (array) ( $data['lists'] ?? array() ) as $list ) {
			$list_id = $list['id'] ?? null;
			$name    = $list['name'] ?? null;
			if ( is_string( $list_id ) && is_string( $name ) && '' !== $name ) {
				$audiences[ $list_id ] = $name;
			}
		}

		return $audiences;
	}

	/**
	 * Build a fully-qualified Mailchimp API URL.
	 *
	 * @param string $api_key  The API key (contains the data-center suffix).
	 * @param string $endpoint The API endpoint path.
	 * @return string
	 */
	private static function build_api_url( string $api_key, string $endpoint ): string {
		$dc = substr( $api_key, strrpos( $api_key, '-' ) + 1 );
		return sprintf( self::API_BASE_URL_FORMAT, $dc ) . $endpoint;
	}
}
