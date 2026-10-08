<?php
/**
 * Plugin Name: CampaignBridge E2E Mailchimp
 * Description: Test-only simulated Mailchimp for browser tests. It answers every Mailchimp API request on this site, so nothing reaches a real Mailchimp account. Never install it on a production site.
 *
 * Installed only into disposable test sites (CI) or, temporarily, into a local
 * development site while browser tests run. It is not part of the release
 * package.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Simulated Mailchimp state and failure injection. */
final class CampaignBridge_E2E_Mailchimp {
	private const STATE   = 'campaignbridge_e2e_mailchimp';
	private const AUDIENCE = 'e2e-list';

	/** Hook the HTTP interceptor and the test control route. */
	public static function register(): void {
		add_filter( 'pre_http_request', array( self::class, 'intercept' ), 1, 3 );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Answer a Mailchimp API request; leave every other request alone.
	 *
	 * @param mixed                $preempt Earlier short-circuit.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public static function intercept( $preempt, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || 1 !== preg_match( '/^[a-z]{2}[0-9]{1,4}\.api\.mailchimp\.com$/', $host ) ) {
			return $preempt;
		}

		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$path   = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$body   = json_decode( is_string( $args['body'] ?? null ) ? $args['body'] : '', true );

		return self::respond( $method, preg_replace( '#^/3\.0#', '', $path ) ?? $path, is_array( $body ) ? $body : array() );
	}

	/**
	 * Route one simulated API call.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Path after /3.0.
	 * @param array<string, mixed> $body   Decoded JSON body.
	 * @return array<string, mixed>
	 */
	private static function respond( string $method, string $path, array $body ): array {
		$state = self::state();

		if ( 'GET' === $method && '/ping' === $path ) {
			return self::json( 200, array( 'health_status' => "Everything's Chimpy!" ) );
		}
		if ( 'GET' === $method && '/lists' === $path ) {
			return self::json(
				200,
				array(
					'lists'       => array(
						array(
							'id'                => self::AUDIENCE,
							'name'              => 'E2E Audience',
							'stats'             => array( 'member_count' => 3 ),
							'campaign_defaults' => array(
								'from_name'  => 'E2E Sender',
								'from_email' => 'sender@example.test',
							),
						),
					),
					'total_items' => 1,
				)
			);
		}
		if ( 'GET' === $method && 1 === preg_match( '#^/lists/[^/]+/merge-fields$#', $path ) ) {
			return self::json(
				200,
				array(
					'merge_fields' => array(
						array(
							'tag'      => 'FNAME',
							'name'     => 'First Name',
							'type'     => 'text',
							'required' => false,
						),
						array(
							'tag'      => 'LNAME',
							'name'     => 'Last Name',
							'type'     => 'text',
							'required' => false,
						),
					),
					'total_items'  => 2,
				)
			);
		}
		if ( 'GET' === $method && 1 === preg_match( '#^/lists/[^/]+/segments$#', $path ) ) {
			return self::json(
				200,
				array(
					'segments'    => array(),
					'total_items' => 0,
				)
			);
		}
		if ( 'POST' === $method && '/campaigns' === $path ) {
			$id                         = 'e2e' . ( count( $state['campaigns'] ) + 1 ) . substr( md5( (string) microtime( true ) ), 0, 6 );
			$state['campaigns'][ $id ] = array(
				'status'      => 'save',
				'list_id'     => (string) ( $body['recipients']['list_id'] ?? '' ),
				'title'       => (string) ( $body['settings']['title'] ?? '' ),
				'emails_sent' => 0,
				'send_time'   => '',
			);
			self::save( $state );

			return self::json( 200, array( 'id' => $id ) );
		}
		if ( 'GET' === $method && '/campaigns' === $path ) {
			$found = array();
			foreach ( $state['campaigns'] as $id => $campaign ) {
				$found[] = array(
					'id'       => $id,
					'settings' => array( 'title' => $campaign['title'] ),
				);
			}

			return self::json(
				200,
				array(
					'campaigns'   => $found,
					'total_items' => count( $found ),
				)
			);
		}
		if ( 1 !== preg_match( '#^/campaigns/([^/]+)(/content|/actions/([a-z-]+))?$#', $path, $matches ) || ! isset( $state['campaigns'][ $matches[1] ] ) ) {
			return self::json( 404, array( 'title' => 'Resource Not Found' ) );
		}

		$id       = $matches[1];
		$action   = $matches[3] ?? '';
		$campaign = $state['campaigns'][ $id ];
		$failing  = '' !== $action && $state['fail'] === $action;

		if ( '' === $action && '' === ( $matches[2] ?? '' ) && 'GET' === $method ) {
			// A send in flight finishes when it is next read.
			if ( 'sending' === $campaign['status'] ) {
				$state['campaigns'][ $id ]['status']      = 'sent';
				$state['campaigns'][ $id ]['emails_sent'] = 3;
				$state['campaigns'][ $id ]['send_time']   = gmdate( 'Y-m-d\TH:i:s+00:00' );
				self::save( $state );
			}

			return self::json( 200, self::read( $campaign ) );
		}
		if ( '' === $action && '' === ( $matches[2] ?? '' ) && 'PATCH' === $method ) {
			$state['campaigns'][ $id ]['list_id'] = (string) ( $body['recipients']['list_id'] ?? $campaign['list_id'] );
			self::save( $state );

			return self::json( 200, array( 'id' => $id ) );
		}
		if ( '/content' === ( $matches[2] ?? '' ) ) {
			return self::json( 200, array() );
		}

		// Applied, then reported as a server error: the ambiguous case CampaignBridge must not retry.
		if ( 'schedule' === $action ) {
			$state['campaigns'][ $id ]['status']    = 'schedule';
			$state['campaigns'][ $id ]['send_time'] = (string) ( $body['schedule_time'] ?? '' );
		} elseif ( 'unschedule' === $action ) {
			$state['campaigns'][ $id ]['status']    = 'paused';
			$state['campaigns'][ $id ]['send_time'] = '';
		} elseif ( 'send' === $action ) {
			$state['campaigns'][ $id ]['status'] = 'sending';
		} elseif ( 'test' !== $action ) {
			return self::json( 404, array( 'title' => 'Resource Not Found' ) );
		}
		if ( $failing ) {
			$state['fail'] = '';
		}
		++$state['calls'][ $action ];
		self::save( $state );

		return $failing ? self::json( 503, array( 'title' => 'Service Unavailable' ) ) : self::json( 204, null );
	}

	/**
	 * The campaign fields CampaignBridge reads.
	 *
	 * @param array<string, mixed> $campaign Stored simulated campaign.
	 * @return array<string, mixed>
	 */
	private static function read( array $campaign ): array {
		return array(
			'type'        => 'regular',
			'status'      => $campaign['status'],
			'emails_sent' => $campaign['emails_sent'],
			'send_time'   => 'paused' === $campaign['status'] ? '-001-11-30T00:00:00+00:00' : $campaign['send_time'],
			'recipients'  => array( 'list_id' => $campaign['list_id'] ),
		);
	}

	/** Register the test control route. */
	public static function routes(): void {
		register_rest_route(
			'campaignbridge-e2e/v1',
			'/mailchimp',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static fn () => rest_ensure_response( self::state() ),
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'control' ),
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				),
			)
		);
	}

	/**
	 * Set the next failing action, delete a campaign, reset the simulation, or seed a connection.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function control( WP_REST_Request $request ): WP_REST_Response {
		$state = self::state();
		if ( true === $request->get_param( 'reset' ) ) {
			$state = self::fresh();
		}
		// Delete a campaign "in Mailchimp", outside CampaignBridge.
		$forget = $request->get_param( 'forget' );
		if ( is_string( $forget ) ) {
			unset( $state['campaigns'][ $forget ] );
		}
		$fail = $request->get_param( 'fail' );
		if ( is_string( $fail ) && in_array( $fail, array( '', 'test', 'schedule', 'unschedule', 'send' ), true ) ) {
			$state['fail'] = $fail;
		}
		self::save( $state );

		// A disposable site has no connection; a development site keeps its own.
		$repository = new \CampaignBridge\Repository\Provider_Connection_Repository();
		if ( true === $request->get_param( 'connect' ) && null === $repository->get( 'mailchimp' ) ) {
			$repository->save(
				\CampaignBridge\Domain\Campaign\Provider_Connection::create(
					'mailchimp',
					\CampaignBridge\Core\Encryption::encrypt( str_repeat( '0', 32 ) . '-us1' ),
					self::AUDIENCE
				)
			);
			update_option( 'campaignbridge_provider', 'mailchimp' );
		}

		return rest_ensure_response( $state );
	}

	/** @return array{campaigns: array<string, array<string, mixed>>, fail: string, calls: array<string, int>} */
	private static function state(): array {
		$state = get_option( self::STATE );

		return is_array( $state ) && isset( $state['campaigns'], $state['fail'], $state['calls'] ) ? $state : self::fresh();
	}

	/** @return array{campaigns: array<string, array<string, mixed>>, fail: string, calls: array<string, int>} */
	private static function fresh(): array {
		return array(
			'campaigns' => array(),
			'fail'      => '',
			'calls'     => array(
				'test'       => 0,
				'schedule'   => 0,
				'unschedule' => 0,
				'send'       => 0,
			),
		);
	}

	/** @param array<string, mixed> $state Simulation state. */
	private static function save( array $state ): void {
		update_option( self::STATE, $state, false );
	}

	/**
	 * A WordPress HTTP API response.
	 *
	 * @param int        $status HTTP status.
	 * @param mixed|null $data   JSON body, or null for none.
	 * @return array<string, mixed>
	 */
	private static function json( int $status, $data ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => null === $data ? '' : (string) wp_json_encode( $data ),
			'response' => array(
				'code'    => $status,
				'message' => get_status_header_desc( $status ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}

CampaignBridge_E2E_Mailchimp::register();
