<?php
/**
 * Inbound provider webhook route.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Services\Webhook\Webhook_Factory;
use CampaignBridge\Workflow\Webhook\Webhook_Intake;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives provider webhook deliveries at `/webhooks/{provider}`.
 *
 * The route is public because providers cannot log in: each delivery is
 * authenticated by its signature, inside the callback, before anything is
 * read from it. Responses never say why a delivery was refused, which
 * campaign it named, or whether the campaign exists; accepted, duplicate,
 * and ignored deliveries all answer the same way. Nothing from a delivery
 * is stored or logged.
 *
 * `GET` answers 200 with no body, because Mailchimp checks the URL that way
 * before it creates a webhook.
 */
final class Provider_Webhook_Routes extends Abstract_Rest_Controller {
	/** Largest body accepted; a campaign event is a few hundred bytes. */
	public const MAX_BODY_BYTES = 65536;

	/** Requests per client address per window. */
	private const LIMIT  = 120;
	private const WINDOW = 60;

	/** Register the webhook route. */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			Webhook_Factory::ROUTE_BASE . '(?P<provider>' . implode( '|', Webhook_Factory::PROVIDERS ) . ')',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'validate_url' ),
					'permission_callback' => array( self::class, 'authenticated_by_signature' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'receive' ),
					'permission_callback' => array( self::class, 'authenticated_by_signature' ),
				),
			)
		);
	}

	/**
	 * Admit the request to its callback, which authenticates the signature.
	 *
	 * Providers cannot send WordPress credentials, so there is no user to
	 * check here; the callback refuses any delivery that is not signed with a
	 * stored secret.
	 */
	public static function authenticated_by_signature(): bool {
		return true;
	}

	/**
	 * Answer the provider's URL check.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function validate_url( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->limited( (string) $request['provider'] );

		return $limited ?? new WP_REST_Response( null, 200 );
	}

	/**
	 * Accept, ignore, or refuse one delivery.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function receive( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$provider = (string) $request['provider'];
		$limited  = $this->limited( $provider );
		if ( null !== $limited ) {
			return $limited;
		}
		$body = $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return self::refusal( 'campaignbridge_webhook_too_large', 413 );
		}
		$intake = Webhook_Factory::intake( $provider );
		if ( null === $intake ) {
			return self::refusal( 'campaignbridge_webhook_refused', 401 );
		}

		return match ( $intake->receive( $body, self::headers( $request ) ) ) {
			Webhook_Intake::ACCEPTED, Webhook_Intake::DUPLICATE, Webhook_Intake::IGNORED => new WP_REST_Response( array( 'received' => true ), 200 ),
			Webhook_Intake::MALFORMED => self::refusal( 'campaignbridge_webhook_malformed', 400 ),
			Webhook_Intake::UNAVAILABLE => self::refusal( 'campaignbridge_webhook_unavailable', 503 ),
			default => self::refusal( 'campaignbridge_webhook_refused', 401 ),
		};
	}

	/**
	 * A rate-limit refusal, or null when the request is admitted.
	 *
	 * @param string $provider Provider slug.
	 */
	private function limited( string $provider ): ?WP_Error {
		$admitted = Rate_Limiter::check_rate_limit( 'webhook_' . $provider, Rest_Constants::CACHE_KEY_PREFIX_GENERAL, self::LIMIT, self::WINDOW );

		return true === $admitted ? null : self::refusal( 'campaignbridge_webhook_limited', $admitted instanceof WP_Error && 503 === ( $admitted->get_error_data()['status'] ?? null ) ? 503 : 429 );
	}

	/**
	 * Request headers, first value each, as WordPress names them.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return array<string, string>
	 */
	private static function headers( WP_REST_Request $request ): array {
		$headers = array();
		foreach ( $request->get_headers() as $name => $values ) {
			$value = is_array( $values ) ? ( $values[0] ?? null ) : $values;
			if ( is_string( $name ) && is_string( $value ) ) {
				$headers[ $name ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * A refusal that says nothing about the delivery.
	 *
	 * @param string $code   Stable error code.
	 * @param int    $status HTTP status.
	 */
	private static function refusal( string $code, int $status ): WP_Error {
		return new WP_Error( $code, __( 'The webhook delivery was not accepted.', 'campaignbridge' ), array( 'status' => $status ) );
	}
}
