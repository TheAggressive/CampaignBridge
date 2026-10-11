<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Campaign collection filters and per-campaign actions.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Campaign_List_Filter;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves operator screens can filter campaigns server-side and read the actions they may offer. */
final class Campaign_List_Route_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

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

		$admin = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'List template',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
				'post_author'  => $admin,
			)
		);
	}

	public function test_state_and_provider_filters_narrow_items_and_totals(): void {
		$export   = $this->create();
		$provider = $this->create(
			array(
				'provider'           => 'provider-one',
				'audience_reference' => 'list-1',
			)
		);
		$archived = $this->create();
		self::assertSame( 200, $this->request( 'POST', self::COLLECTION . "/{$archived['id']}/archive", array( 'expected_version' => 1 ) )->get_status() );

		$ids = fn ( array $query ): array => $this->ids( $this->request( 'GET', self::COLLECTION, $query ) );

		self::assertEqualsCanonicalizing( array( $export['id'], $provider['id'] ), $ids( array( 'state' => array( 'draft' ) ) ) );
		self::assertSame( array( $archived['id'] ), $ids( array( 'state' => array( 'archived' ) ) ) );
		self::assertEqualsCanonicalizing( array( $export['id'], $provider['id'], $archived['id'] ), $ids( array( 'state' => array( 'draft', 'archived' ) ) ) );
		self::assertSame( array( $provider['id'] ), $ids( array( 'provider' => 'provider-one' ) ) );
		self::assertEqualsCanonicalizing( array( $export['id'], $archived['id'] ), $ids( array( 'provider' => Campaign_List_Filter::NO_PROVIDER ) ) );
		self::assertSame(
			array( $export['id'] ),
			$ids(
				array(
					'provider' => Campaign_List_Filter::NO_PROVIDER,
					'state'    => array( 'draft' ),
				)
			)
		);

		$filtered = $this->request( 'GET', self::COLLECTION, array( 'state' => array( 'archived' ) ) );
		self::assertSame( 1, $filtered->get_data()['pagination']['total'] );
		self::assertSame( '1', $filtered->get_headers()['X-WP-Total'] );

		self::assertSame( 400, $this->request( 'GET', self::COLLECTION, array( 'state' => array( 'shipped' ) ) )->get_status() );
		self::assertSame( 400, $this->request( 'GET', self::COLLECTION, array( 'provider' => 'Not An Identifier' ) )->get_status() );
	}

	public function test_each_campaign_lists_the_actions_the_current_user_may_take(): void {
		$draft    = $this->create();
		$archived = $this->create();
		$this->request( 'POST', self::COLLECTION . "/{$archived['id']}/archive", array( 'expected_version' => 1 ) );

		$list = $this->request( 'GET', self::COLLECTION );
		$this->assert_schema( Campaign_Rest_Schema::collection(), $list );
		$actions = array_column( $list->get_data()['items'], 'actions', 'id' );

		self::assertSame( array( 'edit', 'snapshot', 'archive', 'duplicate' ), $actions[ $draft['id'] ] );
		self::assertSame( array( 'duplicate' ), $actions[ $archived['id'] ], 'An archived campaign cannot be changed or archived again.' );
		self::assertSame( array( 'edit', 'snapshot', 'archive', 'duplicate' ), $draft['actions'], 'Single-campaign responses carry the same actions.' );
	}

	/**
	 * @param array<string, mixed> $extra Additional create fields.
	 * @return array<string, mixed>
	 */
	private function create( array $extra = array() ): array {
		$response = $this->request( 'POST', self::COLLECTION, array( 'template_id' => $this->template_id ) + $extra );
		self::assertSame( 201, $response->get_status() );

		return $response->get_data()['campaign'];
	}

	public function test_actions_leave_out_template_steps_for_users_without_template_access(): void {
		$campaign = $this->create();
		self::assertContains( 'snapshot', $campaign['actions'] );

		$manager = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $manager )->add_cap( \CampaignBridge\Core\Capabilities::MANAGE );
		wp_set_current_user( $manager );
		$actions = $this->request( 'GET', self::COLLECTION . "/{$campaign['id']}" )->get_data()['campaign']['actions'];

		self::assertSame( array(), array_values( array_intersect( $actions, array( 'edit', 'snapshot', 'submit', 'approve', 'duplicate' ) ) ) );
		self::assertContains( 'archive', $actions );
		self::assertSame( 403, $this->request( 'POST', self::COLLECTION . "/{$campaign['id']}/snapshot", array( 'expected_version' => 1 ) )->get_status(), 'The workflow refuses what is not offered.' );
	}

	/** @return array<int, string> */
	private function ids( WP_REST_Response $response ): array {
		self::assertSame( 200, $response->get_status() );

		return array_column( $response->get_data()['items'], 'id' );
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
