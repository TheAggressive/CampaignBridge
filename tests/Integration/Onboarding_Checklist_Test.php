<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Onboarding checklist and status health derive from stored state.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Controllers\Status_Controller;
use CampaignBridge\Admin\Onboarding_Checklist;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves each checklist step and each status figure reads what the site actually holds. */
final class Onboarding_Checklist_Test extends Test_Case {
	private const ROUTE = '/campaignbridge/v1/onboarding';

	private int $admin;

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );

		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
		// Migration DDL commits the test transaction, so templates from earlier tests can survive.
		foreach ( get_posts( array( 'post_type' => Post_Type_Email_Template::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
		( new Brand_Kit_Repository() )->clear();
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );

		do_action( 'rest_api_init' );
		Routes::register();

		$this->admin = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
	}

	public function tearDown(): void {
		( new Brand_Kit_Repository() )->clear();
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		parent::tearDown();
	}

	public function test_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		self::assertArrayHasKey( self::ROUTE, $routes );
		self::assertArrayHasKey( self::ROUTE . '/dismiss', $routes );
	}

	public function test_a_fresh_site_has_every_step_open(): void {
		$checklist = Onboarding_Checklist::for_user( $this->admin );

		self::assertSame( array( 'provider', 'audience', 'brand', 'template', 'campaign' ), array_column( $checklist['steps'], 'id' ) );
		self::assertSame( array( false, false, false, false, false ), array_column( $checklist['steps'], 'done' ) );
		self::assertFalse( $checklist['complete'] );
		self::assertTrue( $checklist['visible'] );
	}

	public function test_each_step_follows_its_stored_source(): void {
		$repository = new Provider_Connection_Repository();
		$repository->save( Provider_Connection::create( 'mailchimp', Encryption::encrypt( str_repeat( '0', 32 ) . '-us1' ) ) );
		self::assertSame( array( false, false ), $this->done( array( 'provider', 'audience' ) ), 'An unverified connection without an audience is not set up.' );

		$connection = $repository->get( 'mailchimp' );
		self::assertNotNull( $connection );
		$repository->save( $connection->with_verification( true, gmdate( 'Y-m-d H:i:s' ) )->with_audience( 'list-1' ) );
		self::assertSame( array( true, true ), $this->done( array( 'provider', 'audience' ) ) );

		( new Brand_Kit_Repository() )->save( Brand_Kit::from_colors( Brand_Kit::defaults()->colors() ) );
		self::assertSame( array( true ), $this->done( array( 'brand' ) ) );

		$draft = $this->template( 'draft' );
		self::assertSame( array( false ), $this->done( array( 'template' ) ), 'A draft template is not published.' );
		wp_publish_post( $draft );
		self::assertSame( array( true ), $this->done( array( 'template' ) ) );

		self::assertSame( array( false ), $this->done( array( 'campaign' ) ) );
		$this->campaign( $draft );
		self::assertSame( array( true ), $this->done( array( 'campaign' ) ) );
		self::assertTrue( Onboarding_Checklist::for_user( $this->admin )['complete'] );
	}

	public function test_mailchimp_steps_are_optional_for_html_export(): void {
		$this->complete_required_steps();

		$checklist = Onboarding_Checklist::for_user( $this->admin );

		self::assertSame( array( false, false ), $this->done( array( 'provider', 'audience' ) ) );
		self::assertTrue( $checklist['complete'] );
	}

	public function test_an_incomplete_checklist_cannot_be_dismissed(): void {
		$response = $this->request( 'POST', self::ROUTE . '/dismiss' );

		self::assertSame( 409, $response->get_status() );
		self::assertSame( 'campaignbridge_onboarding_incomplete', $response->get_data()['code'] );
		self::assertFalse( Onboarding_Checklist::for_user( $this->admin )['dismissed'] );
	}

	public function test_a_dismissed_checklist_returns_when_a_step_is_undone(): void {
		$template = $this->complete_required_steps();

		$response = $this->request( 'POST', self::ROUTE . '/dismiss' );
		self::assertSame( 200, $response->get_status() );
		self::assertFalse( $response->get_data()['visible'] );

		wp_trash_post( $template );
		$checklist = $this->request( 'GET', self::ROUTE )->get_data();

		self::assertFalse( $checklist['complete'] );
		self::assertTrue( $checklist['dismissed'] );
		self::assertTrue( $checklist['visible'], 'Losing the only published template reopens the checklist.' );
	}

	public function test_dismissal_is_per_user(): void {
		$this->complete_required_steps();
		self::assertSame( 200, $this->request( 'POST', self::ROUTE . '/dismiss' )->get_status() );

		$other = $this->create_test_user( array( 'role' => 'administrator' ) );

		self::assertFalse( Onboarding_Checklist::for_user( $other )['dismissed'] );
	}

	public function test_users_without_campaign_access_are_refused(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'subscriber' ) ) );

		self::assertSame( 403, $this->request( 'GET', self::ROUTE )->get_status() );
		self::assertSame( 403, $this->request( 'POST', self::ROUTE . '/dismiss' )->get_status() );
	}

	public function test_setup_links_are_offered_only_to_users_who_can_use_them(): void {
		$author = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $author )->add_cap( \CampaignBridge\Core\Capabilities::CREATE_CAMPAIGNS );

		$urls = array_column( Onboarding_Checklist::for_user( $author )['steps'], 'url', 'id' );

		self::assertSame( array( null, null, null, null, null ), array_values( $urls ) );
		self::assertNotNull( array_column( Onboarding_Checklist::for_user( $this->admin )['steps'], 'url', 'id' )['provider'] );
	}

	public function test_status_health_reports_stored_counts(): void {
		$template = $this->template( 'publish' );
		$first    = $this->campaign( $template );
		$this->campaign( $template );
		self::assertSame( 200, $this->request( 'POST', "/campaignbridge/v1/campaigns/{$first}/archive", array( 'expected_version' => 1 ) )->get_status() );

		$health = ( new Status_Controller() )->get_data()['health'];

		self::assertTrue( $health['schema_current'] );
		self::assertFalse( $health['provider_connected'] );
		self::assertNull( $health['last_verified_at'] );
		self::assertSame( 1, $health['published_templates'] );
		self::assertSame(
			array(
				'archived' => 1,
				'draft'    => 1,
			),
			$this->sorted( $health['campaign_states'] )
		);
		self::assertSame( 2, $health['campaign_total'] );
		self::assertSame( 0, $health['unknown_campaigns'] );
	}

	public function test_status_has_no_fixed_or_placeholder_figures(): void {
		$data = ( new Status_Controller() )->get_data();

		self::assertSame( array( 'system_info', 'plugin_info', 'health', 'encryption' ), array_keys( $data ) );
		self::assertSame( \CampaignBridge_Plugin::VERSION, $data['plugin_info']['version'] );

		$screen = (string) file_get_contents( \CampaignBridge_Plugin::path() . 'includes/Admin/Screens/status.php' );
		self::assertDoesNotMatchRegularExpression( '/\b\d+\.\d+\.\d+\b/', $screen, 'The status screen must not print a hardcoded version.' );
		self::assertStringNotContainsString( 'mock', strtolower( $screen ) );
	}

	/**
	 * @param array<int, string> $ids Step ids.
	 * @return array<int, bool>
	 */
	private function done( array $ids ): array {
		$steps = array_column( Onboarding_Checklist::for_user( $this->admin )['steps'], 'done', 'id' );

		return array_map( static fn ( string $id ): bool => $steps[ $id ], $ids );
	}

	/** Brand Kit, a published template, and a campaign; returns the template id. */
	private function complete_required_steps(): int {
		( new Brand_Kit_Repository() )->save( Brand_Kit::from_colors( Brand_Kit::defaults()->colors() ) );
		$template = $this->template( 'publish' );
		$this->campaign( $template );

		return $template;
	}

	private function template( string $status ): int {
		return $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => $status,
				'post_title'   => 'Onboarding template',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
				'post_author'  => $this->admin,
			)
		);
	}

	private function campaign( int $template ): string {
		$response = $this->request( 'POST', '/campaignbridge/v1/campaigns', array( 'template_id' => $template ) );
		self::assertSame( 201, $response->get_status() );

		return $response->get_data()['campaign']['id'];
	}

	/**
	 * @param array<string, int> $counts Counts by state.
	 * @return array<string, int>
	 */
	private function sorted( array $counts ): array {
		ksort( $counts );

		return $counts;
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
