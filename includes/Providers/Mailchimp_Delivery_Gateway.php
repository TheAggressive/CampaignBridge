<?php
/**
 * Mailchimp schedule and unschedule adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Provider_Delivery_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls Mailchimp's schedule, unschedule, and send campaign actions.
 *
 * Each action is posted once and never retried: Mailchimp has no idempotency
 * key for them, so a lost response is reported as ambiguous. Mailchimp only
 * schedules on the quarter-hour. Response bodies are never read. The
 * in-flight cancel action is never called.
 */
final class Mailchimp_Delivery_Gateway implements Provider_Delivery_Gateway {
	/** Seconds to wait for a delivery action before the outcome is unknown. */
	private const TIMEOUT = 20;

	/**
	 * Injected HTTP transport.
	 *
	 * @var Http_Client_Interface
	 */
	private readonly Http_Client_Interface $http;

	/**
	 * Build the mailchimp delivery gateway.
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
	public function schedule_interval_minutes(): int {
		return 15;
	}

	/**
	 * {@inheritDoc}
	 */
	public function schedule( array $settings, string $remote_id, string $scheduled_for ): Action_Outcome {
		$time = strtotime( $scheduled_for );
		if ( false === $time ) {
			return Action_Outcome::from_error( Mailchimp_Errors::request_rejected() );
		}

		return $this->action( $settings, $remote_id, 'schedule', array( 'schedule_time' => gmdate( 'Y-m-d\TH:i:sP', $time ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function unschedule( array $settings, string $remote_id ): Action_Outcome {
		return $this->action( $settings, $remote_id, 'unschedule', null );
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( array $settings, string $remote_id ): Action_Outcome {
		return $this->action( $settings, $remote_id, 'send', null );
	}

	/**
	 * Post one campaign action without retry.
	 *
	 * @param array<string, mixed>      $settings  Decrypted provider settings.
	 * @param string                    $remote_id The provider's campaign ID.
	 * @param string                    $action    Mailchimp campaign action name.
	 * @param array<string, mixed>|null $body      JSON body, or null for none.
	 */
	private function action( array $settings, string $remote_id, string $action, ?array $body ): Action_Outcome {
		$api_key = $settings['api_key'] ?? null;
		if ( ! is_string( $api_key ) || ! ( new Mailchimp_Provider() )->is_valid_api_key( $api_key ) ) {
			return Action_Outcome::from_error( Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
		}

		$request = array(
			'headers'               => array( 'Authorization' => 'Bearer ' . $api_key ),
			'timeout'               => self::TIMEOUT,
			'campaignbridge_retry'  => false,
			'campaignbridge_origin' => Mailchimp_Provider::origin(),
		);
		if ( null !== $body ) {
			$request['headers']['Content-Type'] = 'application/json';
			$request['body']                    = (string) wp_json_encode( $body );
		}

		$response = $this->http->post(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns/' . rawurlencode( $remote_id ) . '/actions/' . $action ),
			$request
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
