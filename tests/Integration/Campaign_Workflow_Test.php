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
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Review_Input_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Id_Generator;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;

/** Deterministic IDs for assertions without changing production generation. */
final class Workflow_Test_Ids implements Campaign_Id_Generator {
	private int $next = 1;

	public function generate( string $prefix ): string {
		return $prefix . '-' . $this->next++;
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

		$this->campaigns = new Campaign_Repository();
		$this->snapshots = new Campaign_Snapshot_Repository();
		$this->attempts  = new Delivery_Attempt_Repository();
		$this->audits    = new Audit_Event_Repository();
		$this->actor     = new Campaign_Actor( 7, true, false, true );
		$this->workflow  = $this->workflow( $this->audits );
		$this->template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Initial campaign',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
			)
		);
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

	public function test_review_requires_normalized_provider_and_audience_references(): void {
		$created = $this->workflow->create( $this->actor, 7, $this->template_id );
		self::assertTrue( $created->is_success() );
		$campaign = $created->campaign();
		self::assertNotNull( $campaign );
		self::assertTrue( $this->workflow->snapshot( $this->actor, $campaign->id(), 1 )->is_success() );

		$result = $this->workflow->submit_for_review( $this->actor, $campaign->id(), 2 );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $result->error()?->code() );
		self::assertSame( 'draft', $this->campaigns->get( $campaign->id() )?->state() );
	}


	public function test_duplicate_is_idempotent_and_does_not_copy_operational_history(): void {
		$campaign = $this->create_campaign();
		self::assertTrue( $this->workflow->snapshot( $this->actor, $campaign->id(), 1 )->is_success() );
		self::assertTrue( ( new Remote_Campaign_Reference_Repository() )->add( $this->remote_reference( $campaign->id() ) ) );
		self::assertTrue( $this->attempts->add( $this->send_attempt( $campaign->id() ) ) );

		$first  = $this->workflow->duplicate( $this->actor, $campaign->id(), 'duplicate-request' );
		$second = $this->workflow->duplicate( $this->actor, $campaign->id(), 'duplicate-request' );
		self::assertTrue( $first->is_success() );
		self::assertTrue( $second->is_success() );
		self::assertTrue( $second->is_idempotent_replay() );
		self::assertSame( $first->campaign()?->id(), $second->campaign()?->id() );

		$duplicate = $first->campaign();
		self::assertNotNull( $duplicate );
		self::assertSame( 'draft', $duplicate->state() );
		self::assertNull( $duplicate->active_snapshot_id() );
		self::assertSame( array(), $this->snapshots->for_campaign( $duplicate->id() ) );
		self::assertNull( ( new Remote_Campaign_Reference_Repository() )->get( $duplicate->id(), 'provider-one' ) );
		self::assertSame( array(), $this->attempts->for_campaign( $duplicate->id() ) );
		self::assertCount( 1, $this->audits->for_target( 'campaign', $duplicate->id() ) );
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

	private function workflow( Audit_Event_Source $audits ): Campaign_Workflow {
		return new Campaign_Workflow(
			$this->campaigns,
			$this->snapshots,
			$this->attempts,
			$audits,
			new Campaign_Review_Input_Repository(),
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
