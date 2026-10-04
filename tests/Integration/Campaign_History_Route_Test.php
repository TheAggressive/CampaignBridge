<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Campaign history and delivery attempt REST integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_History_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves campaign history is paged, authorized like the campaign itself, and free of secrets. */
final class Campaign_History_Route_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	private int $admin_id;
	private string $campaign_id;

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );

		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}

		do_action( 'rest_api_init' );
		Routes::register();

		$this->admin_id = $this->create_test_user(
			array(
				'role'         => 'administrator',
				'display_name' => 'Avery Admin',
			)
		);
		wp_set_current_user( $this->admin_id );
		$template          = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'History template',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
				'post_author'  => $this->admin_id,
			)
		);
		$this->campaign_id = $this->request( 'POST', self::COLLECTION, array( 'template_id' => $template ) )->get_data()['campaign']['id'];
	}

	public function test_history_is_paged_newest_first_with_actor_names(): void {
		$this->audit( 'campaign_snapshot', '2030-01-01T00:00:01Z' );
		$this->audit( 'campaign_submit_review', '2030-01-01T00:00:02Z' );

		$first = $this->request( 'GET', $this->route( '/history' ), array( 'per_page' => 2 ) );
		self::assertSame( 200, $first->get_status() );
		$this->assert_schema( Campaign_History_Schema::history(), $first );
		self::assertSame( 'no-store', $first->get_headers()['Cache-Control'] );
		self::assertSame( '3', $first->get_headers()['X-WP-Total'] );
		self::assertSame( '2', $first->get_headers()['X-WP-TotalPages'] );
		self::assertSame( array( 'campaign_submit_review', 'campaign_snapshot' ), array_column( $first->get_data()['items'], 'action' ) );
		self::assertSame(
			array(
				'id'   => $this->admin_id,
				'name' => 'Avery Admin',
			),
			$first->get_data()['items'][0]['actor']
		);

		$second = $this->request(
			'GET',
			$this->route( '/history' ),
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);
		self::assertSame( array( 'campaign_create' ), array_column( $second->get_data()['items'], 'action' ) );
		self::assertSame(
			array(
				'page'        => 2,
				'per_page'    => 2,
				'total'       => 3,
				'total_pages' => 2,
			),
			$second->get_data()['pagination']
		);

		$beyond = $this->request(
			'GET',
			$this->route( '/history' ),
			array(
				'per_page' => 2,
				'page'     => 3,
			)
		);
		self::assertSame( 400, $beyond->get_status() );
		self::assertSame( 400, $this->request( 'GET', $this->route( '/history' ), array( 'per_page' => 101 ) )->get_status() );
	}

	public function test_attempts_expose_presence_of_the_key_never_the_key(): void {
		$this->attempt( 'm3-live-send-secret-key', 'send', 'succeeded', 'f49a413ed9' );
		$this->attempt( null, 'test_send', 'unknown', null );

		$response = $this->request( 'GET', $this->route( '/attempts' ) );
		self::assertSame( 200, $response->get_status() );
		$this->assert_schema( Campaign_History_Schema::attempts(), $response );
		$items = $response->get_data()['items'];
		self::assertCount( 2, $items );
		self::assertSame( array( true, false ), array_column( $items, 'has_idempotency_key' ) );
		self::assertSame( 'f49a413ed9', $items[0]['remote_correlation'] );
		self::assertStringNotContainsString( 'secret-key', (string) wp_json_encode( $response->get_data() ) );
	}

	public function test_history_carries_only_the_redacted_audit_context(): void {
		$this->audit(
			'campaign_test_send',
			'2030-01-01T00:00:01Z',
			array(
				'destination_count' => 2,
				'api_key'           => 'abc123-us1',
				'recipient'         => 'person@example.com',
			)
		);

		$response = $this->request( 'GET', $this->route( '/history' ) );
		$encoded  = (string) wp_json_encode( $response->get_data() );

		self::assertSame( 2, ( (array) $response->get_data()['items'][0]['context'] )['destination_count'] ?? null );
		self::assertStringNotContainsString( 'abc123-us1', $encoded );
		self::assertStringNotContainsString( 'person@example.com', $encoded );
	}

	public function test_history_is_authorized_exactly_like_the_campaign_detail(): void {
		$author = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $author )->add_cap( Capabilities::CREATE_CAMPAIGNS );
		wp_set_current_user( $author );

		$detail = $this->request( 'GET', $this->route( '' ) );
		self::assertSame( 403, $detail->get_status(), 'Another author cannot read this campaign.' );
		foreach ( array( '/history', '/attempts' ) as $suffix ) {
			$refused = $this->request( 'GET', $this->route( $suffix ) );
			self::assertSame( $detail->get_status(), $refused->get_status(), $suffix );
			self::assertSame( $detail->get_data()['code'], $refused->get_data()['code'], $suffix );
			self::assertArrayNotHasKey( 'items', $refused->get_data() );
		}

		wp_set_current_user( $this->admin_id );
		$missing = $this->request( 'GET', self::COLLECTION . '/campaign-missing/history' );
		self::assertSame( $this->request( 'GET', self::COLLECTION . '/campaign-missing' )->get_status(), $missing->get_status() );

		wp_set_current_user( $this->create_test_user( array( 'role' => 'subscriber' ) ) );
		self::assertSame( 403, $this->request( 'GET', $this->route( '/history' ) )->get_status(), 'Users without campaign capabilities are refused before the workflow.' );
		wp_set_current_user( 0 );
		self::assertSame( 401, $this->request( 'GET', $this->route( '/attempts' ) )->get_status() );
	}

	public function test_both_routes_publish_their_schemas(): void {
		self::assertSame( 'campaignbridge-campaign-history', $this->request( 'OPTIONS', $this->route( '/history' ) )->get_data()['schema']['title'] ?? null );
		self::assertSame( 'campaignbridge-campaign-attempts', $this->request( 'OPTIONS', $this->route( '/attempts' ) )->get_data()['schema']['title'] ?? null );
	}

	/** @param array<string, mixed> $context Audit context. */
	private function audit( string $action, string $created_at, array $context = array( 'source' => 'test' ) ): void {
		self::assertTrue(
			( new Audit_Event_Repository() )->add(
				Audit_Event::from_array(
					array(
						'schema_version' => Audit_Event::SCHEMA_VERSION,
						'id'             => 'audit-' . md5( $action . $created_at ),
						'actor_user_id'  => $this->admin_id,
						'action'         => $action,
						'target_type'    => 'campaign',
						'target_id'      => $this->campaign_id,
						'result'         => 'success',
						'context'        => $context,
						'created_at'     => $created_at,
					)
				)
			)
		);
	}

	private function attempt( ?string $key, string $operation, string $status, ?string $remote ): void {
		static $sequence = 0;
		++$sequence;
		self::assertTrue(
			( new Delivery_Attempt_Repository() )->add(
				Delivery_Attempt::from_array(
					array(
						'schema_version'     => Delivery_Attempt::SCHEMA_VERSION,
						'id'                 => 'attempt-' . $sequence,
						'campaign_id'        => $this->campaign_id,
						'operation'          => $operation,
						'idempotency_key'    => $key,
						'status'             => $status,
						'retryability'       => 'unknown',
						'remote_correlation' => $remote,
						'created_at'         => sprintf( '2030-01-01T00:00:%02dZ', 10 - $sequence ),
						'updated_at'         => sprintf( '2030-01-01T00:00:%02dZ', 10 - $sequence ),
					)
				)
			)
		);
	}

	private function route( string $suffix ): string {
		return self::COLLECTION . '/' . $this->campaign_id . $suffix;
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
