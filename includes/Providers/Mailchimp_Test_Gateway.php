<?php
/**
 * Mailchimp test-delivery adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Provider_Test_Gateway;
use CampaignBridge\Domain\Provider\Test_Delivery;
use CampaignBridge\Domain\Provider\Action_Outcome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends one Mailchimp test email of an existing campaign draft.
 *
 * Only the test action is called, so the campaign audience never receives
 * mail. The request is never retried: Mailchimp has no idempotency key for
 * tests, so a lost response may hide a delivered test and is reported as
 * ambiguous. Response bodies are never read.
 */
final class Mailchimp_Test_Gateway implements Provider_Test_Gateway {
	/** Seconds to wait for the test action before the outcome is unknown. */
	private const TIMEOUT = 20;

	/** Provider-neutral formats to Mailchimp `send_type` values. */
	private const SEND_TYPES = array(
		Test_Delivery::FORMAT_HTML => 'html',
		Test_Delivery::FORMAT_TEXT => 'plaintext',
	);

	/**
	 * Injected HTTP transport.
	 *
	 * @var Http_Client_Interface
	 */
	private readonly Http_Client_Interface $http;

	/**
	 * Build the mailchimp test gateway.
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
	public function send_test( array $settings, string $remote_id, Test_Delivery $delivery ): Action_Outcome {
		$api_key = $settings['api_key'] ?? null;
		if ( ! is_string( $api_key ) || ! ( new Mailchimp_Provider() )->is_valid_api_key( $api_key ) ) {
			return Action_Outcome::from_error( Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
		}

		$response = $this->http->post(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns/' . rawurlencode( $remote_id ) . '/actions/test' ),
			array(
				'headers'               => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'                  => (string) wp_json_encode(
					array(
						'test_emails' => $delivery->recipients(),
						'send_type'   => self::SEND_TYPES[ $delivery->format() ],
					)
				),
				'timeout'               => self::TIMEOUT,
				'campaignbridge_retry'  => false,
				'campaignbridge_origin' => Mailchimp_Provider::origin(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return Action_Outcome::from_error( Mailchimp_Errors::from_transport( $response ) );
		}
		$status = $response['status_code'] ?? 0;
		if ( 204 === $status || 200 === $status ) {
			return Action_Outcome::accepted();
		}

		return Action_Outcome::from_error( 400 === $status ? Mailchimp_Errors::request_rejected() : Mailchimp_Errors::from_status( is_int( $status ) ? $status : 0 ) );
	}
}
