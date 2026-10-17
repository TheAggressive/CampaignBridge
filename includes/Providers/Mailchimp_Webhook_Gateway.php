<?php
/**
 * Mailchimp webhook registration adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Domain\Provider\Provider_Retry_Policy;
use CampaignBridge\Domain\Provider\Provider_Webhook_Gateway;
use CampaignBridge\Domain\Webhook\Webhook_Created;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, finds, and removes Mailchimp audience webhooks for campaign events.
 *
 * Mailchimp signs every delivery of a webhook created through the API and
 * returns the signing secret only in the create response. A created webhook
 * without a secret could not be authenticated, so it is removed again and
 * reported as a failure. Before creating, Mailchimp checks the URL with a
 * GET request, so the site must be reachable from the internet.
 */
final class Mailchimp_Webhook_Gateway implements Provider_Webhook_Gateway {
	/** Seconds to wait for a webhook request. */
	private const TIMEOUT = 20;

	/** The only events requested: subscriber events carry personal data CampaignBridge never needs. */
	private const EVENTS = array(
		'campaign'    => true,
		'subscribe'   => false,
		'unsubscribe' => false,
		'profile'     => false,
		'cleaned'     => false,
		'upemail'     => false,
	);

	/** A campaign sent from the Mailchimp app, by an admin, or through the API all matter. */
	private const SOURCES = array(
		'user'  => true,
		'admin' => true,
		'api'   => true,
	);

	/**
	 * Injected HTTP transport.
	 *
	 * @var Http_Client_Interface
	 */
	private readonly Http_Client_Interface $http;

	/**
	 * Build the mailchimp webhook gateway.
	 *
	 * @param Http_Client_Interface|null $http HTTP client.
	 */
	public function __construct( ?Http_Client_Interface $http = null ) {
		$this->http = $http ?? new Http_Client_Instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mailchimp';
	}

	/**
	 * {@inheritDoc}
	 */
	public function find( array $settings, string $audience_id, string $url ): array|Provider_Error {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION );
		}

		$response = $this->http->get(
			Mailchimp_Provider::build_api_url( $api_key, '/lists/' . rawurlencode( $audience_id ) . '/webhooks?fields=webhooks.id,webhooks.url' ),
			$this->args( $api_key, Provider_Retry_Policy::WEBHOOK_LOOKUP )
		);
		if ( is_wp_error( $response ) ) {
			return Mailchimp_Errors::from_transport( $response );
		}
		if ( 200 !== ( $response['status_code'] ?? 0 ) ) {
			return Mailchimp_Errors::from_response( $response );
		}
		$data = json_decode( (string) ( $response['body'] ?? '' ), true );
		if ( ! is_array( $data ) || ! is_array( $data['webhooks'] ?? null ) ) {
			return Mailchimp_Errors::unexpected_response();
		}

		$found = array();
		foreach ( $data['webhooks'] as $webhook ) {
			if ( is_array( $webhook ) && is_string( $webhook['id'] ?? null ) && '' !== $webhook['id'] && ( $webhook['url'] ?? null ) === $url ) {
				$found[] = $webhook['id'];
			}
		}

		return $found;
	}

	/**
	 * {@inheritDoc}
	 */
	public function create( array $settings, string $audience_id, string $url ): Webhook_Created|Provider_Error {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION );
		}

		$args         = $this->args( $api_key, Provider_Operation::WEBHOOKS );
		$args['body'] = (string) wp_json_encode(
			array(
				'url'     => $url,
				'events'  => self::EVENTS,
				'sources' => self::SOURCES,
			)
		);
		$response     = $this->http->post( Mailchimp_Provider::build_api_url( $api_key, '/lists/' . rawurlencode( $audience_id ) . '/webhooks' ), $args );
		if ( is_wp_error( $response ) ) {
			return Mailchimp_Errors::from_transport( $response );
		}
		$status = $response['status_code'] ?? 0;
		if ( 400 === $status ) {
			// Mailchimp refuses a URL it cannot reach or that it already holds for the audience.
			return self::url_rejected();
		}
		if ( 200 !== $status ) {
			return Mailchimp_Errors::from_response( $response );
		}

		$data = json_decode( (string) ( $response['body'] ?? '' ), true );
		$id   = is_array( $data ) ? ( $data['id'] ?? null ) : null;
		if ( ! is_string( $id ) || '' === $id ) {
			return Mailchimp_Errors::unexpected_response();
		}
		$secret = $data['signing_secret'] ?? null;
		if ( true !== ( $data['signing_enabled'] ?? null ) || ! is_string( $secret ) || '' === $secret ) {
			// An unsigned webhook cannot be authenticated; take it back down.
			$this->remove( $settings, $audience_id, $id );

			return Mailchimp_Errors::unexpected_response();
		}

		return new Webhook_Created( $id, $secret );
	}

	/**
	 * {@inheritDoc}
	 */
	public function remove( array $settings, string $audience_id, string $remote_id ): ?Provider_Error {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION );
		}

		$response = $this->http->delete( // phpcs:ignore CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.InvalidWpdbUsage -- HTTP DELETE request, not $wpdb.
			Mailchimp_Provider::build_api_url( $api_key, '/lists/' . rawurlencode( $audience_id ) . '/webhooks/' . rawurlencode( $remote_id ) ),
			$this->args( $api_key, Provider_Retry_Policy::WEBHOOK_LOOKUP )
		);
		if ( is_wp_error( $response ) ) {
			return Mailchimp_Errors::from_transport( $response );
		}
		$status = $response['status_code'] ?? 0;

		return 204 === $status || 200 === $status || 404 === $status ? null : Mailchimp_Errors::from_response( $response );
	}

	/**
	 * The normalized error for a webhook URL Mailchimp refused.
	 */
	public static function url_rejected(): Provider_Error {
		return Provider_Error::from_category(
			Provider_Error_Category::VALIDATION,
			'mailchimp_webhook_url_rejected',
			__( 'Mailchimp could not reach this site\'s webhook address. The site must be reachable from the internet over HTTPS.', 'campaignbridge' ),
			'mailchimp'
		);
	}

	/**
	 * The validated API key, or null.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	private function api_key( array $settings ): ?string {
		$api_key = $settings['api_key'] ?? null;

		return is_string( $api_key ) && ( new Mailchimp_Provider() )->is_valid_api_key( $api_key ) ? $api_key : null;
	}

	/**
	 * Request arguments for one webhook request.
	 *
	 * @param string $api_key   Mailchimp API key.
	 * @param string $operation Retry-policy operation.
	 * @return array<string, mixed>
	 */
	private function args( #[\SensitiveParameter] string $api_key, string $operation ): array {
		return array(
			'headers'               => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout'               => self::TIMEOUT,
			'campaignbridge_retry'  => Provider_Retry_Policy::may_replay( $operation ),
			'campaignbridge_origin' => Mailchimp_Provider::origin(),
		);
	}
}
