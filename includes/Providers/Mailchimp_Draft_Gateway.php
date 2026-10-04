<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port method contracts are documented by Provider_Draft_Gateway.
/**
 * Mailchimp remote draft adapter.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Instance;
use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Draft_Outcome;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
use CampaignBridge\Domain\Provider\Remote_Draft_Matches;
use CampaignBridge\Domain\Provider\Remote_Draft_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, re-asserts, and inspects one regular Mailchimp campaign draft.
 *
 * The create request is never retried: Mailchimp has no idempotency key for
 * campaign creation, so a lost response may hide a created draft and is
 * reported as ambiguous. Re-asserting a draft is an idempotent PATCH of its
 * audience and envelope followed by an idempotent PUT of its content.
 * Inspection is a read-only GET of the status, send time, list, and
 * segment, plus the type and send count that prove a `paused` campaign (how
 * Mailchimp reports an unscheduled one) has sent nothing. Finding drafts is a read-only listing of campaigns created since
 * the request, matched on the exact correlation title. No schedule or send
 * endpoint is ever called. Response bodies are read only for
 * those fields and never leave this class.
 */
final class Mailchimp_Draft_Gateway implements Provider_Draft_Gateway {
	/** Seconds to wait for a draft mutation before the outcome is unknown. */
	private const TIMEOUT = 20;

	/** Most campaigns one recovery search reads; Mailchimp's page maximum. */
	private const SEARCH_LIMIT = 1000;

	/**
	 * Injected HTTP transport.
	 *
	 * @var Http_Client_Interface
	 */
	private readonly Http_Client_Interface $http;

	public function __construct( ?Http_Client_Interface $http = null ) {
		$this->http = $http ?? new Http_Client_Instance();
	}

	public function slug(): string {
		return 'mailchimp';
	}

	public function create_draft( array $settings, Draft_Content $content ): Draft_Outcome {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Draft_Outcome::from_create_error( Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
		}

		$response = $this->http->post(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns' ),
			$this->json_request(
				$api_key,
				array(
					'type'       => 'regular',
					'recipients' => array( 'list_id' => $content->audience_id() ),
					'settings'   => array(
						'subject_line' => $content->subject(),
						'preview_text' => $content->preview_text(),
						'title'        => $content->correlation(),
						'from_name'    => $content->from_name(),
						'reply_to'     => $content->reply_to(),
					),
				),
				false
			)
		);
		if ( is_wp_error( $response ) ) {
			return Draft_Outcome::from_create_error( Mailchimp_Errors::from_transport( $response ) );
		}
		$status = $response['status_code'] ?? 0;
		if ( ! is_int( $status ) || 200 !== $status ) {
			return Draft_Outcome::from_create_error( Mailchimp_Errors::from_status( is_int( $status ) ? $status : 0 ) );
		}

		$decoded   = json_decode( is_string( $response['body'] ?? null ) ? $response['body'] : '', true );
		$remote_id = is_array( $decoded ) ? ( $decoded['id'] ?? null ) : null;
		if ( ! is_string( $remote_id ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $remote_id ) ) {
			// Mailchimp accepted the request but the draft ID is unreadable: it may exist.
			return Draft_Outcome::from_create_error( Mailchimp_Errors::unexpected_response() );
		}

		$error = $this->upload( $api_key, $remote_id, $content );

		return null === $error ? Draft_Outcome::created( $remote_id ) : Draft_Outcome::content_pending( $remote_id, $error );
	}

	public function sync_draft( array $settings, string $remote_id, Draft_Content $content ): Action_Outcome {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Action_Outcome::from_error( Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION ) );
		}

		$response = $this->http->patch(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns/' . rawurlencode( $remote_id ) ),
			$this->json_request(
				$api_key,
				array(
					'recipients' => array( 'list_id' => $content->audience_id() ),
					'settings'   => array(
						'subject_line' => $content->subject(),
						'preview_text' => $content->preview_text(),
						'from_name'    => $content->from_name(),
						'reply_to'     => $content->reply_to(),
					),
				),
				true
			)
		);
		$error    = $this->failure( $response );
		if ( null === $error ) {
			$error = $this->upload( $api_key, $remote_id, $content );
		}

		return null === $error ? Action_Outcome::accepted() : Action_Outcome::from_error( $error );
	}

	public function inspect_draft( array $settings, string $remote_id ): Remote_Draft_State|Provider_Error {
		$api_key = $this->api_key( $settings );
		if ( null === $api_key ) {
			return Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION );
		}

		$response = $this->http->get(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns/' . rawurlencode( $remote_id ) ) . '?fields=type,status,emails_sent,send_time,recipients.list_id,recipients.segment_opts',
			array(
				'headers'               => array( 'Authorization' => 'Bearer ' . $api_key ),
				'timeout'               => self::TIMEOUT,
				'campaignbridge_origin' => Mailchimp_Provider::origin(),
			)
		);
		$error    = $this->failure( $response );
		if ( null !== $error ) {
			return $error;
		}

		$decoded    = json_decode( is_array( $response ) && is_string( $response['body'] ?? null ) ? $response['body'] : '', true );
		$status     = is_array( $decoded ) ? ( $decoded['status'] ?? null ) : null;
		$recipients = is_array( $decoded ) && is_array( $decoded['recipients'] ?? null ) ? $decoded['recipients'] : null;
		$list_id    = $recipients['list_id'] ?? null;
		if ( ! is_string( $status ) || ! is_string( $list_id ) ) {
			return Mailchimp_Errors::unexpected_response();
		}

		// Mailchimp reports an unscheduled regular campaign as paused, but it also
		// pauses campaigns it halts, possibly mid-send. Paused counts as an unsent
		// draft only for a regular campaign that has sent nothing.
		$unsent_pause = 'paused' === $status && 'regular' === ( $decoded['type'] ?? null ) && 0 === ( $decoded['emails_sent'] ?? null );
		$state        = match ( true ) {
			'save' === $status || $unsent_pause => Remote_Draft_State::DRAFT,
			'schedule' === $status              => Remote_Draft_State::SCHEDULED,
			'sending' === $status               => Remote_Draft_State::SENDING,
			'sent' === $status                  => Remote_Draft_State::SENT,
			'canceled' === $status              => Remote_Draft_State::CANCELED,
			default                             => Remote_Draft_State::OTHER,
		};

		return Remote_Draft_State::create(
			$state,
			$list_id,
			self::is_segmented( $recipients['segment_opts'] ?? null ),
			// An unsent campaign's send_time is empty or a placeholder such as -001-11-30.
			in_array( $state, array( Remote_Draft_State::SCHEDULED, Remote_Draft_State::SENDING, Remote_Draft_State::SENT ), true ) ? self::utc( $decoded['send_time'] ?? null ) : null
		);
	}

	public function find_drafts( array $settings, string $title, string $created_after ): Remote_Draft_Matches|Provider_Error {
		$api_key = $this->api_key( $settings );
		$since   = self::utc( $created_after );
		if ( null === $api_key || null === $since ) {
			return Mailchimp_Errors::for_category( Provider_Error_Category::VALIDATION );
		}

		$query    = http_build_query(
			array(
				'since_create_time' => $since,
				'count'             => self::SEARCH_LIMIT,
				'sort_field'        => 'create_time',
				'sort_dir'          => 'ASC',
				'fields'            => 'total_items,campaigns.id,campaigns.settings.title',
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
		$response = $this->http->get(
			Mailchimp_Provider::build_api_url( $api_key, '/campaigns' ) . '?' . $query,
			array(
				'headers'               => array( 'Authorization' => 'Bearer ' . $api_key ),
				'timeout'               => self::TIMEOUT,
				'campaignbridge_origin' => Mailchimp_Provider::origin(),
			)
		);
		$error    = $this->failure( $response );
		if ( null !== $error ) {
			return $error;
		}

		$decoded   = json_decode( is_array( $response ) && is_string( $response['body'] ?? null ) ? $response['body'] : '', true );
		$campaigns = is_array( $decoded ) ? ( $decoded['campaigns'] ?? null ) : null;
		$total     = is_array( $decoded ) ? ( $decoded['total_items'] ?? null ) : null;
		if ( ! is_array( $campaigns ) || ! is_int( $total ) ) {
			return Mailchimp_Errors::unexpected_response();
		}

		$matches = array();
		foreach ( $campaigns as $campaign ) {
			$campaign_title = is_array( $campaign ) ? ( $campaign['settings']['title'] ?? null ) : null;
			if ( is_string( $campaign['id'] ?? null ) && $campaign_title === $title ) {
				$matches[] = $campaign['id'];
			}
		}

		return Remote_Draft_Matches::create( $matches, count( $campaigns ) >= $total );
	}

	/** Normalize a provider timestamp to UTC; null when absent, unreadable, or a placeholder before 2000. */
	private static function utc( mixed $time ): ?string {
		$parsed = is_string( $time ) && '' !== $time ? strtotime( $time ) : false;

		return false === $parsed || $parsed < 946684800 ? null : gmdate( 'Y-m-d\TH:i:s\Z', $parsed );
	}

	/** Upload content with an idempotent PUT; null on success. */
	private function upload( #[\SensitiveParameter] string $api_key, string $remote_id, Draft_Content $content ): ?Provider_Error {
		return $this->failure(
			$this->http->put(
				Mailchimp_Provider::build_api_url( $api_key, '/campaigns/' . rawurlencode( $remote_id ) . '/content' ),
				$this->json_request(
					$api_key,
					array(
						'html'       => $content->html(),
						'plain_text' => $content->text(),
					),
					true
				)
			)
		);
	}

	/**
	 * Normalize a non-200 response; null on success.
	 *
	 * @param array<string, mixed>|\WP_Error $response Transport response.
	 */
	private function failure( array|\WP_Error $response ): ?Provider_Error {
		if ( is_wp_error( $response ) ) {
			return Mailchimp_Errors::from_transport( $response );
		}
		$status = $response['status_code'] ?? 0;

		return is_int( $status ) && 200 === $status ? null : Mailchimp_Errors::from_status( is_int( $status ) ? $status : 0 );
	}

	/**
	 * Whether any segment narrows or replaces the whole-list audience.
	 *
	 * Unreadable segment data counts as segmented, so it fails closed.
	 */
	private static function is_segmented( mixed $options ): bool {
		if ( null === $options || array() === $options ) {
			return false;
		}
		if ( ! is_array( $options ) ) {
			return true;
		}
		$saved      = $options['saved_segment_id'] ?? 0;
		$prebuilt   = $options['prebuilt_segment_id'] ?? '';
		$conditions = $options['conditions'] ?? array();

		return ! ( in_array( $saved, array( 0, '0' ), true ) && '' === $prebuilt && array() === $conditions );
	}

	/**
	 * Build an authenticated JSON request.
	 *
	 * @param array<string, mixed> $body JSON body.
	 * @return array<string, mixed>
	 */
	private function json_request( #[\SensitiveParameter] string $api_key, array $body, bool $may_retry ): array {
		return array(
			'headers'               => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'                  => (string) wp_json_encode( $body ),
			'timeout'               => self::TIMEOUT,
			'campaignbridge_retry'  => $may_retry,
			'campaignbridge_origin' => Mailchimp_Provider::origin(),
		);
	}

	/** @param array<string, mixed> $settings Decrypted settings. */
	private function api_key( array $settings ): ?string {
		$api_key = $settings['api_key'] ?? null;

		return is_string( $api_key ) && ( new Mailchimp_Provider() )->is_valid_api_key( $api_key ) ? $api_key : null;
	}
}
