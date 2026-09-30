<?php
/**
 * Canonical campaign workflow integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
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
use CampaignBridge\Workflow\Campaign\Campaign_Id_Generator;
use CampaignBridge\Workflow\Campaign\Campaign_Review_Input_Capture;
use CampaignBridge\Workflow\Campaign\Campaign_Template_Authority;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;

/** Deterministic IDs for assertions without changing production generation. */
final class Workflow_Test_Ids implements Campaign_Id_Generator {
	private static int $next = 1;

	public function generate( string $prefix ): string {
		return $prefix . '-' . self::$next++;
	}
}

/** Deterministic timestamp for repository round trips. */
final class Workflow_Test_Clock implements Campaign_Clock {
	public function now(): string {
		return '2026-09-28T12:00:00Z';
	}
}

/** Injected companion-write failure for transaction rollback coverage. */
final class Failing_Workflow_Audits implements Audit_Event_Source {
	public function get( string $id ): ?Audit_Event {
		return null;
	}

	public function add( Audit_Event $event ): bool {
		return false;
	}

	public function for_target( string $target_type, string $target_id, int $limit = 100 ): array {
		return array();
	}
}

/** Explicit template allowlist for workflow integration tests. */
final class Workflow_Test_Template_Authority implements Campaign_Template_Authority {
	/** @param array<int, int> $allowed_template_ids Allowed object IDs. */
	public function __construct( private readonly array $allowed_template_ids ) {}

	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool {
		unset( $actor );

		return in_array( $template_id, $this->allowed_template_ids, true );
	}
}

/** Proves canonical lifecycle, concurrency, snapshots, audit, and atomicity. */
final class Campaign_Workflow_Test extends Test_Case {
	private Campaign_Repository $campaigns;
	private Campaign_Snapshot_Repository $snapshots;
	private Delivery_Attempt_Repository $attempts;
	private Audit_Event_Repository $audits;
	private Campaign_Workflow $workflow;
	private Campaign_Actor $actor;
	private int $template_id;

	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}

		$this->campaigns   = new Campaign_Repository();
		$this->snapshots   = new Campaign_Snapshot_Repository();
		$this->attempts    = new Delivery_Attempt_Repository();
		$this->audits      = new Audit_Event_Repository();
		$this->template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Initial campaign',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
			)
		);
		$this->actor       = new Campaign_Actor( 7, true, false, true );
		$this->workflow    = $this->workflow( $this->audits );
	}

	public function test_complete_local_lifecycle_preserves_immutable_artifacts_and_history(): void {
		$created = $this->workflow->create( $this->actor, 7, $this->template_id, 'provider-one', 'audience-one' );
		self::assertTrue( $created->is_success() );
		$campaign_id = (string) $created->campaign()?->id();

		$frozen = $this->workflow->snapshot( $this->actor, $campaign_id, 1 );
		self::assertTrue( $frozen->is_success() );
		self::assertSame( 1, $frozen->snapshot()?->revision() );
		$first_snapshot = $frozen->snapshot();
		self::assertNotNull( $first_snapshot );

		$validated = $this->workflow->validate( $this->actor, $campaign_id );
		$previewed = $this->workflow->preview( $this->actor, $campaign_id );
		self::assertTrue( $validated->is_success() );
		self::assertTrue( $previewed->is_success() );
		self::assertSame( $validated->compile_result()?->fingerprint(), $previewed->compile_result()?->fingerprint() );

		$submitted = $this->workflow->submit_for_review( $this->actor, $campaign_id, 2 );
		self::assertSame( 'ready_for_review', $submitted->campaign()?->state() );
		$approved = $this->workflow->approve( $this->actor, $campaign_id, 3 );
		self::assertSame( 'approved', $approved->campaign()?->state() );
		self::assertSame( $first_snapshot->id(), $approved->campaign()?->active_snapshot_id() );

		wp_update_post(
			array(
				'ID'         => $this->template_id,
				'post_title' => 'Changed after approval',
			)
		);
		$stored_before_refresh = $this->snapshots->get( $first_snapshot->id() );
		self::assertSame( $first_snapshot->to_array(), $stored_before_refresh?->to_array() );
		self::assertNotSame( $first_snapshot->artifact()->fingerprint(), $this->workflow->preview( $this->actor, $campaign_id )->compile_result()?->fingerprint() );

		$refreshed = $this->workflow->snapshot( $this->actor, $campaign_id, 4 );
		self::assertTrue( $refreshed->is_success() );
		self::assertSame( 2, $refreshed->snapshot()?->revision() );
		self::assertSame( 'ready_for_review', $refreshed->campaign()?->state() );
		self::assertSame( $first_snapshot->to_array(), $this->snapshots->get( $first_snapshot->id() )?->to_array() );

		$reapproved = $this->workflow->approve( $this->actor, $campaign_id, 5 );
		self::assertSame( 'approved', $reapproved->campaign()?->state() );
		self::assertSame( $refreshed->snapshot()?->id(), $reapproved->campaign()?->active_snapshot_id() );
		self::assertSame( $refreshed->snapshot()?->artifact()->fingerprint(), $this->snapshots->get( (string) $reapproved->campaign()?->active_snapshot_id() )?->artifact()->fingerprint() );

		$audience = $this->workflow->select_audience( $this->actor, $campaign_id, 6, 'provider-one', 'audience-two' );
		self::assertSame( 'ready_for_review', $audience->campaign()?->state() );
		self::assertSame( $refreshed->snapshot()?->id(), $audience->campaign()?->active_snapshot_id() );

		self::assertSame( 'approved', $this->workflow->approve( $this->actor, $campaign_id, 7 )->campaign()?->state() );
		self::assertSame( 'ready_for_review', $this->workflow->revoke_approval( $this->actor, $campaign_id, 8 )->campaign()?->state() );
		$archived = $this->workflow->archive( $this->actor, $campaign_id, 9 );
		self::assertSame( 'archived', $archived->campaign()?->state() );
		self::assertCount( 2, $this->snapshots->for_campaign( $campaign_id ) );
		self::assertGreaterThanOrEqual( 10, count( $this->audits->for_target( 'campaign', $campaign_id ) ) );
	}

	public function test_reads_use_object_authorization_and_bounded_owner_queries(): void {
		$campaign = $this->create_campaign();
		$stranger = new Campaign_Actor( 8, true, false, false );
		$manager  = new Campaign_Actor( 9, false, true, false );

		self::assertSame( $campaign->to_array(), $this->workflow->get( $this->actor, $campaign->id() )->campaign()?->to_array() );
		self::assertSame( $campaign->id(), $this->workflow->get( $manager, $campaign->id() )->campaign()?->id() );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $this->workflow->get( $stranger, $campaign->id() )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::NOT_FOUND, $this->workflow->get( $this->actor, 'campaign-missing' )->error()?->code() );

		$this->create_campaign();
		$page = $this->workflow->list( $this->actor, 7, 1, 1 );
		self::assertTrue( $page->is_success() );
		self::assertCount( 1, $page->campaigns() );
		self::assertSame( 2, $page->total() );
		self::assertTrue( $this->workflow->list( $manager, 7, 10, 0 )->is_success() );

		$denied = $this->workflow->list( $stranger, 7, 10, 0 );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $denied->error()?->code() );
		self::assertSame( array(), $denied->campaigns() );
		self::assertSame( 0, $denied->total() );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->workflow->list( $this->actor, 7, 101, 0 )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->workflow->list( $this->actor, 7, 10, -1 )->error()?->code() );
	}

	public function test_stale_mutation_cannot_overwrite_newer_state(): void {
		$campaign = $this->create_campaign();
		$first    = $this->workflow->edit_template( $this->actor, $campaign->id(), 1, $this->template_id );
		self::assertTrue( $first->is_success() );
		self::assertSame( 2, $first->campaign()?->version() );

		$stale = $this->workflow->select_audience( $this->actor, $campaign->id(), 1, 'provider-one', 'stale-audience' );
		self::assertFalse( $stale->is_success() );
		self::assertSame( Campaign_Workflow_Error::CONFLICT, $stale->error()?->code() );
		self::assertSame( 'audience-one', $this->campaigns->get( $campaign->id() )?->audience_reference() );
		self::assertSame( 2, $this->campaigns->get( $campaign->id() )?->version() );
	}

	public function test_approval_fails_for_missing_snapshot_invalid_state_and_stale_version(): void {
		$campaign = $this->create_campaign();
		$missing  = $this->workflow->approve( $this->actor, $campaign->id(), 1 );
		self::assertSame( Campaign_Workflow_Error::APPROVAL_NOT_ALLOWED, $missing->error()?->code() );

		$snapshot = $this->workflow->snapshot( $this->actor, $campaign->id(), 1 );
		self::assertTrue( $snapshot->is_success() );
		$illegal = $this->workflow->approve( $this->actor, $campaign->id(), 2 );
		self::assertSame( Campaign_Workflow_Error::APPROVAL_NOT_ALLOWED, $illegal->error()?->code() );

		self::assertTrue( $this->workflow->submit_for_review( $this->actor, $campaign->id(), 2 )->is_success() );
		$stale = $this->workflow->approve( $this->actor, $campaign->id(), 2 );
		self::assertSame( Campaign_Workflow_Error::CONFLICT, $stale->error()?->code() );
	}

	public function test_template_edit_returns_reviewed_and_approved_campaigns_to_draft(): void {
		$replacement = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Replacement campaign',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
			)
		);
		$workflow    = $this->workflow( $this->audits, new Workflow_Test_Template_Authority( array( $this->template_id, $replacement ) ) );

		foreach ( array( 'ready_for_review' => 3, 'approved' => 4 ) as $state => $version ) {
			$campaign = $this->create_campaign();
			$snapshot = $workflow->snapshot( $this->actor, $campaign->id(), 1 )->snapshot();
			self::assertNotNull( $snapshot );
			self::assertTrue( $workflow->submit_for_review( $this->actor, $campaign->id(), 2 )->is_success() );
			if ( 'approved' === $state ) {
				self::assertTrue( $workflow->approve( $this->actor, $campaign->id(), 3 )->is_success() );
			}
			self::assertSame( $state, $this->campaigns->get( $campaign->id() )?->state() );

			$edited = $workflow->edit_template( $this->actor, $campaign->id(), $version, $replacement );
			self::assertTrue( $edited->is_success(), $state );
			self::assertSame( 'draft', $edited->campaign()?->state() );
			self::assertSame( $replacement, $edited->campaign()?->template_id() );
			self::assertNull( $edited->campaign()?->active_snapshot_id() );
			self::assertSame( $edited->campaign()?->to_array(), $this->campaigns->get( $campaign->id() )?->to_array() );
			self::assertSame( $snapshot->to_array(), $this->snapshots->get( $snapshot->id() )?->to_array(), 'Snapshot history stays immutable.' );

			$events = array_values(
				array_filter(
					$this->audits->for_target( 'campaign', $campaign->id() ),
					static fn ( Audit_Event $event ): bool => 'campaign_edit' === $event->action() && 'success' === $event->result()
				)
			);
			self::assertCount( 1, $events );
			self::assertSame( $state, $events[0]->context()->to_array()['from_state'] );
			self::assertSame( 'draft', $events[0]->context()->to_array()['to_state'] );
			self::assertTrue( $events[0]->context()->to_array()['snapshot_invalidated'] );

			self::assertSame(
				Campaign_Workflow_Error::MISSING_SNAPSHOT,
				$workflow->submit_for_review( $this->actor, $campaign->id(), $version + 1 )->error()?->code(),
				'Review requires a fresh snapshot of the new template.'
			);
		}
	}

	public function test_blocking_compiler_diagnostics_prevent_snapshot_and_submission(): void {
		wp_update_post(
			array(
				'ID'           => $this->template_id,
				'post_content' => '<!-- wp:core:html --><script>alert(1)</script><!-- /wp:core:html -->',
			)
		);
		$campaign = $this->create_campaign();
		$result   = $this->workflow->snapshot( $this->actor, $campaign->id(), 1 );

		self::assertFalse( $result->is_success() );
		self::assertSame( Campaign_Workflow_Error::VALIDATION_FAILED, $result->error()?->code() );
		self::assertNotEmpty( $result->compile_result()?->diagnostics() );
		self::assertSame( 1, $this->campaigns->get( $campaign->id() )?->version() );
		self::assertSame( array(), $this->snapshots->for_campaign( $campaign->id() ) );
		self::assertSame( Campaign_Workflow_Error::MISSING_SNAPSHOT, $this->workflow->submit_for_review( $this->actor, $campaign->id(), 1 )->error()?->code() );
	}

	public function test_html_export_only_campaign_reaches_approval_without_provider_or_audience(): void {
		$created = $this->workflow->create( $this->actor, 7, $this->template_id );
		self::assertTrue( $created->is_success() );
		$campaign = $created->campaign();
		self::assertNotNull( $campaign );
		self::assertNull( $campaign->provider() );
		self::assertNull( $campaign->audience_reference() );

		$snapshot = $this->workflow->snapshot( $this->actor, $campaign->id(), 1 );
		self::assertTrue( $snapshot->is_success() );
		self::assertTrue( $this->workflow->validate( $this->actor, $campaign->id() )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->actor, $campaign->id(), 2 )->is_success() );

		$approved = $this->workflow->approve( $this->actor, $campaign->id(), 3 );
		self::assertTrue( $approved->is_success() );
		self::assertSame( 'approved', $approved->campaign()?->state() );
		self::assertSame( $snapshot->snapshot()?->id(), $approved->campaign()?->active_snapshot_id() );
		$approved_snapshot = $this->snapshots->get( (string) $approved->campaign()?->active_snapshot_id() );
		self::assertNotNull( $approved_snapshot );
		self::assertSame( $snapshot->snapshot()?->artifact()->fingerprint(), $approved_snapshot->artifact()->fingerprint() );
	}

	public function test_inaccessible_templates_are_rejected_before_capture_or_compile(): void {
		$secret_template = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Restricted template title',
				'post_content' => '<!-- wp:core/paragraph --><p>Restricted template content</p><!-- /wp:core/paragraph -->',
			)
		);
		$restricted      = $this->workflow( $this->audits, new Workflow_Test_Template_Authority( array() ) );
		$create          = $restricted->create( $this->actor, 7, $secret_template );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $create->error()?->code() );
		self::assertNull( $create->campaign() );
		self::assertNull( $create->compile_result() );

		$campaign = $this->create_campaign();
		$edit     = $this->workflow->edit_template( $this->actor, $campaign->id(), 1, $secret_template );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $edit->error()?->code() );
		self::assertSame( $this->template_id, $this->campaigns->get( $campaign->id() )?->template_id() );

		foreach ( array( 'snapshot', 'preview', 'validate' ) as $operation ) {
			$result = 'snapshot' === $operation
				? $restricted->snapshot( $this->actor, $campaign->id(), 1 )
				: $restricted->{$operation}( $this->actor, $campaign->id() );
			self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $result->error()?->code() );
			self::assertNull( $result->compile_result() );
		}

		self::assertSame( array(), $this->snapshots->for_campaign( $campaign->id() ) );

		self::assertTrue( $this->workflow->snapshot( $this->actor, $campaign->id(), 1 )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->actor, $campaign->id(), 2 )->is_success() );
		$approval = $restricted->approve( $this->actor, $campaign->id(), 3 );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $approval->error()?->code() );
		self::assertSame( 'ready_for_review', $this->campaigns->get( $campaign->id() )?->state() );

		$duplicate = $restricted->duplicate( $this->actor, $campaign->id(), 'restricted-duplicate' );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $duplicate->error()?->code() );
		self::assertSame( $campaign->id(), $duplicate->campaign()?->id() );
		self::assertCount( 1, $this->campaigns->for_owner( $campaign->owner_user_id() ) );

		$denials = array_values(
			array_filter(
				$this->audits->for_target( 'campaign', $campaign->id() ),
				static fn ( Audit_Event $event ): bool => 'denied' === $event->result()
			)
		);
		self::assertCount( 6, $denials );
		foreach ( $denials as $denial ) {
			self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $denial->context()->to_array()['error_code'] ?? null );
			self::assertStringNotContainsString( 'Restricted template', (string) wp_json_encode( $denial->to_array() ) );
		}
	}


	public function test_duplicate_is_idempotent_and_does_not_copy_operational_history(): void {
		$campaign = $this->create_campaign();
		self::assertTrue( $this->workflow->snapshot( $this->actor, $campaign->id(), 1 )->is_success() );
		self::assertTrue( ( new Remote_Campaign_Reference_Repository() )->add( $this->remote_reference( $campaign->id() ) ) );
		self::assertTrue( $this->attempts->add( $this->send_attempt( $campaign->id() ) ) );
		$source_before = $this->campaigns->get( $campaign->id() )?->to_array();
		$audits_before = count( $this->audits->for_target( 'campaign', $campaign->id() ) );

		$first  = $this->workflow->duplicate( $this->actor, $campaign->id(), 'duplicate-request' );
		$second = $this->workflow->duplicate( $this->actor, $campaign->id(), 'duplicate-request' );
		self::assertTrue( $first->is_success() );
		self::assertTrue( $second->is_success() );
		self::assertTrue( $second->is_idempotent_replay() );
		self::assertSame( $first->campaign()?->id(), $second->campaign()?->id() );
		self::assertCount( 2, $this->campaigns->for_owner( 7 ) );

		$duplicate = $first->campaign();
		self::assertNotNull( $duplicate );
		self::assertSame( 'draft', $duplicate->state() );
		self::assertSame( 1, $duplicate->version() );
		self::assertSame( $source_before, $this->campaigns->get( $campaign->id() )?->to_array() );
		self::assertNull( $duplicate->active_snapshot_id() );
		self::assertSame( array(), $this->snapshots->for_campaign( $duplicate->id() ) );
		self::assertNull( ( new Remote_Campaign_Reference_Repository() )->get( $duplicate->id(), 'provider-one' ) );
		self::assertSame( array(), $this->attempts->for_campaign( $duplicate->id() ) );
		self::assertCount( 1, $this->attempts->for_campaign( $campaign->id() ) );
		self::assertCount( 1, $this->audits->for_target( 'campaign', $duplicate->id() ) );
		self::assertSame( $audits_before, count( $this->audits->for_target( 'campaign', $campaign->id() ) ) );

		$changed = $this->workflow->select_audience( $this->actor, $campaign->id(), 2, 'provider-one', 'audience-two' );
		self::assertTrue( $changed->is_success() );
		$mismatch = $this->workflow->duplicate( $this->actor, $campaign->id(), 'duplicate-request' );
		self::assertSame( Campaign_Workflow_Error::IDEMPOTENCY_CONFLICT, $mismatch->error()?->code() );
		self::assertSame( $duplicate->id(), $first->campaign()?->id() );
	}

	public function test_snapshot_and_approval_companion_failures_roll_back_required_state(): void {
		$campaign = $this->create_campaign();
		$failing  = $this->workflow( new Failing_Workflow_Audits() );
		$snapshot = $failing->snapshot( $this->actor, $campaign->id(), 1 );

		self::assertSame( Campaign_Workflow_Error::PERSISTENCE_FAILED, $snapshot->error()?->code() );
		self::assertSame( array(), $this->snapshots->for_campaign( $campaign->id() ) );
		self::assertSame( 1, $this->campaigns->get( $campaign->id() )?->version() );
		self::assertNull( $this->campaigns->get( $campaign->id() )?->active_snapshot_id() );

		self::assertTrue( $this->workflow->snapshot( $this->actor, $campaign->id(), 1 )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->actor, $campaign->id(), 2 )->is_success() );
		$approval = $failing->approve( $this->actor, $campaign->id(), 3 );
		self::assertSame( Campaign_Workflow_Error::PERSISTENCE_FAILED, $approval->error()?->code() );
		self::assertSame( 'ready_for_review', $this->campaigns->get( $campaign->id() )?->state() );
		self::assertSame( 3, $this->campaigns->get( $campaign->id() )?->version() );
	}


	public function test_failed_companion_audit_rolls_back_campaign_mutation(): void {
		$campaign = $this->create_campaign();
		$failing  = $this->workflow( new Failing_Workflow_Audits() );
		$result   = $failing->select_audience( $this->actor, $campaign->id(), 1, 'provider-two', 'audience-two' );

		self::assertFalse( $result->is_success() );
		self::assertSame( Campaign_Workflow_Error::PERSISTENCE_FAILED, $result->error()?->code() );
		self::assertSame( 1, $this->campaigns->get( $campaign->id() )?->version() );
		self::assertSame( 'provider-one', $this->campaigns->get( $campaign->id() )?->provider() );
	}

	public function test_authorization_is_explicit_and_denials_are_audited(): void {
		$campaign = $this->create_campaign();
		$denied   = new Campaign_Actor( 7, false, false, false );
		$result   = $this->workflow->archive( $denied, $campaign->id(), 1 );

		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $result->error()?->code() );
		$events = array_values(
			array_filter(
				$this->audits->for_target( 'campaign', $campaign->id() ),
				static fn ( Audit_Event $event ): bool => 'campaign_archive' === $event->action()
			)
		);
		self::assertCount( 1, $events );
		self::assertSame( 'denied', $events[0]->result() );
		self::assertSame( 7, $events[0]->actor_user_id() );
	}

	private function workflow( Audit_Event_Source $audits, ?Campaign_Template_Authority $template_authority = null ): Campaign_Workflow {
		return new Campaign_Workflow(
			$this->campaigns,
			$this->snapshots,
			$audits,
			new Campaign_Review_Input_Capture(
				new Campaign_Template_Input_Repository(),
				new Post_Snapshot_Repository(),
				new Brand_Kit_Repository()
			),
			$template_authority ?? new Workflow_Test_Template_Authority( array( $this->template_id ) ),
			new Database_Transaction(),
			new Workflow_Test_Ids(),
			new Workflow_Test_Clock()
		);
	}

	private function create_campaign(): Campaign {
		$result = $this->workflow->create( $this->actor, 7, $this->template_id, 'provider-one', 'audience-one' );
		self::assertTrue( $result->is_success() );
		self::assertNotNull( $result->campaign() );
		return $result->campaign();
	}

	private function remote_reference( string $campaign_id ): Remote_Campaign_Reference {
		return Remote_Campaign_Reference::from_array(
			array(
				'schema_version' => 1,
				'campaign_id'    => $campaign_id,
				'provider'       => 'provider-one',
				'remote_id'      => 'remote-one',
				'observed_state' => 'draft',
				'cursor'         => null,
				'observed_at'    => '2026-09-28T12:00:00Z',
				'reconciled_at'  => null,
			)
		);
	}

	private function send_attempt( string $campaign_id ): Delivery_Attempt {
		return Delivery_Attempt::from_array(
			array(
				'schema_version'     => 1,
				'id'                 => 'send-attempt',
				'campaign_id'        => $campaign_id,
				'operation'          => 'send',
				'idempotency_key'    => 'send-key',
				'status'             => 'succeeded',
				'retryability'       => 'not_retryable',
				'remote_correlation' => 'remote-one',
				'created_at'         => '2026-09-28T12:00:00Z',
				'updated_at'         => '2026-09-28T12:00:00Z',
			)
		);
	}
}
