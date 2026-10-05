<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile,CampaignBridge.Standard.Sniffs.Database
/**
 * Campaign scheduling integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Source;
use CampaignBridge\Domain\Campaign\Delivery_Policy;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Provider_Delivery_Gateway;
use CampaignBridge\Domain\Provider\Remote_Draft_State;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Providers\Mailchimp_Token_Mapper;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Campaign_Template_Input_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Tests\Support\Campaign\Fixed_Delivery_Policy;
use CampaignBridge\Tests\Support\Provider\Scripted_Draft_Gateway;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Draft_Handoff;
use CampaignBridge\Workflow\Campaign\Campaign_Review_Input_Capture;
use CampaignBridge\Workflow\Campaign\Campaign_Scheduler;
use CampaignBridge\Workflow\Campaign\Campaign_Template_Authority;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

/** Stands in for the provider: counts delivery actions and replays scripted outcomes. */
final class Scripted_Delivery_Gateway implements Provider_Delivery_Gateway {
	/** @var array<int, array{action: string, remote_id: string, time: string|null}> */
	public array $calls = array();

	/** @var array<int, Action_Outcome> Outcomes for successive actions; empty means accepted. */
	public array $outcomes = array();

	/** @var (\Closure(): void)|null Runs while the remote action is in flight. */
	public ?\Closure $during_call = null;

	public function slug(): string {
		return 'mailchimp';
	}

	public function schedule_interval_minutes(): int {
		return 15;
	}

	public function schedule( array $settings, string $remote_id, string $scheduled_for ): Action_Outcome {
		return $this->record( 'schedule', $remote_id, $scheduled_for );
	}

	public function unschedule( array $settings, string $remote_id ): Action_Outcome {
		return $this->record( 'unschedule', $remote_id, null );
	}

	public function send( array $settings, string $remote_id ): Action_Outcome {
		return $this->record( 'send', $remote_id, null );
	}

	private function record( string $action, string $remote_id, ?string $time ): Action_Outcome {
		$this->calls[] = array(
			'action'    => $action,
			'remote_id' => $remote_id,
			'time'      => $time,
		);
		if ( null !== $this->during_call ) {
			$during            = $this->during_call;
			$this->during_call = null;
			$during();
		}

		return array_shift( $this->outcomes ) ?? Action_Outcome::accepted();
	}
}

/** Hides earlier attempts, simulating a request that read before a concurrent claim committed. */
final class Stale_Attempt_View implements Delivery_Attempt_Source {
	public function __construct( private readonly Delivery_Attempt_Source $inner ) {}

	public function get( string $id ): ?Delivery_Attempt {
		return $this->inner->get( $id );
	}

	public function find_idempotency( string $campaign_id, string $operation, string $idempotency_key ): ?Delivery_Attempt {
		return $this->inner->find_idempotency( $campaign_id, $operation, $idempotency_key );
	}

	public function add( Delivery_Attempt $attempt ): bool {
		return $this->inner->add( $attempt );
	}

	public function update_result( Delivery_Attempt $attempt ): bool {
		return $this->inner->update_result( $attempt );
	}

	public function for_campaign( string $campaign_id, int $limit = 50, int $offset = 0 ): array {
		return array();
	}

	public function count_for_campaign( string $campaign_id ): int {
		return 0;
	}
}

/** Serves one stale read, as a request that loaded the campaign before a concurrent claim committed. */
final class Stale_Campaign_View implements Campaign_Source {
	public function __construct( private readonly Campaign_Source $inner, private ?Campaign $stale ) {}

	public function get( string $id ): ?Campaign {
		$stale       = $this->stale;
		$this->stale = null;

		return $stale ?? $this->inner->get( $id );
	}

	public function add( Campaign $campaign ): bool {
		return $this->inner->add( $campaign );
	}

	public function compare_and_swap( Campaign $replacement, int $expected_version ): bool {
		return $this->inner->compare_and_swap( $replacement, $expected_version );
	}

	public function for_owner( int $owner_user_id, int $limit = 50, int $offset = 0, ?\CampaignBridge\Domain\Campaign\Campaign_List_Filter $filter = null ): array {
		return $this->inner->for_owner( $owner_user_id, $limit, $offset, $filter );
	}

	public function count_for_owner( int $owner_user_id, ?\CampaignBridge\Domain\Campaign\Campaign_List_Filter $filter = null ): int {
		return $this->inner->count_for_owner( $owner_user_id, $filter );
	}
}

/** A clock tests can move. */
final class Scheduler_Clock implements Campaign_Clock {
	public int $now;

	public function __construct() {
		$this->now = (int) strtotime( '2026-10-05T12:00:00Z' );
	}

	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->now );
	}
}

/** Grants every template so these tests exercise scheduling rules only. */
final class Scheduler_Template_Authority implements Campaign_Template_Authority {
	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool {
		return true;
	}
}

/** Proves scheduling reaches the provider at most once and never pretends about the outcome. */
final class Campaign_Scheduler_Test extends Test_Case {
	private const AUDIENCE = 'abc123';

	private const SEND_AT = '2026-10-05T15:00:00Z';

	private Campaign_Repository $campaigns;

	private Campaign_Snapshot_Repository $snapshots;

	private Remote_Campaign_Reference_Repository $references;

	private Delivery_Attempt_Repository $attempts;

	private Audit_Event_Repository $audits;

	private Campaign_Workflow $workflow;

	private Scripted_Delivery_Gateway $gateway;

	private Scripted_Draft_Gateway $drafts;

	private Fixed_Delivery_Policy $policies;

	private Scheduler_Clock $clock;

	private Campaign_Scheduler $scheduler;

	private Campaign_Actor $sender;

	/** @var array<int, int> */
	private array $template_ids = array();

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
		$this->gateway    = new Scripted_Delivery_Gateway();
		$this->drafts     = new Scripted_Draft_Gateway();
		$this->policies   = new Fixed_Delivery_Policy();
		$this->clock      = new Scheduler_Clock();
		$this->sender     = new Campaign_Actor( 7, true, false, true, false );
		$this->workflow   = new Campaign_Workflow(
			$this->campaigns,
			$this->snapshots,
			$this->audits,
			new Campaign_Review_Input_Capture( new Campaign_Template_Input_Repository(), new Post_Snapshot_Repository(), new Brand_Kit_Repository() ),
			new Scheduler_Template_Authority(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock
		);
		$this->scheduler  = $this->scheduler( $this->attempts );
		kses_remove_filters();
		$this->cache_merge_fields();
		// The remote draft as the handoff left it.
		$this->drafts->remote_audience = self::AUDIENCE;
	}

	/** The workflow commits real transactions, so shared fixtures are removed explicitly. */
	public function tearDown(): void {
		global $wpdb;
		kses_init_filters();
		$this->clear_merge_fields();
		foreach ( $this->template_ids as $template_id ) {
			wp_delete_post( $template_id, true );
		}
		$this->truncate();
		// The harness runs with autocommit off, so cleanup of committed fixtures must commit too.
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	public function test_a_provider_draft_is_scheduled_once_and_audited(): void {
		$campaign = $this->provider_draft_campaign();
		$snapshot = $this->snapshots->get( (string) $campaign->active_snapshot_id() );

		$result = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), '2026-10-05T08:00:00-07:00', self::AUDIENCE, 'schedule-1', self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertSame(
			array(
				array(
					'action'    => 'schedule',
					'remote_id' => 'mc0001',
					'time'      => self::SEND_AT,
				),
			),
			$this->gateway->calls 
		);
		$stored = $this->campaigns->get( $campaign->id() );
		self::assertSame( array( 'scheduled', self::SEND_AT, $campaign->version() + 2 ), array( $stored?->state(), $stored?->scheduled_for(), $stored?->version() ) );
		self::assertSame( $stored?->to_array(), $result->campaign()?->to_array() );
		self::assertSame( Campaign_Scheduler::OBSERVED_SCHEDULED, $this->references->get( $campaign->id(), 'mailchimp' )?->observed_state() );

		// The approved draft was re-asserted and read back before anything was scheduled.
		self::assertSame( array( 1, 2 ), array( $this->drafts->syncs, $this->drafts->inspections ) );
		self::assertSame( array( self::AUDIENCE, 'Spring sale', $snapshot?->artifact()->fingerprint() ), array( $this->drafts->contents[0]->audience_id(), $this->drafts->contents[0]->subject(), $this->drafts->contents[0]->fingerprint() ) );
		self::assertStringContainsString( '*|FNAME|*', $this->drafts->contents[0]->html(), 'Re-asserted content is translated for the provider.' );

		$attempt = $this->delivery_attempts( $campaign->id() )[0];
		self::assertSame( array( 'schedule', 'schedule-1', 'succeeded', 'mc0001' ), array( $attempt->operation(), $attempt->idempotency_key(), $attempt->status(), $attempt->remote_correlation() ) );

		// Audit identifies actor, operation, artifact, remote reference, and normalized result.
		$event   = $this->events( $campaign->id(), 'campaign_schedule' )[0];
		$context = $event->context()->to_array();
		self::assertSame( array( 7, 'success' ), array( $event->actor_user_id(), $event->result() ) );
		self::assertSame(
			array( 'mailchimp', 'mc0001', $attempt->id(), $snapshot?->id(), $snapshot?->artifact()->fingerprint(), self::SEND_AT, 'provider_draft', 'scheduled' ),
			array( $context['provider'], $context['remote_id'], $context['attempt_id'], $context['snapshot_id'], $context['fingerprint'], $context['scheduled_for'], $context['from_state'], $context['to_state'] )
		);
	}

	/** @return array<string, array{\Closure(Scripted_Draft_Gateway): void, string}> */
	public static function drifted_drafts(): array {
		return array(
			'scheduled in the provider' => array( static fn ( Scripted_Draft_Gateway $drafts ) => $drafts->remote_status = Remote_Draft_State::SCHEDULED, Campaign_Workflow_Error::RECONCILIATION_REQUIRED ),
			'sent in the provider'      => array( static fn ( Scripted_Draft_Gateway $drafts ) => $drafts->remote_status = Remote_Draft_State::SENT, Campaign_Workflow_Error::RECONCILIATION_REQUIRED ),
			'segmented in the provider' => array( static fn ( Scripted_Draft_Gateway $drafts ) => $drafts->remote_segmented = true, Campaign_Workflow_Error::RECONCILIATION_REQUIRED ),
			'audience not re-asserted'  => array(
				static function ( Scripted_Draft_Gateway $drafts ): void {
					$drafts->remote_audience       = 'other-audience';
					$drafts->sync_ignores_audience = true;
				},
				Campaign_Workflow_Error::RECONCILIATION_REQUIRED,
			),
			'draft deleted'             => array( static fn ( Scripted_Draft_Gateway $drafts ) => $drafts->sync_outcomes = array( Action_Outcome::from_error( Provider_Error::from_category( Provider_Error_Category::NOT_FOUND, 'mailchimp_not_found', 'Not found.', 'mailchimp' ) ) ), Campaign_Workflow_Error::PROVIDER_FAILED ),
			'draft unreadable'          => array( static fn ( Scripted_Draft_Gateway $drafts ) => $drafts->inspect_error = Provider_Error::timeout( 'mailchimp_connection_timeout', 'Timed out.', 'mailchimp' ), Campaign_Workflow_Error::PROVIDER_FAILED ),
		);
	}

	/**
	 * @dataProvider drifted_drafts
	 * @param \Closure(Scripted_Draft_Gateway): void $drift How the remote draft changed outside CampaignBridge.
	 */
	public function test_a_draft_changed_outside_campaignbridge_never_reaches_the_audience( \Closure $drift, string $code ): void {
		$campaign = $this->provider_draft_campaign();
		$drift( $this->drafts );

		$refused = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );

		self::assertSame( $code, $refused->error()?->code() );
		self::assertSame( array(), $this->gateway->calls, 'Nothing was scheduled.' );
		self::assertSame( array(), $this->delivery_attempts( $campaign->id() ), 'No attempt is left to reconcile.' );
		self::assertSame( $campaign->to_array(), $this->campaigns->get( $campaign->id() )?->to_array(), 'No version was consumed.' );
	}

	public function test_a_scheduled_or_sent_draft_is_never_overwritten(): void {
		$campaign                    = $this->provider_draft_campaign();
		$this->drafts->remote_status = Remote_Draft_State::SCHEDULED;

		$this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );

		self::assertSame( 0, $this->drafts->syncs, 'A campaign the provider already scheduled is not modified.' );
	}

	public function test_separate_delivery_stops_the_approver_and_allows_a_second_person(): void {
		$campaign = $this->provider_draft_campaign();
		self::assertSame( 7, $campaign->approved_by_user_id(), 'Approval records the approver.' );
		$this->policies->policy = Delivery_Policy::from_settings( true, '' );

		$denied = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $denied->error()?->code() );
		self::assertStringContainsString( 'Separation of duties', (string) $denied->error()?->message() );
		self::assertSame( array( array(), 0 ), array( $this->gateway->calls, $this->drafts->syncs ), 'The approver reaches no provider call.' );
		self::assertSame( array(), $this->delivery_attempts( $campaign->id() ) );
		$event = $this->events( $campaign->id(), 'campaign_schedule' )[0];
		self::assertSame( array( 'denied', 'separate_delivery' ), array( $event->result(), $event->context()->to_array()['policy'] ?? null ) );

		$second    = new Campaign_Actor( 8, false, true, true, false );
		$scheduled = $this->scheduler->schedule( $second, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-2', self::settings() );
		self::assertTrue( $scheduled->is_success() );
		self::assertSame( 7, $scheduled->campaign()?->approved_by_user_id() );

		// Unscheduling stops delivery, so the approver may still do it.
		self::assertTrue( $this->scheduler->unschedule( $this->sender, $campaign->id(), (int) $scheduled->campaign()?->version(), 'unschedule-1', self::settings() )->is_success() );
	}

	public function test_separate_delivery_fails_closed_without_a_recorded_approver(): void {
		$campaign = $this->provider_draft_campaign();
		$legacy   = Campaign::from_array(
			array_merge(
				$campaign->to_array(),
				array(
					'approved_by_user_id' => null,
					'version'             => $campaign->version() + 1,
				)
			)
		);
		self::assertTrue( $this->campaigns->compare_and_swap( $legacy, $campaign->version() ) );
		$this->policies->policy = Delivery_Policy::from_settings( true, '' );

		$denied = $this->scheduler->schedule( new Campaign_Actor( 8, false, true, true, false ), $campaign->id(), $legacy->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $denied->error()?->code() );
		self::assertStringContainsString( 'approved before approvers were recorded', (string) $denied->error()?->message() );
		self::assertSame( array(), $this->gateway->calls );
	}

	public function test_repeats_and_double_clicks_never_reach_the_provider_twice(): void {
		$campaign = $this->provider_draft_campaign();
		$first    = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );

		$replay = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );
		self::assertTrue( $replay->is_idempotent_replay() );
		self::assertSame( $first->attempt()?->id(), $replay->attempt()?->id() );

		$second = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-2', self::settings() );
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $second->error()?->code(), 'A scheduled campaign cannot be scheduled again.' );
		self::assertCount( 1, $this->gateway->calls );
		self::assertCount( 1, $this->delivery_attempts( $campaign->id() ) );
	}

	public function test_a_concurrent_request_is_refused_while_one_is_in_flight(): void {
		$campaign = $this->provider_draft_campaign();
		$inner    = null;
		// A double-click: a second request arrives while the first is waiting on the provider.
		$this->gateway->during_call = function () use ( $campaign, &$inner ): void {
			$inner = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-2', self::settings() );
		};

		self::assertTrue( $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->is_success() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $inner?->error()?->code() );
		self::assertCount( 1, $this->gateway->calls );
	}

	public function test_the_version_claim_stops_a_race_the_pending_check_missed(): void {
		$campaign = $this->provider_draft_campaign();
		// The racer read the campaign and its attempts before the first request's claim committed.
		$racer                      = $this->scheduler( new Stale_Attempt_View( $this->attempts ), new Stale_Campaign_View( $this->campaigns, $campaign ) );
		$inner                      = null;
		$this->gateway->during_call = function () use ( $racer, $campaign, &$inner ): void {
			$inner = $racer->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-2', self::settings() );
		};

		self::assertTrue( $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->is_success() );
		self::assertSame( Campaign_Workflow_Error::CONFLICT, $inner?->error()?->code(), 'The claim consumed the version the racer held.' );
		self::assertCount( 1, $this->gateway->calls );
		self::assertNull( $this->attempts->find_idempotency( $campaign->id(), 'schedule', 'schedule-2' ), 'The racer\'s attempt rolled back with its failed claim.' );
	}

	public function test_an_unconfirmed_schedule_makes_the_campaign_unknown_and_blocks_delivery(): void {
		$campaign                = $this->provider_draft_campaign();
		$this->gateway->outcomes = array( Action_Outcome::from_error( Provider_Error::timeout( 'mailchimp_connection_timeout', 'Mailchimp request timed out.', 'mailchimp' ) ) );

		$unknown = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $unknown->error()?->code() );
		self::assertSame( array( 'unknown', 'unknown' ), array( $unknown->attempt()?->status(), $unknown->attempt()?->retryability() ) );
		self::assertSame( Campaign_State::UNKNOWN, $this->campaigns->get( $campaign->id() )?->state(), 'A timeout is not evidence that nothing will send.' );
		self::assertSame( 'unknown', $this->events( $campaign->id(), 'campaign_schedule' )[0]->result() );

		$current = $this->campaigns->get( $campaign->id() );
		foreach ( array( 'schedule-1', 'schedule-2' ) as $key ) {
			$blocked = $this->scheduler->schedule( $this->sender, $campaign->id(), (int) $current?->version(), self::SEND_AT, self::AUDIENCE, $key, self::settings() );
			self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $blocked->error()?->code(), $key );
		}
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $this->scheduler->unschedule( $this->sender, $campaign->id(), (int) $current?->version(), 'unschedule-1', self::settings() )->error()?->code() );
		self::assertCount( 1, $this->gateway->calls, 'An unconfirmed schedule is never retried, whatever key is sent.' );
	}

	public function test_a_definite_refusal_changes_nothing_and_keeps_its_retryability(): void {
		$campaign                = $this->provider_draft_campaign();
		$this->gateway->outcomes = array( Action_Outcome::from_error( Provider_Error::from_category( Provider_Error_Category::RATE_LIMITED, 'mailchimp_rate_limited', 'Mailchimp rate limit reached.', 'mailchimp' ) ) );

		$refused = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $refused->error()?->code() );
		self::assertSame( array( 'failed', 'retryable' ), array( $refused->attempt()?->status(), $refused->attempt()?->retryability() ) );
		$after = $this->campaigns->get( $campaign->id() );
		self::assertSame( array( 'provider_draft', null, $campaign->version() + 1 ), array( $after?->state(), $after?->scheduled_for(), $after?->version() ), 'Only the claim consumed a version.' );
		self::assertSame( $after?->version(), $refused->campaign()?->version() );

		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $this->scheduler->schedule( $this->sender, $campaign->id(), (int) $after?->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->error()?->code() );
		self::assertTrue( $this->scheduler->schedule( $this->sender, $campaign->id(), (int) $after?->version(), self::SEND_AT, self::AUDIENCE, 'schedule-2', self::settings() )->is_success() );
		self::assertCount( 2, $this->gateway->calls );
	}

	public function test_unscheduling_returns_the_campaign_to_its_draft_before_the_send_time(): void {
		$campaign  = $this->provider_draft_campaign();
		$scheduled = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->campaign();

		$result = $this->scheduler->unschedule( $this->sender, $campaign->id(), (int) $scheduled?->version(), 'unschedule-1', self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertSame( array( 'schedule', 'unschedule' ), array_column( $this->gateway->calls, 'action' ) );
		$stored = $this->campaigns->get( $campaign->id() );
		self::assertSame( array( 'provider_draft', null ), array( $stored?->state(), $stored?->scheduled_for() ) );
		self::assertSame( Campaign_Draft_Handoff::OBSERVED_DRAFT, $this->references->get( $campaign->id(), 'mailchimp' )?->observed_state() );
		self::assertSame( self::SEND_AT, $this->events( $campaign->id(), 'campaign_unschedule' )[0]->context()->to_array()['scheduled_for'] );

		// The campaign can be scheduled again with a new key.
		self::assertTrue( $this->scheduler->schedule( $this->sender, $campaign->id(), (int) $stored?->version(), '2026-10-05T16:00:00Z', self::AUDIENCE, 'schedule-2', self::settings() )->is_success() );
	}

	public function test_unscheduling_after_the_send_time_is_refused_without_a_provider_call(): void {
		$campaign         = $this->provider_draft_campaign();
		$scheduled        = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->campaign();
		$this->clock->now = (int) strtotime( self::SEND_AT );

		$late = $this->scheduler->unschedule( $this->sender, $campaign->id(), (int) $scheduled?->version(), 'unschedule-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $late->error()?->code() );
		self::assertStringContainsString( 'may already be sending', (string) $late->error()?->message() );
		self::assertCount( 1, $this->gateway->calls );
		self::assertSame( 'scheduled', $this->campaigns->get( $campaign->id() )?->state() );
	}

	public function test_a_provider_draft_is_sent_once_and_audited(): void {
		$campaign = $this->provider_draft_campaign();
		$snapshot = $this->snapshots->get( (string) $campaign->active_snapshot_id() );

		$result = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-1', self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertSame(
			array(
				array(
					'action'    => 'send',
					'remote_id' => 'mc0001',
					'time'      => null,
				),
			),
			$this->gateway->calls
		);
		$stored = $this->campaigns->get( $campaign->id() );
		self::assertSame( array( 'sending', $campaign->version() + 2 ), array( $stored?->state(), $stored?->version() ), 'Accepted is not delivered: reconciliation records sent.' );
		self::assertSame( Campaign_Scheduler::OBSERVED_SENDING, $this->references->get( $campaign->id(), 'mailchimp' )?->observed_state() );
		self::assertSame( array( 1, 2 ), array( $this->drafts->syncs, $this->drafts->inspections ), 'The approved draft was re-asserted and verified first.' );

		$attempt = $this->delivery_attempts( $campaign->id() )[0];
		self::assertSame( array( 'send', 'send-1', 'succeeded' ), array( $attempt->operation(), $attempt->idempotency_key(), $attempt->status() ) );
		$context = $this->events( $campaign->id(), 'campaign_send' )[0]->context()->to_array();
		self::assertSame( array( 'mc0001', $snapshot?->artifact()->fingerprint(), 'provider_draft', 'sending' ), array( $context['remote_id'], $context['fingerprint'], $context['from_state'], $context['to_state'] ) );
	}

	/**
	 * @dataProvider drifted_drafts
	 * @param \Closure(Scripted_Draft_Gateway): void $drift How the remote draft changed outside CampaignBridge.
	 */
	public function test_a_draft_changed_outside_campaignbridge_is_never_sent( \Closure $drift, string $code ): void {
		$campaign = $this->provider_draft_campaign();
		$drift( $this->drafts );

		$refused = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-1', self::settings() );

		self::assertSame( $code, $refused->error()?->code() );
		self::assertSame( array(), $this->gateway->calls, 'Nothing was sent.' );
		self::assertSame( array(), $this->delivery_attempts( $campaign->id() ) );
	}

	public function test_sending_requires_the_confirmed_audience_and_respects_separate_delivery(): void {
		$campaign = $this->provider_draft_campaign();

		$wrong = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), 'other-audience', 'send-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $wrong->error()?->code() );
		self::assertStringContainsString( 'Nothing was sent', (string) $wrong->error()?->message() );

		$this->policies->policy = Delivery_Policy::from_settings( true, '' );
		$denied                 = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-2', self::settings() );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $denied->error()?->code() );
		self::assertStringContainsString( 'cannot send it', (string) $denied->error()?->message() );
		self::assertSame( array( array(), 0 ), array( $this->gateway->calls, $this->drafts->syncs ) );

		$tester = new Campaign_Actor( 9, true, false, false, true );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $this->scheduler->send( $tester, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-3', self::settings() )->error()?->code(), 'Test authority is not send authority.' );

		self::assertTrue( $this->scheduler->send( new Campaign_Actor( 8, false, true, true, false ), $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-4', self::settings() )->is_success() );
		self::assertCount( 1, $this->gateway->calls );
	}

	public function test_repeats_double_clicks_and_races_never_send_twice(): void {
		$campaign = $this->provider_draft_campaign();
		$inner    = null;
		$racer    = $this->scheduler( new Stale_Attempt_View( $this->attempts ), new Stale_Campaign_View( $this->campaigns, $campaign ) );
		$raced    = null;
		$this->gateway->during_call = function () use ( $campaign, $racer, &$inner, &$raced ): void {
			$inner = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-2', self::settings() );
			$raced = $racer->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-3', self::settings() );
		};

		$first = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-1', self::settings() );
		self::assertTrue( $first->is_success() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $inner?->error()?->code(), 'A double-click waits behind the in-flight send.' );
		self::assertContains( $raced?->error()?->code(), array( Campaign_Workflow_Error::CONFLICT, Campaign_Workflow_Error::RECONCILIATION_REQUIRED ), 'The version claim stops a request that missed the pending check.' );

		$replay = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-1', self::settings() );
		self::assertTrue( $replay->is_idempotent_replay() );
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $this->scheduler->send( $this->sender, $campaign->id(), (int) $first->campaign()?->version(), self::AUDIENCE, 'send-4', self::settings() )->error()?->code(), 'A sending campaign cannot be sent again.' );
		self::assertCount( 1, $this->gateway->calls );
	}

	public function test_an_unconfirmed_send_makes_the_campaign_unknown_and_blocks_all_delivery(): void {
		$campaign                = $this->provider_draft_campaign();
		$this->gateway->outcomes = array( Action_Outcome::from_error( Provider_Error::timeout( 'mailchimp_connection_timeout', 'Mailchimp request timed out.', 'mailchimp' ) ) );

		$unknown = $this->scheduler->send( $this->sender, $campaign->id(), $campaign->version(), self::AUDIENCE, 'send-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $unknown->error()?->code() );
		self::assertSame( Campaign_State::UNKNOWN, $this->campaigns->get( $campaign->id() )?->state(), 'A timed-out send may have reached the audience.' );
		$current = (int) $this->campaigns->get( $campaign->id() )?->version();
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $this->scheduler->send( $this->sender, $campaign->id(), $current, self::AUDIENCE, 'send-2', self::settings() )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $this->scheduler->schedule( $this->sender, $campaign->id(), $current, self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->error()?->code() );
		self::assertCount( 1, $this->gateway->calls, 'An unconfirmed send is never retried, whatever key is sent.' );
	}

	public function test_a_scheduled_campaign_must_be_unscheduled_before_it_is_sent(): void {
		$campaign  = $this->provider_draft_campaign();
		$scheduled = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() )->campaign();

		$refused = $this->scheduler->send( $this->sender, $campaign->id(), (int) $scheduled?->version(), self::AUDIENCE, 'send-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $refused->error()?->code() );
		self::assertSame( 'Only a provider draft campaign can be sent.', $refused->error()?->message() );
		self::assertSame( array( 'schedule' ), array_column( $this->gateway->calls, 'action' ) );
	}

	public function test_preconditions_are_checked_before_any_provider_call(): void {
		$campaign = $this->provider_draft_campaign();
		$version  = $campaign->version();
		$schedule = fn ( Campaign_Actor $actor, string $time, string $confirm = self::AUDIENCE, int $expected = 0, string $id = '' ): ?string => $this->scheduler->schedule( $actor, '' === $id ? $campaign->id() : $id, 0 === $expected ? $version : $expected, $time, $confirm, 'schedule-1', self::settings() )->error()?->code();

		// Test authority and ownership without delivery authority do not schedule.
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $schedule( new Campaign_Actor( 7, true, false, false, true ), self::SEND_AT ) );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $schedule( new Campaign_Actor( 9, true, false, true, false ), self::SEND_AT ) );
		self::assertSame( Campaign_Workflow_Error::NOT_FOUND, $schedule( $this->sender, self::SEND_AT, self::AUDIENCE, 0, 'campaign-missing' ) );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $schedule( $this->sender, self::SEND_AT, 'other-audience' ), 'The confirmation must name this campaign\'s audience.' );
		self::assertSame( Campaign_Workflow_Error::CONFLICT, $schedule( $this->sender, self::SEND_AT, self::AUDIENCE, $version - 1 ) );
		foreach ( array( '2026-10-05T15:00:00', '2026-10-05T15:05:00Z', '2026-10-05T11:00:00Z', '2028-01-01T00:00:00Z' ) as $time ) {
			self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $schedule( $this->sender, $time ), $time );
		}

		$approved = $this->approved_campaign();
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $schedule( $this->sender, self::SEND_AT, self::AUDIENCE, $approved->version(), $approved->id() ), 'Without a remote draft there is nothing to schedule.' );
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $this->scheduler->unschedule( $this->sender, $campaign->id(), $version, 'unschedule-1', self::settings() )->error()?->code() );

		$incomplete = $this->provider_draft_campaign( array( 'campaignbridge_subject' => 'Spring sale' ) );
		self::assertSame( Campaign_Workflow_Error::VALIDATION_FAILED, $schedule( $this->sender, self::SEND_AT, self::AUDIENCE, $incomplete->version(), $incomplete->id() ), 'An incomplete envelope cannot reach the audience.' );

		self::assertSame( array(), $this->gateway->calls );
		self::assertSame( array(), $this->delivery_attempts( $campaign->id() ) );
		self::assertSame( $version, $this->campaigns->get( $campaign->id() )?->version(), 'A refusal consumes no version.' );
		self::assertContains( 'denied', array_map( static fn ( Audit_Event $event ): string => $event->result(), $this->events( $campaign->id(), 'campaign_schedule' ) ) );
	}

	private function scheduler( Delivery_Attempt_Source $attempts, ?Campaign_Source $campaigns = null ): Campaign_Scheduler {
		return new Campaign_Scheduler(
			$campaigns ?? $this->campaigns,
			$this->snapshots,
			$this->references,
			$attempts,
			$this->audits,
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock,
			$this->gateway,
			( new Mailchimp_Provider() )->capabilities(),
			$this->drafts,
			new Mailchimp_Token_Mapper(),
			new Provider_Discovery_Service( new Mailchimp_Discovery(), new Provider_Discovery_Repository(), new System_Clock() ),
			$this->policies
		);
	}

	/** @param array<string, string>|null $meta Envelope meta; null uses a complete envelope. */
	private function approved_campaign( ?array $meta = null ): Campaign {
		$template             = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Scheduler template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello {{cb:subscriber.first_name}}, <a href="{{cb:campaign.unsubscribe_url}}">unsubscribe</a></p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			)
		);
		$this->template_ids[] = $template;
		$meta                 = $meta ?? array(
			'campaignbridge_subject'      => 'Spring sale',
			'campaignbridge_sender_name'  => 'Example Shop',
			'campaignbridge_sender_email' => 'news@example.com',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $template, $key, $value );
		}

		$created = $this->workflow->create( $this->sender, 7, $template, 'mailchimp', self::AUDIENCE )->campaign();
		self::assertNotNull( $created );
		self::assertTrue( $this->workflow->snapshot( $this->sender, $created->id(), 1 )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->sender, $created->id(), 2 )->is_success() );
		$approved = $this->workflow->approve( $this->sender, $created->id(), 3 )->campaign();
		self::assertSame( 'approved', $approved?->state() );

		return $approved;
	}

	/**
	 * An approved campaign whose confirmed remote draft is `mc0001`, as the draft handoff leaves it.
	 *
	 * @param array<string, string>|null $meta Envelope meta; null uses a complete envelope.
	 */
	private function provider_draft_campaign( ?array $meta = null ): Campaign {
		$approved = $this->approved_campaign( $meta );
		$draft    = $approved->transition_to( Campaign_State::PROVIDER_DRAFT, $this->clock->now() );
		self::assertTrue( $this->campaigns->compare_and_swap( $draft, $approved->version() ) );
		self::assertTrue(
			$this->references->add(
				Remote_Campaign_Reference::from_array(
					array(
						'schema_version' => Remote_Campaign_Reference::SCHEMA_VERSION,
						'campaign_id'    => $approved->id(),
						'provider'       => 'mailchimp',
						'remote_id'      => null === $meta ? 'mc0001' : 'mc' . substr( md5( $approved->id() ), 0, 8 ),
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

	/** @return array<int, Delivery_Attempt> */
	private function delivery_attempts( string $campaign_id ): array {
		return $this->attempts->for_campaign( $campaign_id );
	}

	/** @return array<int, Audit_Event> */
	private function events( string $campaign_id, string $action ): array {
		return array_values( array_filter( $this->audits->for_target( 'campaign', $campaign_id ), static fn ( Audit_Event $event ): bool => $action === $event->action() ) );
	}

	private function cache_merge_fields(): void {
		$this->clear_merge_fields();
		( new Provider_Discovery_Repository() )->save(
			(string) ( new Mailchimp_Discovery() )->account_key( self::settings() ),
			Discovery_Result::create(
				'mailchimp',
				self::AUDIENCE,
				Discovery_Batch::create( Discovery_Kind::MERGE_FIELDS, array( Discovered_Merge_Field::create( 'FNAME', 'FNAME', 'text', false ) ), true ),
				gmdate( 'Y-m-d\\TH:i:s\\Z' )
			)
		);
	}

	private function clear_merge_fields(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\\_%'" );
		wp_cache_flush();
	}

	private function truncate(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
	}
}
