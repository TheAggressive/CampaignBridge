<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile
/**
 * Campaign reconciliation tests.
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
use CampaignBridge\Domain\Campaign\Delivery_Operation;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Remote_Draft_Matches;
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
use CampaignBridge\Workflow\Campaign\Campaign_Draft_Handoff;
use CampaignBridge\Workflow\Campaign\Campaign_Reconciler;
use CampaignBridge\Workflow\Campaign\Campaign_Review_Input_Capture;
use CampaignBridge\Workflow\Campaign\Campaign_Scheduler;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

/** Lets a concurrent request change the campaign just before reconciliation writes. */
final class Racing_Campaign_Source implements Campaign_Source {
	/** @var (\Closure(): void)|null */
	public ?\Closure $before_write = null;

	public function __construct( private readonly Campaign_Source $inner ) {}

	public function get( string $id ): ?Campaign {
		return $this->inner->get( $id );
	}

	public function add( Campaign $campaign ): bool {
		return $this->inner->add( $campaign );
	}

	public function compare_and_swap( Campaign $replacement, int $expected_version ): bool {
		if ( null !== $this->before_write ) {
			$race               = $this->before_write;
			$this->before_write = null;
			$race();
		}

		return $this->inner->compare_and_swap( $replacement, $expected_version );
	}

	public function for_owner( int $owner_user_id, int $limit = 50, int $offset = 0 ): array {
		return $this->inner->for_owner( $owner_user_id, $limit, $offset );
	}

	public function count_for_owner( int $owner_user_id ): int {
		return $this->inner->count_for_owner( $owner_user_id );
	}
}

/** Proves reconciliation settles outcomes only from provider evidence and never mutates the provider. */
final class Campaign_Reconciler_Test extends Test_Case {
	private const AUDIENCE = 'abc123';

	private const SEND_AT = '2026-10-05T15:00:00Z';

	private Campaign_Repository $campaigns;

	private Racing_Campaign_Source $racing;

	private Remote_Campaign_Reference_Repository $references;

	private Delivery_Attempt_Repository $attempts;

	private Audit_Event_Repository $audits;

	private Campaign_Workflow $workflow;

	private Scripted_Delivery_Gateway $gateway;

	private Scripted_Draft_Gateway $drafts;

	private Scheduler_Clock $clock;

	private Campaign_Scheduler $scheduler;

	private Campaign_Reconciler $reconciler;

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
		$this->racing     = new Racing_Campaign_Source( $this->campaigns );
		$this->references = new Remote_Campaign_Reference_Repository();
		$this->attempts   = new Delivery_Attempt_Repository();
		$this->audits     = new Audit_Event_Repository();
		$this->gateway    = new Scripted_Delivery_Gateway();
		$this->drafts     = new Scripted_Draft_Gateway();
		$this->clock      = new Scheduler_Clock();
		$this->sender     = new Campaign_Actor( 7, true, false, true, false );
		$snapshots        = new Campaign_Snapshot_Repository();
		$this->workflow   = new Campaign_Workflow(
			$this->campaigns,
			$snapshots,
			$this->audits,
			new Campaign_Review_Input_Capture( new Campaign_Template_Input_Repository(), new Post_Snapshot_Repository(), new Brand_Kit_Repository() ),
			new Scheduler_Template_Authority(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock
		);
		$this->scheduler  = new Campaign_Scheduler(
			$this->campaigns,
			$snapshots,
			$this->references,
			$this->attempts,
			$this->audits,
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock,
			$this->gateway,
			( new Mailchimp_Provider() )->capabilities(),
			$this->drafts,
			new Mailchimp_Token_Mapper(),
			new Provider_Discovery_Service( new Mailchimp_Discovery(), new Provider_Discovery_Repository(), new System_Clock() ),
			new Fixed_Delivery_Policy()
		);
		$this->reconciler = new Campaign_Reconciler(
			$this->racing,
			$this->references,
			$this->attempts,
			$this->audits,
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock,
			$this->drafts,
			( new Mailchimp_Provider() )->capabilities()
		);
		kses_remove_filters();
		$this->cache_merge_fields();
		$this->drafts->remote_audience = self::AUDIENCE;
	}

	/** The workflows commit real transactions, so shared fixtures are removed explicitly. */
	public function tearDown(): void {
		global $wpdb;
		kses_init_filters();
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\\_%'" );
		foreach ( $this->template_ids as $template_id ) {
			wp_delete_post( $template_id, true );
		}
		$this->truncate();
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	public function test_a_timed_out_schedule_the_provider_accepted_becomes_scheduled_without_another_call(): void {
		$campaign = $this->unknown_after( 'schedule' );
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );
		$mutations = $this->provider_mutations();

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertSame( array( Campaign_State::SCHEDULED, self::SEND_AT ), array( $result->campaign()?->state(), $result->campaign()?->scheduled_for() ) );
		self::assertSame( array( 'succeeded', 'not_retryable' ), array( $result->resolved_attempts()[0]->status(), $result->resolved_attempts()[0]->retryability() ) );
		self::assertSame( array( 'scheduled', $this->clock->now() ), array( $result->reference()?->observed_state(), $result->reference()?->reconciled_at() ) );
		self::assertSame( $mutations, $this->provider_mutations(), 'Reconciliation never schedules, sends, or edits the provider campaign.' );

		$event   = $this->events( $campaign->id() )[0];
		$context = $event->context()->to_array();
		self::assertSame( 'success', $event->result() );
		self::assertSame( array( 'unknown', 'scheduled', 'scheduled', false ), array( $context['from_state'], $context['to_state'], $context['remote_status'], $context['unexplained'] ) );

		// Delivery is no longer blocked: the campaign can be unscheduled.
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );
		self::assertTrue( $this->scheduler->unschedule( $this->sender, $campaign->id(), (int) $result->campaign()?->version(), 'unschedule-1', self::settings() )->is_success() );
	}

	public function test_a_timed_out_schedule_the_provider_never_applied_returns_to_draft_and_may_be_retried(): void {
		$campaign = $this->unknown_after( 'schedule' );
		$this->provider_reports( Remote_Draft_State::DRAFT );

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_State::PROVIDER_DRAFT, $result->campaign()?->state() );
		self::assertNull( $result->campaign()?->scheduled_for() );
		self::assertSame( array( 'failed', 'retryable' ), array( $result->resolved_attempts()[0]->status(), $result->resolved_attempts()[0]->retryability() ) );
		self::assertTrue( $this->scheduler->schedule( $this->sender, $campaign->id(), (int) $result->campaign()?->version(), self::SEND_AT, self::AUDIENCE, 'schedule-2', self::settings() )->is_success() );
	}

	public function test_a_timed_out_unschedule_follows_whether_the_provider_still_has_it_scheduled(): void {
		$still = $this->unknown_after( 'unschedule' );
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );
		$kept = $this->reconciler->reconcile( $this->sender, $still->id(), self::settings() );
		self::assertSame( array( Campaign_State::SCHEDULED, 'failed' ), array( $kept->campaign()?->state(), $kept->resolved_attempts()[0]->status() ) );

		$done = $this->unknown_after( 'unschedule' );
		$this->provider_reports( Remote_Draft_State::DRAFT );
		$unscheduled = $this->reconciler->reconcile( $this->sender, $done->id(), self::settings() );
		self::assertSame( array( Campaign_State::PROVIDER_DRAFT, 'succeeded' ), array( $unscheduled->campaign()?->state(), $unscheduled->resolved_attempts()[0]->status() ) );
	}

	public function test_repeated_reconciliation_settles_nothing_twice_and_never_reaches_the_provider_audience(): void {
		$campaign = $this->unknown_after( 'schedule' );
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );
		$mutations = $this->provider_mutations();

		$first  = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );
		$second = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertCount( 1, $first->resolved_attempts() );
		self::assertSame( array(), $second->resolved_attempts() );
		self::assertSame( $first->campaign()?->state(), $second->campaign()?->state() );
		self::assertSame( $first->campaign()?->scheduled_for(), $second->campaign()?->scheduled_for() );
		self::assertSame( $mutations, $this->provider_mutations() );
	}

	public function test_a_pending_request_is_left_alone_until_it_cannot_still_be_in_flight(): void {
		$campaign = $this->provider_draft_campaign();
		$this->add_attempt( $campaign, Delivery_Operation::SCHEDULE, 'pending', 'mc0001' );
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );

		$early = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $early->error()?->code() );
		self::assertStringContainsString( 'still in progress', (string) $early->error()?->message() );
		self::assertSame( 0, $this->drafts->inspections, 'An in-flight request is not judged by an early observation.' );

		$this->clock->now += Campaign_Reconciler::SETTLE_SECONDS;
		$settled = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );
		self::assertSame( array( Campaign_State::SCHEDULED, 'succeeded' ), array( $settled->campaign()?->state(), $settled->resolved_attempts()[0]->status() ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function delivered(): array {
		return array(
			'sending' => array( Remote_Draft_State::SENDING, Campaign_State::SENDING ),
			'sent'    => array( Remote_Draft_State::SENT, Campaign_State::SENT ),
		);
	}

	/** @dataProvider delivered */
	public function test_a_scheduled_campaign_follows_the_provider_through_delivery( string $remote, string $local ): void {
		$campaign = $this->scheduled_campaign();
		$this->provider_reports( $remote, self::SEND_AT );

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( $local, $result->campaign()?->state() );
		self::assertSame( $remote, $result->reference()?->observed_state() );
	}

	public function test_a_campaign_scheduled_outside_campaignbridge_is_recorded_as_unexplained(): void {
		$campaign = $this->provider_draft_campaign();
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_State::SCHEDULED, $result->campaign()?->state() );
		self::assertTrue( $this->events( $campaign->id() )[0]->context()->to_array()['unexplained'] );
	}

	/** @return array<string, array{0: \Closure(Scripted_Draft_Gateway): void, 1: string}> */
	public static function unresolvable(): array {
		return array(
			'deleted in the provider'  => array(
				static function ( Scripted_Draft_Gateway $drafts ): void {
					$drafts->inspect_error = Provider_Error::from_category( Provider_Error_Category::NOT_FOUND, 'mailchimp_not_found', 'Not found.', 'mailchimp' );
				},
				Campaign_Reconciler::OBSERVED_MISSING,
			),
			'paused in the provider'   => array(
				static function ( Scripted_Draft_Gateway $drafts ): void {
					$drafts->remote_status = Remote_Draft_State::OTHER;
				},
				Campaign_Reconciler::OBSERVED_OTHER,
			),
			'scheduled with no time'   => array(
				static function ( Scripted_Draft_Gateway $drafts ): void {
					$drafts->remote_status    = Remote_Draft_State::SCHEDULED;
					$drafts->remote_send_time = null;
				},
				'scheduled',
			),
		);
	}

	/**
	 * @dataProvider unresolvable
	 * @param \Closure(Scripted_Draft_Gateway): void $provider Provider state to report.
	 */
	public function test_evidence_that_cannot_be_followed_stays_visibly_unresolved( \Closure $provider, string $observed ): void {
		$campaign = $this->unknown_after( 'schedule' );
		$provider( $this->drafts );

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $result->error()?->code() );
		self::assertSame( Campaign_State::UNKNOWN, $this->campaigns->get( $campaign->id() )?->state() );
		self::assertSame( 'unknown', $this->attempts->for_campaign( $campaign->id() )[0]->status(), 'Nothing is settled without evidence.' );
		$reference = $this->references->get( $campaign->id(), 'mailchimp' );
		self::assertSame( array( $observed, null ), array( $reference?->observed_state(), $reference?->reconciled_at() ) );
		self::assertSame( 'unknown', $this->events( $campaign->id() )[0]->result() );
	}

	public function test_a_provider_that_regresses_a_sent_campaign_is_never_followed(): void {
		$campaign = $this->scheduled_campaign();
		$this->provider_reports( Remote_Draft_State::SENT, self::SEND_AT );
		self::assertSame( Campaign_State::SENT, $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() )->campaign()?->state() );

		$this->provider_reports( Remote_Draft_State::DRAFT );
		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $result->error()?->code() );
		self::assertSame( Campaign_State::SENT, $this->campaigns->get( $campaign->id() )?->state() );
	}

	public function test_a_provider_read_failure_changes_nothing(): void {
		$campaign                    = $this->unknown_after( 'schedule' );
		$before                      = $this->campaigns->get( $campaign->id() );
		$this->drafts->inspect_error = Provider_Error::timeout( 'mailchimp_connection_timeout', 'Timed out.', 'mailchimp' );

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $result->error()?->code() );
		self::assertSame( $before?->to_array(), $this->campaigns->get( $campaign->id() )?->to_array() );
		self::assertSame( 'unknown', $this->attempts->for_campaign( $campaign->id() )[0]->status() );
	}

	public function test_a_concurrent_change_wins_and_reconciliation_writes_nothing(): void {
		$campaign = $this->unknown_after( 'schedule' );
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );
		$this->racing->before_write = function () use ( $campaign ): void {
			$current = $this->campaigns->get( $campaign->id() );
			self::assertTrue( $this->campaigns->compare_and_swap( $current->claim( $this->clock->now() ), $current->version() ) );
		};

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_Workflow_Error::CONFLICT, $result->error()?->code() );
		self::assertSame( 'unknown', $this->attempts->for_campaign( $campaign->id() )[0]->status(), 'The attempt update rolled back with the campaign write.' );
		self::assertSame( Campaign_State::UNKNOWN, $this->campaigns->get( $campaign->id() )?->state() );
	}

	public function test_an_unconfirmed_draft_create_is_recovered_by_its_correlation_title(): void {
		$campaign             = $this->approved_campaign();
		$attempt              = $this->add_attempt( $campaign, Delivery_Operation::CREATE_DRAFT, 'unknown', null );
		$this->drafts->found  = Remote_Draft_Matches::create( array( 'mc7777' ), true );
		$this->clock->now    += 60;

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertSame( 'CampaignBridge ' . $attempt->id(), $this->drafts->searches[0]['title'] );
		self::assertLessThan( $attempt->created_at(), $this->drafts->searches[0]['since'] );
		self::assertSame( array( 'mc7777', Campaign_Draft_Handoff::OBSERVED_CONTENT_PENDING ), array( $result->reference()?->remote_id(), $result->reference()?->observed_state() ) );
		self::assertSame( array( 'succeeded', 'mc7777' ), array( $result->resolved_attempts()[0]->status(), $result->resolved_attempts()[0]->remote_correlation() ) );
		self::assertSame( Campaign_State::APPROVED, $this->campaigns->get( $campaign->id() )?->state(), 'The handoff, not reconciliation, finishes the draft.' );
		self::assertSame( 0, $this->drafts->creates );
	}

	public function test_an_unconfirmed_draft_create_is_settled_as_failed_only_after_a_complete_search_and_the_settle_time(): void {
		$campaign = $this->approved_campaign();
		$this->add_attempt( $campaign, Delivery_Operation::CREATE_DRAFT, 'unknown', null );

		$early = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $early->error()?->code() );

		$this->clock->now   += Campaign_Reconciler::SETTLE_SECONDS;
		$this->drafts->found = Remote_Draft_Matches::create( array(), false );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() )->error()?->code(), 'An incomplete search proves nothing.' );

		$this->drafts->found = Remote_Draft_Matches::create( array(), true );
		$settled             = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );
		self::assertSame( array( 'failed', 'retryable' ), array( $settled->resolved_attempts()[0]->status(), $settled->resolved_attempts()[0]->retryability() ) );
		self::assertNull( $this->references->get( $campaign->id(), 'mailchimp' ) );
	}

	public function test_duplicate_drafts_for_one_request_stay_unresolved(): void {
		$campaign            = $this->approved_campaign();
		$this->add_attempt( $campaign, Delivery_Operation::CREATE_DRAFT, 'unknown', null );
		$this->drafts->found = Remote_Draft_Matches::create( array( 'mc1', 'mc2' ), true );

		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $result->error()?->code() );
		self::assertNull( $this->references->get( $campaign->id(), 'mailchimp' ) );
	}

	public function test_only_a_person_with_delivery_authority_may_reconcile_and_tests_are_not_touched(): void {
		$campaign = $this->unknown_after( 'schedule' );
		$tester   = new Campaign_Actor( 7, true, false, false, true );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $this->reconciler->reconcile( $tester, $campaign->id(), self::settings() )->error()?->code() );

		$test = $this->add_attempt( $campaign, Delivery_Operation::TEST_SEND, 'unknown', 'mc0001' );
		$this->provider_reports( Remote_Draft_State::SCHEDULED, self::SEND_AT );
		$result = $this->reconciler->reconcile( $this->sender, $campaign->id(), self::settings() );

		self::assertCount( 1, $result->resolved_attempts() );
		self::assertSame( 'unknown', $this->attempts->get( $test->id() )?->status(), 'A test send cannot be observed in campaign state.' );
	}

	private function provider_reports( string $status, ?string $send_time = null ): void {
		$this->drafts->inspect_error    = null;
		$this->drafts->remote_status    = $status;
		$this->drafts->remote_send_time = $send_time;
	}

	/** @return array<int, int> Provider mutations so far: creates, syncs, delivery calls. */
	private function provider_mutations(): array {
		return array( $this->drafts->creates, $this->drafts->syncs, count( $this->gateway->calls ) );
	}

	/** A campaign left `unknown` by a timed-out schedule or unschedule. */
	private function unknown_after( string $operation ): Campaign {
		$this->provider_reports( Remote_Draft_State::DRAFT );
		$campaign = 'unschedule' === $operation ? $this->scheduled_campaign() : $this->provider_draft_campaign();
		$this->gateway->outcomes = array( Action_Outcome::from_error( Provider_Error::timeout( 'mailchimp_connection_timeout', 'Timed out.', 'mailchimp' ) ) );
		$result                  = 'unschedule' === $operation
			? $this->scheduler->unschedule( $this->sender, $campaign->id(), $campaign->version(), 'unschedule-1', self::settings() )
			: $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $result->error()?->code() );
		self::assertSame( Campaign_State::UNKNOWN, $this->campaigns->get( $campaign->id() )?->state() );
		$this->drafts->remote_status = Remote_Draft_State::DRAFT;

		return $this->campaigns->get( $campaign->id() ) ?? $campaign;
	}

	private function scheduled_campaign(): Campaign {
		$this->provider_reports( Remote_Draft_State::DRAFT );
		$campaign  = $this->provider_draft_campaign();
		$scheduled = $this->scheduler->schedule( $this->sender, $campaign->id(), $campaign->version(), self::SEND_AT, self::AUDIENCE, 'schedule-0', self::settings() );
		self::assertTrue( $scheduled->is_success() );
		$this->drafts->remote_status    = Remote_Draft_State::SCHEDULED;
		$this->drafts->remote_send_time = self::SEND_AT;

		return $scheduled->campaign();
	}

	private function add_attempt( Campaign $campaign, string $operation, string $status, ?string $correlation ): Delivery_Attempt {
		$attempt = Delivery_Attempt::from_array(
			array(
				'schema_version'     => Delivery_Attempt::SCHEMA_VERSION,
				'id'                 => ( new Random_Id_Generator() )->generate( 'attempt' ),
				'campaign_id'        => $campaign->id(),
				'operation'          => $operation,
				'idempotency_key'    => $operation . '-' . $status,
				'status'             => $status,
				'retryability'       => 'unknown',
				'remote_correlation' => $correlation,
				'created_at'         => $this->clock->now(),
				'updated_at'         => $this->clock->now(),
			)
		);
		self::assertTrue( $this->attempts->add( $attempt ) );

		return $attempt;
	}

	private function approved_campaign(): Campaign {
		$template             = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Reconciler template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello {{cb:subscriber.first_name}}, <a href="{{cb:campaign.unsubscribe_url}}">unsubscribe</a></p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			)
		);
		$this->template_ids[] = $template;
		update_post_meta( $template, 'campaignbridge_subject', 'Spring sale' );
		update_post_meta( $template, 'campaignbridge_sender_name', 'Example Shop' );
		update_post_meta( $template, 'campaignbridge_sender_email', 'news@example.com' );

		$created = $this->workflow->create( $this->sender, 7, $template, 'mailchimp', self::AUDIENCE )->campaign();
		self::assertNotNull( $created );
		self::assertTrue( $this->workflow->snapshot( $this->sender, $created->id(), 1 )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->sender, $created->id(), 2 )->is_success() );

		return $this->workflow->approve( $this->sender, $created->id(), 3 )->campaign();
	}

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
						'remote_id'      => 'mc' . substr( md5( $approved->id() ), 0, 8 ),
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

	/** @return array<int, Audit_Event> Reconciliation events, newest first. */
	private function events( string $campaign_id ): array {
		return array_values( array_filter( $this->audits->for_target( 'campaign', $campaign_id ), static fn ( Audit_Event $event ): bool => 'campaign_reconcile' === $event->action() ) );
	}

	private function cache_merge_fields(): void {
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

	private function truncate(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
	}
}
