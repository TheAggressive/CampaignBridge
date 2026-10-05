<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Campaign REST lifecycle integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Campaign_Authorizer;
use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Rest_Resource;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Services\Campaign\Campaign_Workflow_Factory;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the public adapter delegates a secure, provider-neutral local lifecycle. */
final class Campaign_Routes_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	/** @var int Administrator actor ID. */
	private int $admin_id;
	/** @var int Accessible email template ID. */
	private int $template_id;

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );

		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}

		do_action( 'rest_api_init' );
		Routes::register();

		$this->admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		$this->template_id = $this->template( '<!-- wp:campaignbridge/container /-->' );
	}

	public function test_routes_create_validate_input_and_enforce_cross_owner_authority(): void {
		self::assertArrayHasKey( self::COLLECTION, rest_get_server()->get_routes() );

		wp_set_current_user( 0 );
		self::assertSame( 401, $this->request( 'POST', self::COLLECTION, array( 'template_id' => $this->template_id ) )->get_status() );

		wp_set_current_user( $this->admin_id );
		$created = $this->create_campaign();
		self::assertSame( 201, $created->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::campaign_result(), $created );
		$data = $created->get_data();
		self::assertSame( 'draft', $data['campaign']['state'] );
		self::assertSame( 1, $data['campaign']['version'] );
		self::assertSame( $this->admin_id, $data['campaign']['owner_user_id'] );
		self::assertNull( $data['campaign']['provider'] );
		self::assertNull( $data['campaign']['audience_reference'] );
		self::assertSame( 'no-store', $created->get_headers()['Cache-Control'] );

		foreach ( array( 0, -1, 'abc' ) as $malformed ) {
			$refused = $this->request( 'POST', self::COLLECTION, array( 'template_id' => $malformed ) );
			self::assertSame( 400, $refused->get_status() );
			self::assertSame( 'rest_invalid_param', $refused->get_data()['code'] );
		}
		self::assertSame( 'rest_missing_callback_param', $this->request( 'POST', self::COLLECTION )->get_data()['code'] );
		self::assertSame( 400, $this->create_campaign( null, null, array( 'provider' => 'Not An Identifier' ) )->get_status() );
		self::assertSame( 400, $this->create_campaign( null, null, array( 'audience_reference' => str_repeat( 'a', 192 ) ) )->get_status() );

		$missing = $this->request( 'POST', self::COLLECTION, array( 'template_id' => 999999 ) );
		self::assertSame( 403, $missing->get_status() );
		self::assertSame( 'campaignbridge_campaign_forbidden', $missing->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $missing );

		$author = $this->campaign_author();
		wp_set_current_user( $author );
		$forged = $this->create_campaign( $this->admin_id );
		self::assertSame( 403, $forged->get_status() );
		self::assertSame( 'campaignbridge_campaign_forbidden', $forged->get_data()['code'] );
		self::assertSame( array(), ( new Campaign_Repository() )->for_owner( $author ) );

		wp_get_current_user()->remove_cap( Capabilities::EDIT_TEMPLATES );
		$this->assert_error( 403, 'forbidden', $this->create_campaign() );

		wp_set_current_user( $this->admin_id );
		$delegated = $this->create_campaign( $author );
		self::assertSame( 201, $delegated->get_status() );
		self::assertSame( $author, $delegated->get_data()['campaign']['owner_user_id'] );
	}

	public function test_detail_and_bounded_list_do_not_leak_other_owners(): void {
		$owner_one = $this->campaign_author();
		$owner_two = $this->campaign_author();

		wp_set_current_user( $this->admin_id );
		$one = $this->create_campaign( $owner_one )->get_data()['campaign'];
		$two = $this->create_campaign( $owner_two )->get_data()['campaign'];

		$managed_detail = $this->request( 'GET', self::COLLECTION . '/' . $two['id'] );
		self::assertSame( 200, $managed_detail->get_status() );
		self::assertSame( $two, $managed_detail->get_data()['campaign'] );

		wp_set_current_user( $owner_one );
		$owned = $this->request( 'GET', self::COLLECTION . '/' . $one['id'] );
		self::assertSame( 200, $owned->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::campaign_result(), $owned );
		$denied = $this->request( 'GET', self::COLLECTION . '/' . $two['id'] );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( array( 'status' => 403 ), $denied->get_data()['data'] );
		self::assertSame( 404, $this->request( 'GET', self::COLLECTION . '/campaign-missing' )->get_status() );
		self::assertSame( 'rest_invalid_param', $this->request( 'GET', self::COLLECTION . '/Not_Valid' )->get_data()['code'] );

		$list = $this->request( 'GET', self::COLLECTION, array( 'per_page' => 1 ) );
		self::assertSame( 200, $list->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::collection(), $list );
		self::assertSame( 1, $list->get_data()['pagination']['total'] );
		self::assertSame( array( $one['id'] ), array_column( $list->get_data()['items'], 'id' ) );
		self::assertSame( '1', $list->get_headers()['X-WP-Total'] );
		self::assertSame( '1', $list->get_headers()['X-WP-TotalPages'] );

		$other_owner = $this->request( 'GET', self::COLLECTION, array( 'owner_user_id' => $owner_two ) );
		self::assertSame( 403, $other_owner->get_status() );
		self::assertArrayNotHasKey( 'items', $other_owner->get_data() );

		wp_set_current_user( $this->admin_id );
		$managed = $this->request( 'GET', self::COLLECTION, array( 'owner_user_id' => $owner_two ) );
		self::assertSame( 200, $managed->get_status() );
		self::assertSame( array( $two['id'] ), array_column( $managed->get_data()['items'], 'id' ) );
	}

	public function test_pagination_is_bounded_and_deterministic(): void {
		$empty = $this->request( 'GET', self::COLLECTION );
		self::assertSame( 200, $empty->get_status() );
		self::assertSame( array(), $empty->get_data()['items'] );
		self::assertSame( 0, $empty->get_data()['pagination']['total_pages'] );

		for ( $i = 0; $i < 3; ++$i ) {
			$this->create_campaign();
		}

		$first  = $this->page( 1, 2 );
		$second = $this->page( 2, 2 );
		self::assertSame( 3, $first->get_data()['pagination']['total'] );
		self::assertSame( 2, $first->get_data()['pagination']['total_pages'] );
		self::assertCount( 2, $first->get_data()['items'] );
		self::assertCount( 1, $second->get_data()['items'] );
		$ids = array_merge( array_column( $first->get_data()['items'], 'id' ), array_column( $second->get_data()['items'], 'id' ) );
		self::assertCount( 3, array_unique( $ids ) );
		self::assertSame( array_column( $first->get_data()['items'], 'id' ), array_column( $this->page( 1, 2 )->get_data()['items'], 'id' ) );

		self::assertSame( 'campaignbridge_campaign_invalid_page', $this->page( 3, 2 )->get_data()['code'] );
		self::assertSame( 400, $this->page( 1, 101 )->get_status() );
		self::assertSame( 400, $this->page( 0, 10 )->get_status() );
	}

	public function test_html_export_only_lifecycle_and_stale_version_contract(): void {
		$campaign = $this->create_campaign()->get_data()['campaign'];
		$id       = $campaign['id'];

		$snapshot = $this->action( $id, 'snapshot', array( 'expected_version' => 1 ) );
		self::assertSame( 200, $snapshot->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::snapshot_result(), $snapshot );
		self::assertSame( 2, $snapshot->get_data()['campaign']['version'] );
		self::assertSame( 1, $snapshot->get_data()['snapshot']['revision'] );
		self::assertSame( $snapshot->get_data()['snapshot']['id'], $snapshot->get_data()['campaign']['active_snapshot_id'] );
		self::assertTrue( $snapshot->get_data()['validation']['valid'] );
		self::assertSame( $snapshot->get_data()['snapshot']['fingerprint'], $snapshot->get_data()['validation']['fingerprint'] );
		self::assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', $snapshot->get_data()['snapshot']['fingerprint'] );
		// The envelope is frozen even when incomplete; only provider handoff requires it.
		self::assertFalse( $snapshot->get_data()['snapshot']['envelope']['complete'] );
		self::assertContains( 'subject_missing', $snapshot->get_data()['snapshot']['envelope']['problems'] );

		$validation = $this->action( $id, 'validation' );
		self::assertSame( 200, $validation->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::validation_result(), $validation );
		self::assertTrue( $validation->get_data()['validation']['valid'] );

		$preview = $this->action( $id, 'preview' );
		self::assertSame( 200, $preview->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::preview_result(), $preview );
		self::assertSame( $snapshot->get_data()['snapshot']['fingerprint'], $preview->get_data()['preview']['fingerprint'] );
		self::assertSame( 2, ( new Campaign_Repository() )->get( $id )?->version() );

		$submitted = $this->action( $id, 'submit', array( 'expected_version' => 2 ) );
		self::assertSame( 'ready_for_review', $submitted->get_data()['campaign']['state'] );
		$approved = $this->action( $id, 'approve', array( 'expected_version' => 3 ) );
		self::assertSame( 200, $approved->get_status() );
		$approved_campaign = $approved->get_data()['campaign'];
		self::assertSame( 'approved', $approved_campaign['state'] );
		self::assertSame( 4, $approved_campaign['version'] );
		self::assertNull( $approved_campaign['provider'] );
		self::assertNull( $approved_campaign['audience_reference'] );
		self::assertSame( $snapshot->get_data()['snapshot']['id'], $approved_campaign['active_snapshot_id'] );
		self::assertSame(
			$snapshot->get_data()['snapshot']['fingerprint'],
			( new Campaign_Snapshot_Repository() )->get( $approved_campaign['active_snapshot_id'] )?->artifact()->fingerprint()
		);
		self::assertNull( ( new Remote_Campaign_Reference_Repository() )->get( $id, 'mailchimp' ) );
		self::assertSame( array(), ( new Delivery_Attempt_Repository() )->for_campaign( $id ) );

		$history = array_map(
			static fn ( Audit_Event $event ): string => $event->action() . ':' . $event->result(),
			( new Audit_Event_Repository() )->for_target( 'campaign', $id )
		);
		foreach ( array( 'campaign_create', 'campaign_snapshot', 'campaign_validate', 'campaign_submit_review', 'campaign_approve' ) as $action ) {
			self::assertContains( $action . ':success', $history );
		}

		$stale = $this->action( $id, 'archive', array( 'expected_version' => 3 ) );
		self::assertSame( 409, $stale->get_status() );
		self::assertSame( 'campaignbridge_campaign_conflict', $stale->get_data()['code'] );
		self::assertSame( 4, $stale->get_data()['data']['current_version'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $stale );
		self::assertSame( 'approved', ( new Campaign_Repository() )->get( $id )?->state() );

		self::assertSame( 'rest_missing_callback_param', $this->action( $id, 'archive' )->get_data()['code'] );
		foreach ( array( 0, -4, 'four' ) as $malformed ) {
			self::assertSame( 'rest_invalid_param', $this->action( $id, 'archive', array( 'expected_version' => $malformed ) )->get_data()['code'] );
		}
		self::assertSame( 4, ( new Campaign_Repository() )->get( $id )?->version() );

		$revoked = $this->action( $id, 'revoke-approval', array( 'expected_version' => 4 ) );
		self::assertSame( 'ready_for_review', $revoked->get_data()['campaign']['state'] );
		$archived = $this->action( $id, 'archive', array( 'expected_version' => 5 ) );
		self::assertSame( 'archived', $archived->get_data()['campaign']['state'] );
		self::assertSame( 'campaignbridge_campaign_invalid_state', $this->action( $id, 'archive', array( 'expected_version' => 6 ) )->get_data()['code'] );
	}

	public function test_template_and_targeting_edits_are_versioned_workflow_calls(): void {
		$id          = $this->create_campaign()->get_data()['campaign']['id'];
		$replacement = $this->template( '<!-- wp:campaignbridge/container /-->' );

		self::assertSame( 'rest_missing_callback_param', $this->action( $id, 'template', array( 'template_id' => $replacement ) )->get_data()['code'] );
		$edited = $this->action(
			$id,
			'template',
			array(
				'expected_version' => 1,
				'template_id'      => $replacement,
			)
		);
		self::assertSame( 200, $edited->get_status() );
		self::assertSame( $replacement, $edited->get_data()['campaign']['template_id'] );
		self::assertSame( 2, $edited->get_data()['campaign']['version'] );

		self::assertSame( 'rest_missing_callback_param', $this->action( $id, 'targeting', array( 'expected_version' => 2 ) )->get_data()['code'] );
		$targeted = $this->action(
			$id,
			'targeting',
			array(
				'expected_version'   => 2,
				'provider'           => 'provider-one',
				'audience_reference' => 'audience-one',
			)
		);
		self::assertSame( 'provider-one', $targeted->get_data()['campaign']['provider'] );
		$cleared = $this->action(
			$id,
			'targeting',
			array(
				'expected_version'   => 3,
				'provider'           => null,
				'audience_reference' => null,
			)
		);
		self::assertSame( 200, $cleared->get_status() );
		self::assertNull( $cleared->get_data()['campaign']['provider'] );
		self::assertNull( $cleared->get_data()['campaign']['audience_reference'] );
	}

	public function test_approval_refusals_map_to_stable_errors_without_mutation(): void {
		$id = $this->create_campaign()->get_data()['campaign']['id'];
		$this->assert_error( 409, 'approval_not_allowed', $this->action( $id, 'approve', array( 'expected_version' => 1 ) ) );

		$this->action( $id, 'snapshot', array( 'expected_version' => 1 ) );
		$this->assert_error( 409, 'approval_not_allowed', $this->action( $id, 'approve', array( 'expected_version' => 2 ) ) );
		self::assertSame( 'draft', ( new Campaign_Repository() )->get( $id )?->state() );

		$this->action( $id, 'submit', array( 'expected_version' => 2 ) );
		$this->assert_error( 409, 'conflict', $this->action( $id, 'approve', array( 'expected_version' => 2 ) ) );

		$author = $this->campaign_author();
		wp_set_current_user( $author );
		$owned = $this->create_campaign()->get_data()['campaign']['id'];
		$this->action( $owned, 'snapshot', array( 'expected_version' => 1 ) );
		$this->action( $owned, 'submit', array( 'expected_version' => 2 ) );
		$no_authority = $this->action( $owned, 'approve', array( 'expected_version' => 3 ) );
		self::assertSame( 403, $no_authority->get_status() );
		self::assertSame( 'rest_forbidden', $no_authority->get_data()['code'] );

		$user = wp_get_current_user();
		$user->add_cap( Capabilities::SEND_CAMPAIGNS );
		$user->remove_cap( Capabilities::EDIT_TEMPLATES );
		$this->assert_error( 403, 'forbidden', $this->action( $owned, 'approve', array( 'expected_version' => 3 ) ) );
		self::assertSame( 'ready_for_review', ( new Campaign_Repository() )->get( $owned )?->state() );

		$user->add_cap( Capabilities::EDIT_TEMPLATES );
		self::assertSame( 'approved', $this->action( $owned, 'approve', array( 'expected_version' => 3 ) )->get_data()['campaign']['state'] );
	}

	public function test_invalid_content_returns_structured_diagnostics_without_mutation(): void {
		$invalid_template = $this->template( '<!-- wp:core:html --><script>alert(1)</script><!-- /wp:core:html -->' );
		$invalid          = $this->create_campaign( null, $invalid_template )->get_data()['campaign'];

		$snapshot = $this->action( $invalid['id'], 'snapshot', array( 'expected_version' => 1 ) );
		$this->assert_error( 400, 'validation_failed', $snapshot );
		self::assertNotEmpty( $snapshot->get_data()['data']['diagnostics'] );
		self::assertSame( 1, ( new Campaign_Repository() )->get( $invalid['id'] )?->version() );
		self::assertSame( array(), ( new Campaign_Snapshot_Repository() )->for_campaign( $invalid['id'] ) );

		$validation = $this->action( $invalid['id'], 'validation' );
		self::assertSame( 200, $validation->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::validation_result(), $validation );
		self::assertFalse( $validation->get_data()['validation']['valid'] );
		self::assertNull( $validation->get_data()['validation']['fingerprint'] );
		self::assertSame( 'error', $validation->get_data()['validation']['diagnostics'][0]['severity'] );

		$preview = $this->action( $invalid['id'], 'preview' );
		self::assertSame( 200, $preview->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::preview_result(), $preview );
		self::assertSame( '', $preview->get_data()['preview']['html'] );
		self::assertNull( $preview->get_data()['preview']['sample'] );

		$this->assert_error( 409, 'missing_snapshot', $this->action( $invalid['id'], 'submit', array( 'expected_version' => 1 ) ) );
	}

	public function test_an_image_without_an_authored_height_snapshots_and_reloads(): void {
		// The editor's default: a width and "height: auto", so the compiled image has no height.
		$template = $this->template( '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:image {"width":"268px","sizeSlug":"large"} --><figure class="wp-block-image size-large is-resized"><img src="https://example.com/hoodie.jpg" alt="Hoodie" style="width:268px"/></figure><!-- /wp:image --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' );
		$campaign = $this->create_campaign( null, $template )->get_data()['campaign'];

		$snapshot = $this->action( $campaign['id'], 'snapshot', array( 'expected_version' => 1 ) );

		self::assertSame( 200, $snapshot->get_status() );
		$stored = ( new Campaign_Snapshot_Repository() )->for_campaign( $campaign['id'] );
		self::assertCount( 1, $stored );
		self::assertSame( $snapshot->get_data()['snapshot']['fingerprint'], $stored[0]->artifact()->fingerprint() );
		self::assertNull( $stored[0]->artifact()->assets()[0]['height'] );
	}

	public function test_duplicate_is_idempotent_and_matches_direct_workflow_invocation(): void {
		$source = $this->create_campaign(
			null,
			null,
			array(
				'provider'           => 'provider-one',
				'audience_reference' => 'audience-one',
			)
		)->get_data()['campaign'];
		$this->action( $source['id'], 'snapshot', array( 'expected_version' => 1 ) );

		self::assertSame( 'rest_missing_callback_param', $this->action( $source['id'], 'duplicate' )->get_data()['code'] );
		self::assertSame( 'rest_invalid_param', $this->action( $source['id'], 'duplicate', array( 'idempotency_key' => 'has spaces' ) )->get_data()['code'] );

		$first = $this->action( $source['id'], 'duplicate', array( 'idempotency_key' => 'rest-duplicate-1' ) );
		$retry = $this->action( $source['id'], 'duplicate', array( 'idempotency_key' => 'rest-duplicate-1' ) );
		self::assertSame( 201, $first->get_status() );
		self::assertSame( 200, $retry->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::duplicate_result(), $first );
		self::assertFalse( $first->get_data()['idempotent_replay'] );
		self::assertTrue( $retry->get_data()['idempotent_replay'] );
		self::assertSame( $first->get_data()['campaign'], $retry->get_data()['campaign'] );

		$duplicate = $first->get_data()['campaign'];
		self::assertNotSame( $source['id'], $duplicate['id'] );
		self::assertSame( 'draft', $duplicate['state'] );
		self::assertSame( 1, $duplicate['version'] );
		self::assertNull( $duplicate['active_snapshot_id'] );
		self::assertSame( array(), ( new Campaign_Snapshot_Repository() )->for_campaign( $duplicate['id'] ) );
		self::assertSame( array(), ( new Delivery_Attempt_Repository() )->for_campaign( $duplicate['id'] ) );
		self::assertNull( ( new Remote_Campaign_Reference_Repository() )->get( $duplicate['id'], 'provider-one' ) );

		$workflow = Campaign_Workflow_Factory::create();
		$actor    = ( new Campaign_Authorizer() )->actor( $this->admin_id );
		$direct   = $workflow->duplicate( $actor, $source['id'], 'rest-duplicate-1' );
		self::assertTrue( $direct->is_idempotent_replay() );
		self::assertSame( $duplicate, Campaign_Rest_Resource::campaign( $direct->campaign(), $actor ) );
		self::assertSame(
			$this->request( 'GET', self::COLLECTION . '/' . $source['id'] )->get_data()['campaign'],
			Campaign_Rest_Resource::campaign( $workflow->get( $actor, $source['id'] )->campaign(), $actor )
		);

		$version = ( new Campaign_Repository() )->get( $source['id'] )?->version();
		$changed = $this->action(
			$source['id'],
			'targeting',
			array(
				'expected_version'   => $version,
				'provider'           => 'provider-one',
				'audience_reference' => 'audience-two',
			)
		);
		self::assertSame( 200, $changed->get_status() );
		$this->assert_error( 409, 'idempotency_conflict', $this->action( $source['id'], 'duplicate', array( 'idempotency_key' => 'rest-duplicate-1' ) ) );
		self::assertCount( 2, ( new Campaign_Repository() )->for_owner( $this->admin_id ) );
	}

	public function test_every_route_publishes_its_response_schema(): void {
		$routes = array(
			''                    => 'campaignbridge-campaign-collection',
			'/c1'                 => 'campaignbridge-campaign-result',
			'/c1/template'        => 'campaignbridge-campaign-result',
			'/c1/targeting'       => 'campaignbridge-campaign-result',
			'/c1/snapshot'        => 'campaignbridge-campaign-snapshot-result',
			'/c1/validation'      => 'campaignbridge-campaign-validation-result',
			'/c1/preview'         => 'campaignbridge-campaign-preview-result',
			'/c1/submit'          => 'campaignbridge-campaign-result',
			'/c1/approve'         => 'campaignbridge-campaign-result',
			'/c1/revoke-approval' => 'campaignbridge-campaign-result',
			'/c1/archive'         => 'campaignbridge-campaign-result',
			'/c1/duplicate'       => 'campaignbridge-campaign-duplicate-result',
			'/c1/provider-draft'  => 'campaignbridge-campaign-provider-draft-result',
			'/c1/test-send'       => 'campaignbridge-campaign-test-send-result',
			'/c1/schedule'        => 'campaignbridge-campaign-delivery-result',
			'/c1/unschedule'      => 'campaignbridge-campaign-delivery-result',
		);
		foreach ( $routes as $suffix => $title ) {
			$options = $this->request( 'OPTIONS', self::COLLECTION . $suffix );
			self::assertSame( $title, $options->get_data()['schema']['title'] ?? null, $suffix );
		}
	}

	/** @return iterable<string, array{string, array<string, mixed>}> */
	public static function expensive_routes(): iterable {
		yield 'create' => array( '', array( 'template_id' => 999999 ) );
		yield 'snapshot' => array( '/campaign-missing/snapshot', array( 'expected_version' => 1 ) );
		yield 'validation' => array( '/campaign-missing/validation', array() );
		yield 'preview' => array( '/campaign-missing/preview', array() );
		yield 'duplicate' => array( '/campaign-missing/duplicate', array( 'idempotency_key' => 'rate-limit' ) );
		yield 'test send' => array(
			'/campaign-missing/test-send',
			array(
				'recipients'      => array( 'qa@example.com' ),
				'idempotency_key' => 'rate-limit',
			),
		);
	}

	/**
	 * @dataProvider expensive_routes
	 * @param array<string, mixed> $params Valid request fields.
	 */
	public function test_expensive_routes_are_rate_limited_per_authenticated_user( string $suffix, array $params ): void {
		for ( $i = 0; $i < 10; ++$i ) {
			self::assertNotSame( 429, $this->request( 'POST', self::COLLECTION . $suffix, $params )->get_status() );
		}

		$limited = $this->request( 'POST', self::COLLECTION . $suffix, $params );
		self::assertSame( 429, $limited->get_status() );
		self::assertSame( 'rate_limit_exceeded', $limited->get_data()['code'] );

		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		self::assertNotSame( 429, $this->request( 'POST', self::COLLECTION . $suffix, $params )->get_status() );
	}

	private function assert_error( int $status, string $code, WP_REST_Response $response ): void {
		self::assertSame( $status, $response->get_status() );
		self::assertSame( 'campaignbridge_campaign_' . $code, $response->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $response );
	}

	/** @param array<string, mixed> $schema Published response schema. */
	private function assert_schema( array $schema, WP_REST_Response $response ): void {
		$valid = rest_validate_value_from_schema( $response->get_data(), $schema, 'response' );
		self::assertTrue( true === $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
	}

	private function campaign_author(): int {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( Capabilities::CREATE_CAMPAIGNS );
		$user->add_cap( Capabilities::EDIT_TEMPLATES );
		return $user_id;
	}

	private function template( string $content ): int {
		return $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Test campaign template',
				'post_content' => $content,
				'post_author'  => $this->admin_id,
			)
		);
	}

	/** @param array<string, mixed> $extra Additional create fields. */
	private function create_campaign( ?int $owner_id = null, ?int $template_id = null, array $extra = array() ): WP_REST_Response {
		$body = array_merge(
			array( 'template_id' => $template_id ?? $this->template_id ),
			$extra
		);
		if ( null !== $owner_id ) {
			$body['owner_user_id'] = $owner_id;
		}
		return $this->request( 'POST', self::COLLECTION, $body );
	}

	/** @param array<string, mixed> $params Request parameters. */
	private function action( string $id, string $action, array $params = array() ): WP_REST_Response {
		return $this->request( 'POST', self::COLLECTION . "/{$id}/{$action}", $params );
	}

	private function page( int $page, int $per_page ): WP_REST_Response {
		return $this->request(
			'GET',
			self::COLLECTION,
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/** @param array<string, mixed> $params Request parameters. */
	private function request( string $method, string $route, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}
}
