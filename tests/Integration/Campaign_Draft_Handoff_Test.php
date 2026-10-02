<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile,CampaignBridge.Standard.Sniffs.Database
/**
 * Remote draft handoff integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Draft_Outcome;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
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
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Draft_Handoff;
use CampaignBridge\Workflow\Campaign\Campaign_Review_Input_Capture;
use CampaignBridge\Workflow\Campaign\Campaign_Template_Authority;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

/** Stands in for the provider: counts mutations and replays scripted outcomes. */
final class Scripted_Draft_Gateway implements Provider_Draft_Gateway {
	public int $creates = 0;

	public int $uploads = 0;

	/** @var array<int, Draft_Content> */
	public array $contents = array();

	/** @var array<int, Draft_Outcome> Outcomes for successive creates. */
	public array $create_outcomes = array();

	/** @var array<int, Draft_Outcome> Outcomes for successive content uploads. */
	public array $upload_outcomes = array();

	/** @var (\Closure(): void)|null Runs while the remote create is in flight. */
	public ?\Closure $during_create = null;

	public string $next_remote_id = 'mc0001';

	public function slug(): string {
		return 'mailchimp';
	}

	public function create_draft( array $settings, Draft_Content $content ): Draft_Outcome {
		++$this->creates;
		$this->contents[] = $content;
		if ( null !== $this->during_create ) {
			( $this->during_create )();
		}

		return array_shift( $this->create_outcomes ) ?? Draft_Outcome::created( $this->next_remote_id );
	}

	public function upload_content( array $settings, string $remote_id, Draft_Content $content ): Draft_Outcome {
		++$this->uploads;
		$this->contents[] = $content;

		return array_shift( $this->upload_outcomes ) ?? Draft_Outcome::created( $remote_id );
	}
}

/** Grants every template so these tests exercise handoff rules only. */
final class Handoff_Template_Authority implements Campaign_Template_Authority {
	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool {
		return true;
	}
}

/** Proves the draft handoff creates exactly one remote draft and never retries blindly. */
final class Campaign_Draft_Handoff_Test extends Test_Case {
	private const AUDIENCE = 'abc123';

	private Campaign_Repository $campaigns;

	private Campaign_Snapshot_Repository $snapshots;

	private Remote_Campaign_Reference_Repository $references;

	private Delivery_Attempt_Repository $attempts;

	private Audit_Event_Repository $audits;

	private Campaign_Workflow $workflow;

	private Scripted_Draft_Gateway $gateway;

	private Campaign_Draft_Handoff $handoff;

	private Campaign_Actor $approver;

	private int $template_id;

	/** @var array<int, int> Templates committed by workflow transactions. */
	private array $template_ids = array();

	/** @return array<string, string> */
	private static function settings(): array {
		return array( 'api_key' => str_repeat( 'c0ffee', 5 ) . 'c0' . '-us20' );
	}

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}

		$this->campaigns  = new Campaign_Repository();
		$this->snapshots  = new Campaign_Snapshot_Repository();
		$this->references = new Remote_Campaign_Reference_Repository();
		$this->attempts   = new Delivery_Attempt_Repository();
		$this->audits     = new Audit_Event_Repository();
		$this->gateway    = new Scripted_Draft_Gateway();
		$this->approver   = new Campaign_Actor( 7, true, false, true );
		$this->workflow   = new Campaign_Workflow(
			$this->campaigns,
			$this->snapshots,
			$this->audits,
			new Campaign_Review_Input_Capture( new Campaign_Template_Input_Repository(), new Post_Snapshot_Repository(), new Brand_Kit_Repository() ),
			new Handoff_Template_Authority(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			new System_Clock()
		);
		$this->handoff    = new Campaign_Draft_Handoff(
			$this->campaigns,
			$this->snapshots,
			$this->references,
			$this->attempts,
			$this->audits,
			new Database_Transaction(),
			new Random_Id_Generator(),
			new System_Clock(),
			$this->gateway,
			( new Mailchimp_Provider() )->capabilities(),
			new Mailchimp_Token_Mapper(),
			new Provider_Discovery_Service( new Mailchimp_Discovery(), new Provider_Discovery_Repository(), new System_Clock() )
		);

		// Token URLs are authored by trusted editors; unfiltered content keeps them intact.
		kses_remove_filters();
		$this->template_id = $this->template( 'Hello {{cb:subscriber.first_name}}, <a href="{{cb:campaign.unsubscribe_url}}">unsubscribe</a>' );
		$this->cache_merge_fields( array( 'FNAME', 'LNAME' ) );
	}

	/** The workflow commits real transactions, so shared fixtures are removed explicitly. */
	public function tearDown(): void {
		global $wpdb;
		kses_init_filters();
		foreach ( $this->template_ids as $template_id ) {
			wp_delete_post( $template_id, true );
		}
		$this->clear_merge_fields();
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
		// The harness runs with autocommit off, so cleanup of committed fixtures must commit too.
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	public function test_approved_artifact_becomes_exactly_one_remote_draft(): void {
		$campaign = $this->approved_campaign();
		$snapshot = $this->snapshots->get( (string) $campaign->active_snapshot_id() );
		self::assertNotNull( $snapshot );

		$result = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );

		self::assertTrue( $result->is_success() );
		self::assertFalse( $result->is_idempotent_replay() );
		self::assertSame( 1, $this->gateway->creates );
		self::assertSame( 'provider_draft', $result->campaign()?->state() );
		self::assertSame( $campaign->version() + 1, $result->campaign()?->version() );
		self::assertSame( $result->campaign()?->to_array(), $this->campaigns->get( $campaign->id() )?->to_array() );

		// The stored reference maps back to exactly this campaign and attempt.
		$reference = $this->references->get( $campaign->id(), 'mailchimp' );
		self::assertSame( 'mc0001', $reference?->remote_id() );
		self::assertSame( Campaign_Draft_Handoff::OBSERVED_DRAFT, $reference?->observed_state() );
		self::assertSame( $campaign->id(), $this->references->find_remote( 'mailchimp', 'mc0001' )?->campaign_id() );
		$attempts = $this->attempts->for_campaign( $campaign->id() );
		self::assertCount( 1, $attempts );
		self::assertSame( array( 'create_draft', 'draft-key-1', 'succeeded', 'mc0001' ), array( $attempts[0]->operation(), $attempts[0]->idempotency_key(), $attempts[0]->status(), $attempts[0]->remote_correlation() ) );

		// The uploaded content is the approved artifact, translated, with its frozen envelope.
		$sent = $this->gateway->contents[0];
		self::assertSame( $snapshot->artifact()->fingerprint(), $sent->fingerprint() );
		self::assertSame( str_replace( array( '{{cb:subscriber.first_name}}', '{{cb:campaign.unsubscribe_url}}' ), array( '*|FNAME|*', '*|UNSUB|*' ), $snapshot->artifact()->html() ), $sent->html() );
		self::assertStringNotContainsString( '{{cb:', $sent->html() . $sent->text() . $sent->subject() );
		self::assertSame( 'Spring sale for *|FNAME|*', $sent->subject() );
		self::assertSame( array( 'Example Shop', 'news@example.com', self::AUDIENCE ), array( $sent->from_name(), $sent->reply_to(), $sent->audience_id() ) );
		self::assertSame( 'CampaignBridge ' . $attempts[0]->id(), $sent->correlation(), 'The remote draft carries the attempt ID for reconciliation.' );
		self::assertStringContainsString( '{{cb:subscriber.first_name}}', (string) $this->snapshots->get( $snapshot->id() )?->artifact()->html(), 'The canonical artifact is unchanged.' );

		self::assertContains( 'campaign_provider_draft:success', $this->audit_trail( $campaign->id() ) );
	}

	public function test_repeated_requests_reuse_the_existing_draft(): void {
		$campaign = $this->approved_campaign();
		$first    = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );

		foreach ( array( array( 'draft-key-1', $campaign->version() ), array( 'draft-key-2', $campaign->version() + 1 ), array( 'draft-key-1', 999 ) ) as $retry ) {
			$again = $this->handoff->create_draft( $this->approver, $campaign->id(), $retry[1], $retry[0], self::settings() );
			self::assertTrue( $again->is_success() );
			self::assertTrue( $again->is_idempotent_replay() );
			self::assertSame( $first->reference()?->to_array(), $again->reference()?->to_array() );
		}
		self::assertSame( 1, $this->gateway->creates, 'No repeat may create a second remote draft.' );
		self::assertSame( 0, $this->gateway->uploads );
		self::assertCount( 1, $this->attempts->for_campaign( $campaign->id() ) );
	}

	public function test_ambiguous_outcome_requires_reconciliation_and_is_never_retried(): void {
		$campaign                       = $this->approved_campaign();
		$this->gateway->create_outcomes = array( Draft_Outcome::from_create_error( Provider_Error::timeout( 'mailchimp_connection_timeout', 'Mailchimp request timed out.', 'mailchimp' ) ) );

		$result = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $result->error()?->code() );
		self::assertSame( 'unknown', $result->attempt()?->status() );
		self::assertSame( 'unknown', $this->attempts->for_campaign( $campaign->id() )[0]->status() );
		self::assertSame( 'approved', $this->campaigns->get( $campaign->id() )?->state() );
		self::assertNull( $this->references->get( $campaign->id(), 'mailchimp' ) );
		self::assertContains( 'campaign_provider_draft:unknown', $this->audit_trail( $campaign->id() ) );

		foreach ( array( 'draft-key-1', 'draft-key-2' ) as $key ) {
			$blocked = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), $key, self::settings() );
			self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $blocked->error()?->code() );
		}
		self::assertSame( 1, $this->gateway->creates, 'An unknown outcome is never retried, even with a new key.' );
		self::assertCount( 1, $this->attempts->for_campaign( $campaign->id() ) );
	}

	public function test_definite_failure_creates_nothing_and_needs_a_new_key_to_retry(): void {
		$campaign                       = $this->approved_campaign();
		$this->gateway->create_outcomes = array(
			Draft_Outcome::from_create_error( Provider_Error::from_category( Provider_Error_Category::NOT_FOUND, 'mailchimp_not_found', 'The Mailchimp resource was not found.', 'mailchimp' ) ),
		);

		$failed = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $failed->error()?->code() );
		self::assertSame( Provider_Error_Category::NOT_FOUND, $failed->provider_error()?->category() );
		self::assertSame( array( 'failed', 'not_retryable' ), array( $failed->attempt()?->status(), $failed->attempt()?->retryability() ) );
		self::assertSame( 'approved', $this->campaigns->get( $campaign->id() )?->state() );
		self::assertNull( $this->references->get( $campaign->id(), 'mailchimp' ) );

		$same_key = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $same_key->error()?->code() );
		self::assertSame( 1, $this->gateway->creates, 'A settled key is not sent to the provider again.' );

		$new_key = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-2', self::settings() );
		self::assertTrue( $new_key->is_success() );
		self::assertSame( 2, $this->gateway->creates );
		self::assertSame( 'mc0001', $this->references->get( $campaign->id(), 'mailchimp' )?->remote_id() );
		$statuses = array_map( static fn ( Delivery_Attempt $attempt ): string => $attempt->status(), $this->attempts->for_campaign( $campaign->id() ) );
		sort( $statuses );
		self::assertSame( array( 'failed', 'succeeded' ), $statuses );
	}

	public function test_failed_content_upload_keeps_the_draft_and_resumes_without_a_second_create(): void {
		$campaign                       = $this->approved_campaign();
		$this->gateway->create_outcomes = array(
			Draft_Outcome::content_pending( 'mc0001', Provider_Error::from_category( Provider_Error_Category::PROVIDER_ERROR, 'mailchimp_provider_error', 'Mailchimp service returned an error.', 'mailchimp' ) ),
		);

		$partial = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );
		self::assertSame( Campaign_Workflow_Error::PROVIDER_FAILED, $partial->error()?->code() );
		self::assertSame( Campaign_Draft_Handoff::OBSERVED_CONTENT_PENDING, $partial->reference()?->observed_state() );
		self::assertSame( 'mc0001', $this->references->get( $campaign->id(), 'mailchimp' )?->remote_id(), 'A created draft ID is never lost.' );
		self::assertSame( array( 'failed', 'retryable', 'mc0001' ), array( $partial->attempt()?->status(), $partial->attempt()?->retryability(), $partial->attempt()?->remote_correlation() ) );
		self::assertSame( 'approved', $this->campaigns->get( $campaign->id() )?->state() );

		$resumed = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-2', self::settings() );
		self::assertTrue( $resumed->is_success() );
		self::assertSame( array( 1, 1 ), array( $this->gateway->creates, $this->gateway->uploads ), 'Resuming uploads content only.' );
		self::assertSame( Campaign_Draft_Handoff::OBSERVED_DRAFT, $this->references->get( $campaign->id(), 'mailchimp' )?->observed_state() );
		self::assertSame( 'provider_draft', $this->campaigns->get( $campaign->id() )?->state() );
	}

	public function test_a_concurrent_campaign_change_never_loses_the_created_draft(): void {
		$campaign                     = $this->approved_campaign();
		$this->gateway->during_create = function () use ( $campaign ): void {
			self::assertTrue( $this->workflow->revoke_approval( $this->approver, $campaign->id(), $campaign->version() )->is_success() );
		};

		$result = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::CONFLICT, $result->error()?->code() );
		self::assertSame( 'mc0001', $this->references->get( $campaign->id(), 'mailchimp' )?->remote_id() );
		self::assertSame( 'succeeded', $this->attempts->for_campaign( $campaign->id() )[0]->status() );
		self::assertSame( 'ready_for_review', $this->campaigns->get( $campaign->id() )?->state(), 'The concurrent change is not overwritten.' );

		$this->gateway->during_create = null;
		$later                        = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version() + 1, 'draft-key-2', self::settings() );
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $later->error()?->code() );
		self::assertSame( 1, $this->gateway->creates );
	}

	public function test_one_remote_draft_cannot_be_claimed_by_two_campaigns(): void {
		$first  = $this->approved_campaign();
		$second = $this->approved_campaign();
		self::assertTrue( $this->handoff->create_draft( $this->approver, $first->id(), $first->version(), 'draft-key-1', self::settings() )->is_success() );

		// The provider answers with an ID that is already mapped locally.
		$clash = $this->handoff->create_draft( $this->approver, $second->id(), $second->version(), 'draft-key-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $clash->error()?->code() );
		self::assertSame( $first->id(), $this->references->find_remote( 'mailchimp', 'mc0001' )?->campaign_id() );
		self::assertNull( $this->references->get( $second->id(), 'mailchimp' ) );
		self::assertSame( 'approved', $this->campaigns->get( $second->id() )?->state() );
	}

	public function test_preconditions_are_checked_before_any_provider_call(): void {
		$approved = $this->approved_campaign();
		$author   = new Campaign_Actor( 7, true, false, false );
		self::assertSame( Campaign_Workflow_Error::FORBIDDEN, $this->handoff->create_draft( $author, $approved->id(), $approved->version(), 'k', self::settings() )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::NOT_FOUND, $this->handoff->create_draft( $this->approver, 'campaign-missing', 1, 'k', self::settings() )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->handoff->create_draft( $this->approver, $approved->id(), $approved->version(), '', self::settings() )->error()?->code() );
		self::assertSame( Campaign_Workflow_Error::CONFLICT, $this->handoff->create_draft( $this->approver, $approved->id(), $approved->version() - 1, 'k', self::settings() )->error()?->code() );

		$draft = $this->workflow->create( $this->approver, 7, $this->template_id, 'mailchimp', self::AUDIENCE )->campaign();
		self::assertSame( Campaign_Workflow_Error::INVALID_STATE, $this->handoff->create_draft( $this->approver, (string) $draft?->id(), 1, 'k', self::settings() )->error()?->code() );

		$untargeted = $this->approved_campaign( null, null );
		self::assertSame( Campaign_Workflow_Error::INVALID_INPUT, $this->handoff->create_draft( $this->approver, $untargeted->id(), $untargeted->version(), 'k', self::settings() )->error()?->code() );

		self::assertSame( 0, $this->gateway->creates );
		self::assertSame( array(), $this->attempts->for_campaign( $approved->id() ) );
		self::assertContains( 'campaign_provider_draft:denied', $this->audit_trail( $approved->id() ) );
	}

	/** @return array<string, array{string, array<string, string>, array<int, string>, string}> */
	public static function unsendable_artifacts(): array {
		$complete = array(
			'campaignbridge_subject'      => 'Spring sale',
			'campaignbridge_sender_name'  => 'Example Shop',
			'campaignbridge_sender_email' => 'news@example.com',
		);

		return array(
			'incomplete envelope'      => array( 'Hello', array( 'campaignbridge_subject' => 'Spring sale' ), array( 'FNAME' ), 'sender_name_missing' ),
			'undiscovered merge field' => array( 'Hello {{cb:subscriber.last_name}}', $complete, array(), 'Refresh merge fields' ),
			'missing merge field'      => array( 'Hello {{cb:subscriber.last_name}}', $complete, array( 'FNAME' ), 'cannot substitute' ),
			'literal provider syntax'  => array( 'Hello *|FNAME|*', $complete, array( 'FNAME' ), 'literal provider merge syntax' ),
			'literal syntax in subject' => array( 'Hello', array_merge( $complete, array( 'campaignbridge_subject' => 'Hi *|EMAIL|*' ) ), array( 'FNAME' ), 'approved subject' ),
		);
	}

	/**
	 * @dataProvider unsendable_artifacts
	 * @param array<string, string> $meta   Template envelope meta.
	 * @param array<int, string>    $fields Cached merge-field tags; empty means none discovered.
	 */
	public function test_unsendable_artifacts_are_refused_before_any_provider_call( string $body, array $meta, array $fields, string $expected ): void {
		$this->template_id = $this->template( $body, $meta );
		if ( array() === $fields ) {
			$this->clear_merge_fields();
		} else {
			$this->cache_merge_fields( $fields );
		}
		$campaign = $this->approved_campaign();

		$result = $this->handoff->create_draft( $this->approver, $campaign->id(), $campaign->version(), 'draft-key-1', self::settings() );

		self::assertSame( Campaign_Workflow_Error::VALIDATION_FAILED, $result->error()?->code() );
		self::assertStringContainsString( $expected, (string) $result->error()?->message() );
		self::assertSame( 0, $this->gateway->creates );
		self::assertSame( array(), $this->attempts->for_campaign( $campaign->id() ) );
		self::assertSame( 'approved', $this->campaigns->get( $campaign->id() )?->state() );
	}

	/** @param array<string, string>|null $meta Envelope meta; null uses a complete envelope. */
	private function template( string $paragraph, ?array $meta = null ): int {
		$id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Handoff template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>' . $paragraph . '</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			)
		);
		$meta = $meta ?? array(
			'campaignbridge_subject'      => 'Spring sale for {{cb:subscriber.first_name}}',
			'campaignbridge_preheader'    => 'Two days only',
			'campaignbridge_sender_name'  => 'Example Shop',
			'campaignbridge_sender_email' => 'news@example.com',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$this->template_ids[] = $id;

		return $id;
	}

	private function approved_campaign( ?string $provider = 'mailchimp', ?string $audience = self::AUDIENCE ): Campaign {
		$created = $this->workflow->create( $this->approver, 7, $this->template_id, $provider, $audience )->campaign();
		self::assertNotNull( $created );
		self::assertTrue( $this->workflow->snapshot( $this->approver, $created->id(), 1 )->is_success() );
		self::assertTrue( $this->workflow->submit_for_review( $this->approver, $created->id(), 2 )->is_success() );
		$approved = $this->workflow->approve( $this->approver, $created->id(), 3 )->campaign();
		self::assertSame( 'approved', $approved?->state() );

		return $approved;
	}

	/** @param array<int, string> $tags Discovered merge-field tags. */
	private function cache_merge_fields( array $tags ): void {
		$this->clear_merge_fields();
		$account    = (string) ( new Mailchimp_Discovery() )->account_key( self::settings() );
		$repository = new Provider_Discovery_Repository();
		$repository->save(
			$account,
			Discovery_Result::create(
				'mailchimp',
				self::AUDIENCE,
				Discovery_Batch::create(
					Discovery_Kind::MERGE_FIELDS,
					array_map( static fn ( string $tag ): Discovered_Merge_Field => Discovered_Merge_Field::create( $tag, $tag, 'text', false ), $tags ),
					true
				),
				gmdate( 'Y-m-d\TH:i:s\Z' )
			)
		);
		self::assertCount( count( $tags ), $repository->get( $account, 'mailchimp', Discovery_Kind::MERGE_FIELDS, self::AUDIENCE )?->items() ?? array() );
	}

	private function clear_merge_fields(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\_%'" );
		wp_cache_flush();
	}

	/** @return array<int, string> */
	private function audit_trail( string $campaign_id ): array {
		return array_map(
			static fn ( Audit_Event $event ): string => $event->action() . ':' . $event->result(),
			$this->audits->for_target( 'campaign', $campaign_id )
		);
	}
}
