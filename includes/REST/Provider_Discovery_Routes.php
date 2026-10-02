<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Provider capability and discovery REST adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Discovered_Item;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Workflow\Provider\Discovery_Lookup;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes provider capabilities and discovered references.
 *
 * GET routes read the cache only and never contact the provider. The
 * rate-limited refresh route is the only path that makes a remote call.
 * Credentials are decrypted server-side for that one call and never returned.
 */
final class Provider_Discovery_Routes extends Abstract_Rest_Controller {
	private const PROVIDER = '/providers/(?P<provider>[a-z0-9][a-z0-9_-]{0,63})';
	private const KIND     = '/discovery/(?P<kind>audiences|merge_fields|segments)';

	/** Per-user remote refreshes per window. */
	private const REFRESH_LIMIT = 10;

	public function register(): void {
		$provider = array(
			'provider' => array(
				'type'     => 'string',
				'required' => true,
				'pattern'  => '^[a-z0-9][a-z0-9_-]{0,63}$',
			),
		);
		$lookup   = array_merge(
			$provider,
			array(
				'kind'     => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => Discovery_Kind::all(),
				),
				'audience' => array(
					'description' => __( 'Audience ID for audience-scoped kinds. Omit for audiences.', 'campaignbridge' ),
					'type'        => 'string',
					'pattern'     => '^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$',
				),
			)
		);

		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::PROVIDER . '/capabilities',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_capabilities' ),
					'permission_callback' => array( __CLASS__, 'can_discover' ),
					'args'                => $provider,
				),
			)
		);
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::PROVIDER . self::KIND,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cached' ),
					'permission_callback' => array( __CLASS__, 'can_discover' ),
					'args'                => $lookup,
				),
			)
		);
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::PROVIDER . self::KIND . '/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refresh' ),
					'permission_callback' => array( __CLASS__, 'can_discover' ),
					'args'                => $lookup,
				),
			)
		);
	}

	/** Campaign authors choose targeting; managers and connection owners administer it. */
	public static function can_discover(): bool {
		return 0 < get_current_user_id()
			&& (
				current_user_can( Capabilities::CREATE_CAMPAIGNS )
				|| current_user_can( Capabilities::MANAGE )
				|| current_user_can( Capabilities::MANAGE_CONNECTIONS )
			);
	}

	public function get_capabilities( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = $this->service( $request );
		if ( $service instanceof WP_Error ) {
			return $service;
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'provider'   => (string) $request->get_param( 'provider' ),
					'operations' => $service->capabilities()->to_array(),
				)
			)
		);
	}

	public function get_cached( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->lookup( $request, false );
	}

	public function refresh( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = Rate_Limiter::check_rate_limit_authenticated(
			'provider_discovery_refresh',
			Rest_Constants::CACHE_KEY_PREFIX_GENERAL,
			self::REFRESH_LIMIT,
			Rest_Constants::RATE_LIMIT_WINDOW
		);
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		return $this->lookup( $request, true );
	}

	private function lookup( WP_REST_Request $request, bool $refresh ): WP_REST_Response|WP_Error {
		$service = $this->service( $request );
		if ( $service instanceof WP_Error ) {
			return $service;
		}
		$provider = (string) $request->get_param( 'provider' );
		$kind     = (string) $request->get_param( 'kind' );
		$scope    = $request->has_param( 'audience' ) ? (string) $request->get_param( 'audience' ) : '';
		if ( Discovery_Kind::is_audience_scoped( $kind ) === ( '' === $scope ) ) {
			return new WP_Error(
				'campaignbridge_discovery_invalid_scope',
				Discovery_Kind::AUDIENCES === $kind
					? __( 'Audiences are account-wide; omit the audience parameter.', 'campaignbridge' )
					: __( 'This discovery kind requires an audience parameter.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$settings = Provider_Discovery_Factory::settings( $provider );
		$result   = $refresh ? $service->refresh( $kind, $settings, $scope ) : $service->cached( $kind, $settings, $scope );
		unset( $settings );

		$error = $result->error();
		if ( null !== $error && null === $result->result() ) {
			return new WP_Error(
				$error->code(),
				$error->message(),
				array(
					'status'    => self::status( $error ),
					'category'  => $error->category(),
					'retryable' => $error->is_retryable(),
				)
			);
		}

		return $this->no_store( new WP_REST_Response( self::representation( $provider, $kind, $scope, $result ) ) );
	}

	/** @return array<string, mixed> */
	private static function representation( string $provider, string $kind, string $scope, Discovery_Lookup $lookup ): array {
		$result = $lookup->result();
		$error  = $lookup->error();

		return array(
			'provider'   => $provider,
			'kind'       => $kind,
			'audience'   => '' === $scope ? null : $scope,
			'supported'  => $lookup->is_supported(),
			'source'     => $lookup->source(),
			'stale'      => $lookup->is_stale(),
			'fetched_at' => $result?->fetched_at(),
			'complete'   => $result?->is_complete(),
			'items'      => null === $result ? array() : array_map( static fn ( Discovered_Item $item ): array => $item->to_array(), $result->items() ),
			'error'      => null === $error ? null : array(
				'code'      => $error->code(),
				'category'  => $error->category(),
				'message'   => $error->message(),
				'retryable' => $error->is_retryable(),
			),
		);
	}

	private function service( WP_REST_Request $request ): Provider_Discovery_Service|WP_Error {
		$service = Provider_Discovery_Factory::service( (string) $request->get_param( 'provider' ) );

		return $service ?? new WP_Error(
			'campaignbridge_provider_not_found',
			__( 'That provider does not support discovery.', 'campaignbridge' ),
			array( 'status' => Rest_Constants::HTTP_NOT_FOUND )
		);
	}

	/** A failed upstream call is a gateway failure, not a client error. */
	private static function status( Provider_Error $error ): int {
		return match ( $error->category() ) {
			Provider_Error_Category::VALIDATION   => Rest_Constants::HTTP_CONFLICT,
			Provider_Error_Category::RATE_LIMITED => Rest_Constants::HTTP_TOO_MANY_REQUESTS,
			default                               => Rest_Constants::HTTP_BAD_GATEWAY,
		};
	}

	private function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
