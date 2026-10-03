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
use CampaignBridge\Domain\Provider\Discovered_Audience;
use CampaignBridge\Domain\Provider\Discovery_Batch;

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
	private const ENDPOINT_PING = '/ping';

	/**
	 * Operations this adapter implements. Every other operation is unsupported.
	 *
	 * @var array<string, bool>
	 */
	public const CAPABILITIES = array(
		'verify_connection'          => true,
		'discover_audiences'         => true,
		'discover_merge_fields'      => true,
		'discover_segments'          => true,
		'discover_senders'           => true,
		'discover_template_sections' => false,
		'export'                     => false,
		'create_draft'               => true,
		'send_test'                  => true,
		'schedule'                   => false,
		'send'                       => false,
		'cancel'                     => false,
		'reconcile'                  => false,
		'reports'                    => false,
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'mailchimp', __( 'Mailchimp', 'campaignbridge' ) );

		$this->capabilities = self::CAPABILITIES;

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
			return Connection_Result::failure( Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
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
			return Connection_Result::failure( Mailchimp_Errors::from_transport( $response ) );
		}
		if ( 200 !== ( $response['status_code'] ?? 0 ) ) {
			return Connection_Result::failure( Mailchimp_Errors::from_status( (int) ( $response['status_code'] ?? 0 ) ) );
		}

		return Connection_Result::success(
			array(
				'provider' => $this->slug(),
				'verified' => true,
			)
		);
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
	 * A display adapter over {@see Mailchimp_Discovery::discover_audiences()},
	 * so there is one Mailchimp audience request and one normalization path.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 * @return array<string, string>|WP_Error Audience IDs keyed to display names.
	 */
	public function get_audiences( array $settings ): array|WP_Error {
		if ( ! $this->is_configured( $settings ) ) {
			return $this->create_error( 'mailchimp_invalid_credentials', __( 'The Mailchimp API key format is invalid.', 'campaignbridge' ), 400 );
		}

		$batch = ( new Mailchimp_Discovery() )->discover_audiences( $settings );
		if ( ! $batch instanceof Discovery_Batch ) {
			return $this->create_error( 'mailchimp_audiences_unavailable', $batch->message(), 503 );
		}

		$result = array();
		foreach ( $batch->items() as $audience ) {
			if ( $audience instanceof Discovered_Audience ) {
				$result[ $audience->id() ] = $audience->name();
			}
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
	public static function build_api_url( string $api_key, string $endpoint ): string {
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
			return new \WP_Error(
				'mailchimp_provider_error',
				__( 'An error occurred while communicating with Mailchimp.', 'campaignbridge' )
			);
		}

		if ( is_array( $error ) && isset( $error['title'], $error['detail'] ) ) {
			// Mailchimp API error format — do not expose raw provider detail.
			return new \WP_Error(
				'mailchimp_provider_error',
				__( 'Mailchimp returned an unexpected error.', 'campaignbridge' )
			);
		}

		return new \WP_Error(
			'mailchimp_provider_error',
			__( 'An error occurred while communicating with Mailchimp.', 'campaignbridge' )
		);
	}
}
