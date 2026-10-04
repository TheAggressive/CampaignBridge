<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile
/**
 * Mailchimp draft gateway tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Core\Http_Origin;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Draft_Outcome;
use CampaignBridge\Domain\Provider\Remote_Draft_Matches;
use CampaignBridge\Domain\Provider\Remote_Draft_State;
use CampaignBridge\Providers\Mailchimp_Draft_Gateway;
use WP_UnitTestCase;

/** Replays a queue of responses and records every request by method. */
final class Sequenced_Http_Client implements Http_Client_Interface {
	/** @var array<int, array{method: string, url: string, args: array<string, mixed>}> */
	public array $requests = array();

	/** @param array<int, array<string, mixed>|\WP_Error> $responses Responses in call order. */
	public function __construct( private array $responses ) {}

	public function get( string $url, array $args = array() ) {
		return $this->record( 'GET', $url, $args );
	}

	public function post( string $url, array $args = array() ) {
		return $this->record( 'POST', $url, $args );
	}

	public function put( string $url, array $args = array() ) {
		return $this->record( 'PUT', $url, $args );
	}

	public function patch( string $url, array $args = array() ) {
		return $this->record( 'PATCH', $url, $args );
	}

	public function delete( string $url, array $args = array() ) {
		return $this->record( 'DELETE', $url, $args );
	}

	/**
	 * @param array<string, mixed> $args Request arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function record( string $method, string $url, array $args ) {
		if ( ! ( $args['campaignbridge_origin'] ?? null ) instanceof Http_Origin || ! $args['campaignbridge_origin']->allows( $url ) ) {
			throw new \LogicException( 'Every Mailchimp request must declare a trusted origin that allows its URL.' );
		}
		$this->requests[] = array(
			'method' => $method,
			'url'    => $url,
			'args'   => $args,
		);
		if ( array() === $this->responses ) {
			throw new \LogicException( 'Unexpected request: ' . $method . ' ' . $url );
		}

		return array_shift( $this->responses );
	}
}

/** Proves the draft adapter creates once, classifies outcomes truthfully, and never sends. */
final class Mailchimp_Draft_Gateway_Test extends WP_UnitTestCase {
	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0' . '-us20';
	}

	/** @return array<string, string> */
	private static function settings(): array {
		return array( 'api_key' => self::api_key() );
	}

	private static function content(): Draft_Content {
		return Draft_Content::create(
			'abc123',
			'Spring sale for *|FNAME|*',
			'Two days only',
			'Example Shop',
			'News@Example.com',
			'<html><body>Hello *|FNAME|*</body></html>',
			'Hello *|FNAME|*',
			'sha256:' . str_repeat( 'a', 64 ),
			'CampaignBridge attempt-1'
		);
	}

	/** @return array<string, mixed> */
	private static function reply( int $status, string $body = '{}' ): array {
		return array(
			'status_code' => $status,
			'body'        => $body,
			'headers'     => array(),
		);
	}

	public function test_create_posts_once_then_uploads_the_exact_content(): void {
		$http    = new Sequenced_Http_Client( array( self::reply( 200, '{"id":"mc9876","status":"save","web_id":42}' ), self::reply( 200 ) ) );
		$outcome = ( new Mailchimp_Draft_Gateway( $http ) )->create_draft( self::settings(), self::content() );

		self::assertSame( Draft_Outcome::CREATED, $outcome->status() );
		self::assertSame( 'mc9876', $outcome->remote_id() );
		self::assertNull( $outcome->error() );
		self::assertCount( 2, $http->requests );

		$create = $http->requests[0];
		self::assertSame( 'POST', $create['method'] );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns', $create['url'] );
		self::assertSame( 'Bearer ' . self::api_key(), $create['args']['headers']['Authorization'] );
		self::assertFalse( $create['args']['campaignbridge_retry'], 'A create must never be retried automatically.' );
		self::assertSame(
			array(
				'type'       => 'regular',
				'recipients' => array( 'list_id' => 'abc123' ),
				'settings'   => array(
					'subject_line' => 'Spring sale for *|FNAME|*',
					'preview_text' => 'Two days only',
					'title'        => 'CampaignBridge attempt-1',
					'from_name'    => 'Example Shop',
					'reply_to'     => 'news@example.com',
				),
			),
			json_decode( $create['args']['body'], true )
		);

		$upload = $http->requests[1];
		self::assertSame( 'PUT', $upload['method'] );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns/mc9876/content', $upload['url'] );
		self::assertSame(
			array(
				'html'       => '<html><body>Hello *|FNAME|*</body></html>',
				'plain_text' => 'Hello *|FNAME|*',
			),
			json_decode( $upload['args']['body'], true )
		);
		foreach ( $http->requests as $request ) {
			self::assertStringNotContainsString( '/actions/', $request['url'], 'The gateway never calls a send, schedule, or test action.' );
		}
	}

	/** @return array<string, array{array<string, mixed>|\WP_Error, string, string}> */
	public static function create_failures(): array {
		$detail = '{"title":"Invalid Resource","detail":"private provider detail"}';

		return array(
			'invalid request'  => array( self::reply( 400, $detail ), Draft_Outcome::FAILED, Provider_Error_Category::VALIDATION ),
			'rejected key'     => array( self::reply( 401, $detail ), Draft_Outcome::FAILED, Provider_Error_Category::AUTHENTICATION ),
			'forbidden'        => array( self::reply( 403, $detail ), Draft_Outcome::FAILED, Provider_Error_Category::AUTHORIZATION ),
			'missing audience' => array( self::reply( 404, $detail ), Draft_Outcome::FAILED, Provider_Error_Category::NOT_FOUND ),
			'rate limited'     => array( self::reply( 429, $detail ), Draft_Outcome::FAILED, Provider_Error_Category::RATE_LIMITED ),
			'server error'     => array( self::reply( 500, $detail ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::PROVIDER_ERROR ),
			'gateway error'    => array( self::reply( 503, $detail ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::PROVIDER_ERROR ),
			'timeout'          => array( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::TIMEOUT ),
			'connection lost'  => array( new \WP_Error( 'http_request_failed', 'Connection reset by peer' ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::NETWORK ),
			'unreadable body'  => array( self::reply( 200, '<html>proxy</html>' ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::UNKNOWN ),
			'missing id'       => array( self::reply( 200, '{"status":"save"}' ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::UNKNOWN ),
			'unsafe id'        => array( self::reply( 200, '{"id":"../x"}' ), Draft_Outcome::AMBIGUOUS, Provider_Error_Category::UNKNOWN ),
		);
	}

	/**
	 * @dataProvider create_failures
	 * @param array<string, mixed>|\WP_Error $response Scripted create response.
	 */
	public function test_create_failures_are_definite_or_ambiguous_and_never_retried( array|\WP_Error $response, string $status, string $category ): void {
		$http    = new Sequenced_Http_Client( array( $response ) );
		$outcome = ( new Mailchimp_Draft_Gateway( $http ) )->create_draft( self::settings(), self::content() );

		self::assertSame( $status, $outcome->status() );
		self::assertSame( $category, $outcome->error()?->category() );
		self::assertNull( $outcome->remote_id() );
		self::assertCount( 1, $http->requests, 'One create attempt, and no content upload without a draft ID.' );
		$exposed = (string) wp_json_encode( $outcome->error()?->to_array() );
		foreach ( array( 'private provider detail', 'cURL', 'reset by peer', 'proxy', self::api_key() ) as $leak ) {
			self::assertStringNotContainsString( $leak, $exposed );
		}
	}

	public function test_failed_content_upload_keeps_the_created_draft_id(): void {
		foreach ( array( self::reply( 500 ), new \WP_Error( 'http_request_failed', 'timed out' ), self::reply( 404 ) ) as $failure ) {
			$http    = new Sequenced_Http_Client( array( self::reply( 200, '{"id":"mc9876"}' ), $failure ) );
			$outcome = ( new Mailchimp_Draft_Gateway( $http ) )->create_draft( self::settings(), self::content() );

			self::assertSame( Draft_Outcome::CONTENT_PENDING, $outcome->status() );
			self::assertSame( 'mc9876', $outcome->remote_id(), 'The draft exists, so its ID must never be lost.' );
			self::assertNotNull( $outcome->error() );
		}
	}

	public function test_sync_re_asserts_audience_envelope_and_content_idempotently(): void {
		$http    = new Sequenced_Http_Client( array( self::reply( 200 ), self::reply( 200 ) ) );
		$outcome = ( new Mailchimp_Draft_Gateway( $http ) )->sync_draft( self::settings(), 'mc9876', self::content() );

		self::assertSame( Action_Outcome::ACCEPTED, $outcome->status() );
		self::assertSame( array( 'PATCH', 'PUT' ), array_column( $http->requests, 'method' ) );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns/mc9876', $http->requests[0]['url'] );
		self::assertSame(
			array(
				'recipients' => array( 'list_id' => 'abc123' ),
				'settings'   => array(
					'subject_line' => 'Spring sale for *|FNAME|*',
					'preview_text' => 'Two days only',
					'from_name'    => 'Example Shop',
					'reply_to'     => 'news@example.com',
				),
			),
			json_decode( $http->requests[0]['args']['body'], true )
		);
		self::assertTrue( $http->requests[0]['args']['campaignbridge_retry'], 'The update is idempotent, so it may retry.' );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns/mc9876/content', $http->requests[1]['url'] );
		self::assertTrue( $http->requests[1]['args']['campaignbridge_retry'] );
	}

	public function test_a_failed_settings_update_never_uploads_content(): void {
		$http    = new Sequenced_Http_Client( array( self::reply( 404, '{"detail":"private detail"}' ) ) );
		$outcome = ( new Mailchimp_Draft_Gateway( $http ) )->sync_draft( self::settings(), 'mc9876', self::content() );

		self::assertSame( Action_Outcome::FAILED, $outcome->status() );
		self::assertSame( 'mailchimp_not_found', $outcome->error()?->code() );
		self::assertCount( 1, $http->requests );
	}

	/** @return array<string, array{string, string, bool}> */
	public static function observations(): array {
		return array(
			'unsent draft'        => array( '{"status":"save","recipients":{"list_id":"abc123","segment_opts":{}}}', Remote_Draft_State::DRAFT, false ),
			'no segment options'  => array( '{"status":"save","recipients":{"list_id":"abc123"}}', Remote_Draft_State::DRAFT, false ),
			'empty conditions'    => array( '{"status":"save","recipients":{"list_id":"abc123","segment_opts":{"saved_segment_id":0,"match":"any","conditions":[]}}}', Remote_Draft_State::DRAFT, false ),
			'saved segment'       => array( '{"status":"save","recipients":{"list_id":"abc123","segment_opts":{"saved_segment_id":12}}}', Remote_Draft_State::DRAFT, true ),
			'prebuilt segment'    => array( '{"status":"save","recipients":{"list_id":"abc123","segment_opts":{"prebuilt_segment_id":"subscribers-female"}}}', Remote_Draft_State::DRAFT, true ),
			'conditions'          => array( '{"status":"save","recipients":{"list_id":"abc123","segment_opts":{"match":"all","conditions":[{"field":"EMAIL"}]}}}', Remote_Draft_State::DRAFT, true ),
			'unreadable segment'  => array( '{"status":"save","recipients":{"list_id":"abc123","segment_opts":"all"}}', Remote_Draft_State::DRAFT, true ),
			'scheduled elsewhere' => array( '{"status":"schedule","recipients":{"list_id":"abc123"}}', Remote_Draft_State::SCHEDULED, false ),
			'sent elsewhere'      => array( '{"status":"sent","recipients":{"list_id":"abc123"}}', Remote_Draft_State::SENT, false ),
			// Mailchimp reports an unscheduled regular campaign as paused (observed live).
			'paused'              => array( '{"status":"paused","recipients":{"list_id":"abc123"}}', Remote_Draft_State::DRAFT, false ),
		);
	}

	/** @dataProvider observations */
	public function test_inspection_reads_only_status_audience_and_segment( string $body, string $status, bool $segmented ): void {
		$http  = new Sequenced_Http_Client( array( self::reply( 200, $body ) ) );
		$state = ( new Mailchimp_Draft_Gateway( $http ) )->inspect_draft( self::settings(), 'mc9876' );

		self::assertInstanceOf( Remote_Draft_State::class, $state );
		self::assertSame( array( $status, 'abc123', $segmented ), array( $state->status(), $state->audience_id(), $state->is_segmented() ) );
		self::assertSame( 'GET', $http->requests[0]['method'] );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns/mc9876?fields=status,send_time,recipients.list_id,recipients.segment_opts', $http->requests[0]['url'] );
		self::assertSame( Remote_Draft_State::DRAFT === $status && ! $segmented, $state->matches( 'abc123' ) );
		self::assertFalse( $state->matches( 'other-audience' ) );
	}

	public function test_inspection_normalizes_the_send_time_and_a_canceled_campaign(): void {
		$scheduled = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( self::reply( 200, '{"status":"schedule","send_time":"2026-10-05T17:00:00+02:00","recipients":{"list_id":"abc123"}}' ) ) ) ) )->inspect_draft( self::settings(), 'mc9876' );
		$draft     = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( self::reply( 200, '{"status":"save","send_time":"","recipients":{"list_id":"abc123"}}' ) ) ) ) )->inspect_draft( self::settings(), 'mc9876' );
		$canceled  = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( self::reply( 200, '{"status":"canceled","recipients":{"list_id":"abc123"}}' ) ) ) ) )->inspect_draft( self::settings(), 'mc9876' );
		$canceling = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( self::reply( 200, '{"status":"canceling","recipients":{"list_id":"abc123"}}' ) ) ) ) )->inspect_draft( self::settings(), 'mc9876' );

		self::assertSame( array( Remote_Draft_State::SCHEDULED, '2026-10-05T15:00:00Z' ), array( $scheduled->status(), $scheduled->send_time() ) );
		self::assertNull( $draft->send_time() );
		self::assertSame( Remote_Draft_State::CANCELED, $canceled->status() );
		self::assertSame( Remote_Draft_State::OTHER, $canceling->status(), 'A cancellation still in progress is not yet canceled.' );

		// Recorded from the live Mailchimp run: an unscheduled campaign.
		$unscheduled = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( self::reply( 200, '{"status":"paused","send_time":"-001-11-30T00:00:00+00:00","recipients":{"list_id":"abc123"}}' ) ) ) ) )->inspect_draft( self::settings(), 'mc9876' );
		self::assertSame( array( Remote_Draft_State::DRAFT, null ), array( $unscheduled->status(), $unscheduled->send_time() ) );
		self::assertTrue( $unscheduled->matches( 'abc123' ), 'An unscheduled campaign can be re-asserted and delivered again.' );
	}

	public function test_finding_drafts_matches_the_exact_title_and_reports_completeness(): void {
		$body    = '{"total_items":3,"campaigns":[{"id":"mc1","settings":{"title":"CampaignBridge attempt-9"}},{"id":"mc2","settings":{"title":"CampaignBridge attempt-90"}},{"id":"mc3","settings":{"title":"Other"}}]}';
		$http    = new Sequenced_Http_Client( array( self::reply( 200, $body ) ) );
		$matches = ( new Mailchimp_Draft_Gateway( $http ) )->find_drafts( self::settings(), 'CampaignBridge attempt-9', '2026-10-05T11:00:00Z' );

		self::assertInstanceOf( Remote_Draft_Matches::class, $matches );
		self::assertSame( array( 'mc1' ), $matches->remote_ids() );
		self::assertTrue( $matches->is_complete() );
		self::assertSame( 'GET', $http->requests[0]['method'] );
		self::assertStringStartsWith( 'https://us20.api.mailchimp.com/3.0/campaigns?since_create_time=2026-10-05T11%3A00%3A00Z&count=1000', $http->requests[0]['url'] );

		$partial = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( self::reply( 200, '{"total_items":1500,"campaigns":[]}' ) ) ) ) )->find_drafts( self::settings(), 'CampaignBridge attempt-9', '2026-10-05T11:00:00Z' );
		self::assertFalse( $partial->is_complete(), 'An empty page of a larger result proves nothing.' );

		foreach ( array( self::reply( 200, 'not json' ), self::reply( 200, '{"campaigns":[]}' ), self::reply( 500 ) ) as $reply ) {
			self::assertInstanceOf( Provider_Error::class, ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( $reply ) ) ) )->find_drafts( self::settings(), 'x', '2026-10-05T11:00:00Z' ) );
		}
	}

	public function test_an_unreadable_inspection_fails_closed(): void {
		foreach ( array( self::reply( 200, 'not json' ), self::reply( 200, '{"status":"save"}' ), self::reply( 503 ) ) as $reply ) {
			$state = ( new Mailchimp_Draft_Gateway( new Sequenced_Http_Client( array( $reply ) ) ) )->inspect_draft( self::settings(), 'mc9876' );
			self::assertInstanceOf( Provider_Error::class, $state );
		}
	}

	public function test_invalid_credentials_fail_before_any_request(): void {
		$http    = new Sequenced_Http_Client( array() );
		$outcome = ( new Mailchimp_Draft_Gateway( $http ) )->create_draft( array( 'api_key' => 'nope' ), self::content() );

		self::assertSame( Draft_Outcome::FAILED, $outcome->status() );
		self::assertSame( Provider_Error_Category::VALIDATION, $outcome->error()?->category() );
		self::assertSame( array(), $http->requests );
	}

	public function test_draft_content_rejects_incomplete_or_unbounded_requests(): void {
		$valid = array( 'abc123', 'Subject', '', 'Shop', 'a@example.com', '<p>Hi</p>', 'Hi', 'sha256:' . str_repeat( 'a', 64 ), 'CampaignBridge attempt-1' );
		foreach ( array(
			1 => '',
			3 => '',
			4 => 'not-an-email',
			5 => '',
			7 => 'md5:abc',
			8 => "bad\nlabel",
			0 => '../lists',
		) as $index => $bad ) {
			$arguments           = $valid;
			$arguments[ $index ] = $bad;
			try {
				Draft_Content::create( ...$arguments );
				self::fail( 'Argument ' . $index . ' must be rejected.' );
			} catch ( \InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}
}
