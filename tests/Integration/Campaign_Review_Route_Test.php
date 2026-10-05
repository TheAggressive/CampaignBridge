<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Reviewed snapshot route and review-stage actions.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the review screen sees exactly the reviewed artifact and the review actions the workflow allows. */
final class Campaign_Review_Route_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	private const CONTENT = '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>%s, {{cb:subscriber.first_name}}</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

	private int $admin_id;
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
		$this->template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Review template',
				'post_content' => sprintf( self::CONTENT, 'Reviewed words' ),
				'post_author'  => $this->admin_id,
			)
		);
	}

	public function test_the_reviewed_snapshot_is_the_frozen_artifact_not_live_content(): void {
		$campaign = $this->create();
		$missing  = $this->request( 'GET', $this->route( $campaign['id'], '/reviewed-snapshot' ) );
		self::assertSame( 409, $missing->get_status() );
		self::assertSame( 'campaignbridge_campaign_missing_snapshot', $missing->get_data()['code'] );

		$snapshot = $this->request( 'POST', $this->route( $campaign['id'], '/snapshot' ), array( 'expected_version' => 1 ) );
		self::assertSame( 200, $snapshot->get_status() );

		wp_update_post(
			array(
				'ID'           => $this->template_id,
				'post_content' => sprintf( self::CONTENT, 'Later live edit' ),
			)
		);

		$reviewed = $this->request( 'GET', $this->route( $campaign['id'], '/reviewed-snapshot' ) );
		self::assertSame( 200, $reviewed->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::reviewed_snapshot_result(), $reviewed );
		self::assertSame( 'no-store', $reviewed->get_headers()['Cache-Control'] );
		$artifact = $reviewed->get_data()['artifact'];
		self::assertStringContainsString( 'Reviewed words', $artifact['html'] );
		self::assertStringNotContainsString( 'Later live edit', $artifact['html'] );
		self::assertSame( $snapshot->get_data()['snapshot']['fingerprint'], $artifact['fingerprint'] );
		self::assertSame( $snapshot->get_data()['snapshot']['id'], $reviewed->get_data()['snapshot']['id'] );
		self::assertStringContainsString( '{{cb:subscriber.first_name}}', $artifact['html'], 'The artifact keeps canonical tokens.' );
		self::assertNotNull( $artifact['sample'] );
		self::assertStringNotContainsString( '{{cb:', $artifact['sample']['html'], 'The sample shows synthetic values instead.' );

		$live = $this->request( 'POST', $this->route( $campaign['id'], '/preview' ) );
		self::assertStringContainsString( 'Later live edit', $live->get_data()['preview']['html'], 'The live preview is a different, unreviewed view.' );
	}

	public function test_review_actions_follow_the_state_machine_and_approval_authority(): void {
		$campaign = $this->create();
		self::assertSame( array( 'edit', 'snapshot', 'archive', 'duplicate' ), $campaign['actions'] );

		$snapshot = $this->request( 'POST', $this->route( $campaign['id'], '/snapshot' ), array( 'expected_version' => 1 ) )->get_data()['campaign'];
		self::assertSame( array( 'edit', 'snapshot', 'submit', 'archive', 'duplicate' ), $snapshot['actions'] );

		$submitted = $this->request( 'POST', $this->route( $campaign['id'], '/submit' ), array( 'expected_version' => $snapshot['version'] ) )->get_data()['campaign'];
		self::assertSame( 'ready_for_review', $submitted['state'] );
		self::assertSame( array( 'edit', 'snapshot', 'approve', 'archive', 'duplicate' ), $submitted['actions'] );

		$approved = $this->request( 'POST', $this->route( $campaign['id'], '/approve' ), array( 'expected_version' => $submitted['version'] ) )->get_data()['campaign'];
		self::assertSame( 'approved', $approved['state'] );
		self::assertSame( array( 'edit', 'snapshot', 'revoke_approval', 'archive', 'duplicate' ), $approved['actions'] );

		$revoked = $this->request( 'POST', $this->route( $campaign['id'], '/revoke-approval' ), array( 'expected_version' => $approved['version'] ) )->get_data()['campaign'];
		self::assertSame( 'ready_for_review', $revoked['state'] );
	}

	public function test_approve_is_not_offered_without_approval_authority(): void {
		$author = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user   = get_userdata( $author );
		$user->add_cap( Capabilities::CREATE_CAMPAIGNS );
		$user->add_cap( Capabilities::EDIT_TEMPLATES );
		wp_set_current_user( $author );
		$template = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => sprintf( self::CONTENT, 'Author words' ),
				'post_author'  => $author,
			)
		);
		$campaign  = $this->request( 'POST', self::COLLECTION, array( 'template_id' => $template ) )->get_data()['campaign'];
		$snapshot  = $this->request( 'POST', $this->route( $campaign['id'], '/snapshot' ), array( 'expected_version' => 1 ) )->get_data()['campaign'];
		$submitted = $this->request( 'POST', $this->route( $campaign['id'], '/submit' ), array( 'expected_version' => $snapshot['version'] ) )->get_data()['campaign'];

		self::assertSame( 'ready_for_review', $submitted['state'] );
		self::assertNotContains( 'approve', $submitted['actions'] );
		self::assertSame( 403, $this->request( 'POST', $this->route( $campaign['id'], '/approve' ), array( 'expected_version' => $submitted['version'] ) )->get_status() );

		wp_set_current_user( $this->admin_id );
		$other = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $other )->add_cap( Capabilities::CREATE_CAMPAIGNS );
		wp_set_current_user( $other );
		self::assertSame( 403, $this->request( 'GET', $this->route( $campaign['id'], '/reviewed-snapshot' ) )->get_status(), 'Another author cannot read the reviewed artifact.' );
	}

	public function test_the_route_publishes_its_schema(): void {
		$campaign = $this->create();
		self::assertSame( 'campaignbridge-campaign-reviewed-snapshot', $this->request( 'OPTIONS', $this->route( $campaign['id'], '/reviewed-snapshot' ) )->get_data()['schema']['title'] ?? null );
		self::assertSame( 'campaignbridge-campaign-snapshot-result', $this->request( 'OPTIONS', $this->route( $campaign['id'], '/snapshot' ) )->get_data()['schema']['title'] ?? null, 'The snapshot action keeps its own schema.' );
	}

	/** @return array<string, mixed> */
	private function create(): array {
		$response = $this->request( 'POST', self::COLLECTION, array( 'template_id' => $this->template_id ) );
		self::assertSame( 201, $response->get_status() );

		return $response->get_data()['campaign'];
	}

	private function route( string $id, string $suffix ): string {
		return self::COLLECTION . '/' . $id . $suffix;
	}

	/** @param array<string, mixed> $schema Published response schema. */
	private function assert_schema( array $schema, WP_REST_Response $response ): void {
		$valid = rest_validate_value_from_schema( $response->get_data(), $schema, 'response' );
		self::assertTrue( true === $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
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
