<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile,CampaignBridge.Standard.Sniffs.Database
/**
 * Campaign test delivery integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Provider\Provider_Test_Gateway;
use CampaignBridge\Domain\Provider\Test_Delivery;
use CampaignBridge\Domain\Provider\Test_Outcome;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Campaign_Template_Input_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Draft_Handoff;
use CampaignBridge\Workflow\Campaign\Campaign_Review_Input_Capture;
use CampaignBridge\Workflow\Campaign\Campaign_Template_Authority;
use CampaignBridge\Workflow\Campaign\Campaign_Test_Delivery;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;

/** Stands in for the provider: counts test sends and replays scripted outcomes. */
final class Scripted_Test_Gateway implements Provider_Test_Gateway {
	public int $sends = 0;

	/** @var array<int, array{remote_id: string, delivery: Test_Delivery}> */
	public array $calls = array();

	/** @var array<int, Test_Outcome> Outcomes for successive sends; empty means sent. */
	public array $outcomes = array();

	public function slug(): string {
		return 'mailchimp';
	}

	public function send_test( array $settings, string $remote_id, Test_Delivery $delivery ): Test_Outcome {
		++$this->sends;
		$this->calls[] = array(
			'remote_id' => $remote_id,
			'delivery'  => $delivery,
		);

		return array_shift( $this->outcomes ) ?? Test_Outcome::sent();
	}
}

/** A clock tests can move forward. */
final class Movable_Clock implements Campaign_Clock {
	public int $now;

	public function __construct() {
		$this->now = time();
	}

	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->now );
	}
}

/** Grants every template so these tests exercise test-delivery rules only. */
final class Test_Delivery_Template_Authority implements Campaign_Template_Authority {
	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool {
		return true;
	}
}

/** Proves a test reaches only named addresses, once per key, without touching the lifecycle. */
final class Campaign_Test_Delivery_Test extends Test_Case {
	private const AUDIENCE = 'abc123';

	private Campaign_Repository $campaigns;

	private Campaign_Snapshot_Repository $snapshots;

	private Remote_Campaign_Reference_Repository $references;

	private Delivery_Attempt_Repository $attempts;

	private Audit_Event_Repository $audits;

	private Campaign_Workflow $workflow;

	private Scripted_Test_Gateway $gateway;

	private Movable_Clock $clock;

	private Campaign_Test_Delivery $delivery;

	private Campaign_Actor $tester;

	private int $template_id;

	/** @return array<string, string> */
	private static function settings(): array {
		return array( 'api_key' => str_repeat( 'c0ffee', 5 ) . 'c0-us20' );
	}

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );
		$this->truncate();

		$this->campaigns  = new Campaign_Repository();
		$this->snapshots  = new Campaign_Snapshot_Repository();
		$this->references = new Remote_Campaign_Reference_Repository();
		$this->attempts   = new Delivery_Attempt_Repository();
		$this->audits     = new Audit_Event_Repository();
		$this->gateway    = new Scripted_Test_Gateway();
		$this->clock      = new Movable_Clock();
		$this->tester     = new Campaign_Actor( 7, true, false, true, true );
		$this->workflow   = new Campaign_Workflow(
			$this->campaigns,
			$this->snapshots,
			$this->audits,
			new Campaign_Review_Input_Capture( new Campaign_Template_Input_Repository(), new Post_Snapshot_Repository(), new Brand_Kit_Repository() ),
			new Test_Delivery_Template_Authority(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock
		);
		$this->delivery   = new Campaign_Test_Delivery(
			$this->campaigns,
			$this->snapshots,
			$this->references,
			$this->attempts,
			$this->audits,
			new Random_Id_Generator(),
			$this->clock,
			$this->gateway,
			( new Mailchimp_Provider() )->capabilities()
		);

		kses_remove_filters();
		$this->template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Test delivery template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello {{cb:subscriber.first_name}}, <a href="{{cb:campaign.unsubscribe_url}}">unsubscribe</a></p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			)
		);
		update_post_meta( $this->template_id, 'campaignbridge_subject', 'Spring sale' );
		update_post_meta( $this->template_id, 'campaignbridge_sender_name', 'Example Shop' );
		update_post_meta( $this->template_id, 'campaignbridge_sender_email', 'news@example.com' );
	}

	/** The workflow commits real transactions, so shared fixtures are removed explicitly. */
	public function tearDown(): void {
		global $wpdb;
		kses_init_filters();
		wp_delete_post( $this->template_id, true );
		$this->truncate();
		// The harness runs with autocommit off, so cleanup of committed fixtures must commit too.
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	public function test_provider_draft_is_tested_once_without_changing_the_campaign(): void {
		$campaign = $this->provider_draft_campaign();
		$snapshot = $this->snapshots->get( (string) $campaign->active_snapshot_id() );

		$result = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'QA@Example.com', 'lead@example.org' ), 'html', 'test-key-1', self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertFalse( $result->is_idempotent_replay() );
		self::assertSame( 1, $this->gateway->sends );
		self::assertSame( 'mc0001', $this->gateway->calls[0]['remote_id'], 'The test sends the existing remote draft, not editor HTML.' );
		self::assertSame( array( 'qa@example.com', 'lead@example.org' ), $this->gateway->calls[0]['delivery']->recipients() );
		self::assertSame( $snapshot?->artifact()->fingerprint(), $result->snapshot()?->artifact()->fingerprint() );

		// A test never advances the lifecycle or the version.
		self::assertSame( $campaign->to_array(), $this->campaigns->get( $campaign->id() )?->to_array() );
		self::assertSame( Campaign_State::PROVIDER_DRAFT, $result->campaign()?->state() );

		// The attempt is a test_send against the draft, distinct from the create_draft record.
		$attempts = $this->attempts->for_campaign( $campaign->id() );
		self::assertCount( 1, $attempts );
		self::assertSame( array( 'test_send', 'test-key-1', 'succeeded', 'not_retryable', 'mc0001' ), array( $attempts[0]->operation(), $attempts[0]->idempotency_key(), $attempts[0]->status(), $attempts[0]->retryability(), $attempts[0]->remote_correlation() ) );

		$event = $this->events( $campaign->id(), 'campaign_test_send' )[0];
		self::assertSame( 'success', $event->result() );
		$context = $event->context()->to_array();
		self::assertSame( array( 'mc0001', $attempts[0]->id(), $snapshot?->id(), $snapshot?->artifact()->fingerprint(), 'html', 2, 'provider_draft' ), array( $context['remote_id'], $context['attempt_id'], $context['snapshot_id'], $context['fingerprint'], $context['test_format'], $context['destination_count'], $context['campaign_state'] ) );

		$this->assert_no_recipient_is_stored( array( 'qa@example.com', 'lead@example.org' ) );
	}

	public function test_a_repeated_key_replays_the_recorded_test_and_a_new_key_sends_again(): void {
		$campaign = $this->provider_draft_campaign();
		$first    = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-1', self::settings() );

		$replay = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'other@example.com' ), 'text', 'test-key-1', self::settings() );
		self::assertTrue( $replay->is_success() );
		self::assertTrue( $replay->is_idempotent_replay() );
		self::assertSame( $first->attempt()?->to_array(), $replay->attempt()?->to_array() );
		self::assertNull( $replay->delivery(), 'A replay reports the recorded attempt; recipients were never stored.' );
		self::assertSame( 1, $this->gateway->sends );

		self::assertTrue( $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-2', self::settings() )->is_success() );
		self::assertSame( 2, $this->gateway->sends );
		self::assertCount( 2, $this->attempts->for_campaign( $campaign->id() ) );
	}

	public function test_an_unconfirmed_test_is_reported_and_never_retried(): void {
		$campaign                = $this->provider_draft_campaign();
		$this->gateway->outcomes = array( Test_Outcome::from_error( Provider_Error::timeout( 'mailchimp_connection_timeout', 'Mailchimp request timed out.', 'mailchimp' ) ) );

		$unknown = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $unknown->error()?->code() );
		self::assertSame( Provider_Error_Category::TIMEOUT, $unknown->provider_error()?->category() );
		self::assertSame( array( 'unknown', 'unknown' ), array( $unknown->attempt()?->status(), $unknown->attempt()?->retryability() ) );
		self::assertSame( 'unknown', $this->attempts->for_campaign( $campaign->id() )[0]->status() );
		self::assertSame( 'unknown', $this->events( $campaign->id(), 'campaign_test_send' )[0]->result() );
		self::assertSame( Campaign_State::PROVIDER_DRAFT, $this->campaigns->get( $campaign->id() )?->state(), 'An unconfirmed test never makes the campaign unknown.' );

		$same_key = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $same_key->error()?->code() );
		self::assertSame( 1, $this->gateway->sends, 'The unconfirmed key is never sent again.' );

		// A duplicate test reaches only named test addresses, so a new key is allowed.
		self::assertTrue( $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-2', self::settings() )->is_success() );
		self::assertSame( 2, $this->gateway->sends );
	}

	public function test_a_provider_refusal_records_a_failed_attempt_and_settles_its_key(): void {
		$campaign                = $this->provider_draft_campaign();
		$this->gateway->outcomes = array( Test_Outcome::from_error( Provider_Error::from_category( Provider_Error_Category::VALIDATION, 'mailchimp_request_rejected', 'Mailchimp rejected the request.', 'mailchimp' ) ) );

		$refused = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $refused->error()?->code() );
		self::assertSame( array( 'failed', 'not_retryable' ), array( $refused->attempt()?->status(), $refused->attempt()?->retryability() ) );
		self::assertSame( 'failure', $this->events( $campaign->id(), 'campaign_test_send' )[0]->result() );

		$same_key = $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $same_key->error()?->code() );
		self::assertSame( 1, $this->gateway->sends );
	}

	public function test_a_durable_per_campaign_quota_bounds_tests_in_a_rolling_day(): void {
		$campaign = $this->provider_draft_campaign();
		for ( $i = 1; $i <= Campaign_Test_Delivery::QUOTA; ++$i ) {
			self::assertTrue( $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', "test-key-{$i}", self::settings() )->is_success() );
		}

		// Another tester, or a fresh transport limit, does not reset the campaign quota.
		$other   = new Campaign_Actor( 8, false, true, false, true );
		$limited = $this->delivery->send_test( $other, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-over', self::settings() );
		self::assertSame( Campaign_Workflow_Error::RATE_LIMITED, $limited->error()?->code() );
		self::assertSame( Campaign_Test_Delivery::QUOTA, $this->gateway->sends );
		self::assertNull( $this->attempts->find_idempotency( $campaign->id(), 'test_send', 'test-key-over' ), 'A refused request leaves no attempt behind.' );

		// The quota is rolling: once the earliest tests age out, testing resumes.
		$this->clock->now += Campaign_Test_Delivery::QUOTA_WINDOW + 1;
		self::assertTrue( $this->delivery->send_test( $other, $campaign->id(), array( 'qa@example.com' ), 'html', 'test-key-over', self::settings() )->is_success() );
		self::assertSame( Campaign_Test_Delivery::QUOTA + 1, $this->gateway->sends );
	}

	public function test_preconditions_are_checked_before_any_provider_call(): void {
		$campaign = $this->provider_draft_campaign();

		// Approval and production send authority do not grant test sends.
		$approver = new Campaign_Actor( 7, true, false, true, false );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $this->delivery->send_test( $approver, $campaign->id(), array( 'qa@example.com' ), 'html', 'k', self::settings() )->error()?->code() );
		$stranger = new Campaign_Actor( 9, true, false, true, true );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $this->delivery->send_test( $stranger, $campaign->id(), array( 'qa@example.com' ), 'html', 'k', self::settings() )->error()?->code(), 'Test authority still requires campaign ownership or management.' );

		self::assertSame( Campaign_Workflow_Error::NOT_FOUND, $this->delivery->send_test( $this->tester, 'campaign-missing', array( 'qa@example.com' ), 'html', 'k', self::settings() )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'html', '', self::settings() )->error()?->code() );
		foreach ( array( array(), array( 'not-an-address' ), array_fill( 0, 6, 'qa@example.com' ), array( 'a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com', 'f@example.com' ) ) as $recipients ) {
			self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->delivery->send_test( $this->tester, $campaign->id(), $recipients, 'html', 'k', self::settings() )->error()?->code() );
		}
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->delivery->send_test( $this->tester, $campaign->id(), array( 'qa@example.com' ), 'amp', 'k', self::settings() )->error()?->code() );

		$approved = $this->approved_campaign();
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $this->delivery->send_test( $this->tester, $approved->id(), array( 'qa@example.com' ), 'html', 'k', self::settings() )->error()?->code(), 'Without a remote draft there is nothing approved to test.' );

		$untargeted = $this->approved_campaign( null, null );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->delivery->send_test( $this->tester, $untargeted->id(), array( 'qa@example.com' ), 'html', 'k', self::settings() )->error()?->code() );

		self::assertSame( 0, $this->gateway->sends );
		self::assertSame( array(), $this->attempts->for_campaign( $campaign->id() ) );
		self::assertContains( 'denied', array_map( static fn ( Audit_Event $event ): string => $event->result(), $this->events( $campaign->id(), 'campaign_test_send' ) ) );
		$this->assert_no_recipient_is_stored( array( 'qa@example.com', 'not-an-address', 'f@example.com' ) );
	}

	private function approved_campaign( ?string $provider = 'mailchimp', ?string $audience = self::AUDIENCE ): Campaign {
		$created = $this->workflow->create( $this->tester, 7, $this->template_id, $provider, $audience )->campaign();
		self::assertNotNull( $created );
		self::assertTrue( $this->workflow->snapshot( $this->tester, $created->id(), 1 )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->tester, $created->id(), 2 )->is_success() );
		$approved = $this->workflow->approve( $this->tester, $created->id(), 3 )->campaign();
		self::assertSame( 'approved', $approved?->state() );

		return $approved;
	}

	/** An approved campaign whose confirmed remote draft is `mc0001`, as the draft handoff leaves it. */
	private function provider_draft_campaign(): Campaign {
		$approved = $this->approved_campaign();
		$draft    = $approved->transition_to( Campaign_State::PROVIDER_DRAFT, $this->clock->now() );
		self::assertTrue( $this->campaigns->compare_and_swap( $draft, $approved->version() ) );
		self::assertTrue(
			$this->references->add(
				Remote_Campaign_Reference::from_array(
					array(
						'schema_version' => Remote_Campaign_Reference::SCHEMA_VERSION,
						'campaign_id'    => $approved->id(),
						'provider'       => 'mailchimp',
						'remote_id'      => 'mc0001',
						'observed_state' => Campaign_Draft_Handoff::OBSERVED_DRAFT,
						'cursor'         => null,
						'observed_at'    => $this->clock->now(),
						'reconciled_at'  => null,
					)
				)
			)
		);

		return $draft;
	}

	/** @return array<int, Audit_Event> */
	private function events( string $campaign_id, string $action ): array {
		return array_values( array_filter( $this->audits->for_target( 'campaign', $campaign_id ), static fn ( Audit_Event $event ): bool => $action === $event->action() ) );
	}

	/** @param array<int, string> $recipients Addresses that must not appear in any CampaignBridge table. */
	private function assert_no_recipient_is_stored( array $recipients ): void {
		global $wpdb;
		foreach ( Schema_Manager::table_names() as $table ) {
			$rows = (string) wp_json_encode( $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Allowlisted isolated test tables.
			foreach ( $recipients as $recipient ) {
				self::assertStringNotContainsStringIgnoringCase( $recipient, $rows, $table );
			}
		}
	}

	private function truncate(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
	}
}
