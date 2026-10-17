<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Webhook authentication, replay, and disclosure boundaries.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Admin\Controllers\Provider_Webhook_Handler;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Repository\Webhook_Subscription_Repository;
use CampaignBridge\REST\Provider_Webhook_Routes;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Tests\Integration\Provider_Webhook_Test;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the public webhook route admits only fresh, signed, first-time deliveries and discloses nothing. */
final class Provider_Webhook_Security_Test extends Test_Case {
	/** @var array<int, string> Outbound request URLs. */
	private array $outbound = array();

	/** @var callable|null */
	private $filter = null;

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );
		$this->truncate();
		do_action( 'rest_api_init' );
		Routes::register();
		$this->filter = function ( mixed $preempt, array $args, string $url ): array {
			$this->outbound[] = $url;

			return array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => 500,
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->filter, 10, 3 );
		Provider_Webhook_Test::store_secret( Provider_Webhook_Test::SECRET );
		wp_set_current_user( 0 );
	}

	public function tearDown(): void {
		global $wpdb;
		remove_filter( 'pre_http_request', $this->filter );
		( new Webhook_Subscription_Repository() )->remove( 'mailchimp', 'abc123' );
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%rate\_limit%'" );
		$this->truncate();
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	public function test_the_public_route_is_registered_with_the_signature_guard_for_known_providers_only(): void {
		$routes = rest_get_server()->get_routes( 'campaignbridge/v1' );
		self::assertArrayHasKey( '/campaignbridge/v1/webhooks/(?P<provider>mailchimp)', $routes );
		foreach ( $routes['/campaignbridge/v1/webhooks/(?P<provider>mailchimp)'] as $endpoint ) {
			self::assertSame( array( Provider_Webhook_Routes::class, 'authenticated_by_signature' ), $endpoint['permission_callback'] );
		}

		$unknown = new WP_REST_Request( 'POST', '/campaignbridge/v1/webhooks/brevo' );
		self::assertSame( 404, rest_get_server()->dispatch( $unknown )->get_status() );
	}

	public function test_a_wrong_missing_or_malformed_signature_is_refused_without_detail(): void {
		$body  = Provider_Webhook_Test::campaign_event( 'mc0042' );
		$now   = time();
		$cases = array(
			'wrong secret'      => Provider_Webhook_Test::deliver( $body, 'not-the-secret' ),
			'missing signature' => Provider_Webhook_Test::deliver( $body, signature: '' ),
			'garbage signature' => Provider_Webhook_Test::deliver( $body, signature: 'sha256=deadbeef' ),
			'body altered'      => Provider_Webhook_Test::deliver( $body . '&data%5Bid%5D=other', signature: 't=' . $now . ',v1=' . hash_hmac( 'sha256', $now . '.' . $body, Provider_Webhook_Test::SECRET ) ),
		);
		foreach ( $cases as $label => $response ) {
			self::assert_refused( $response, 401, $label );
		}
		self::assert_nothing_recorded();
	}

	public function test_a_stale_delivery_is_refused_so_a_late_replay_cannot_pass(): void {
		$stale = Provider_Webhook_Test::deliver( Provider_Webhook_Test::campaign_event( 'mc0042' ), Provider_Webhook_Test::SECRET, time() - 301 );

		self::assert_refused( $stale, 401, 'stale' );
		self::assert_nothing_recorded();
	}

	public function test_without_stored_webhooks_every_delivery_is_refused(): void {
		( new Webhook_Subscription_Repository() )->remove( 'mailchimp', 'abc123' );

		self::assert_refused( Provider_Webhook_Test::deliver( Provider_Webhook_Test::campaign_event( 'mc0042' ) ), 401, 'no secrets' );
	}

	public function test_an_oversized_body_is_refused_before_it_is_checked(): void {
		$body = Provider_Webhook_Test::campaign_event( 'mc0042' ) . '&pad=' . str_repeat( 'a', Provider_Webhook_Routes::MAX_BODY_BYTES );

		self::assert_refused( Provider_Webhook_Test::deliver( $body ), 413, 'oversized' );
		self::assert_nothing_recorded();
	}

	public function test_a_signed_but_malformed_body_is_refused(): void {
		self::assert_refused( Provider_Webhook_Test::deliver( 'type=campaign&data%5Bid%5D=..%2F..%2Fetc' ), 400, 'malformed' );
		self::assert_nothing_recorded();
	}

	public function test_a_replay_inside_the_window_is_answered_like_the_original_and_changes_nothing(): void {
		$body = Provider_Webhook_Test::campaign_event( 'mc0042' );
		$now  = time();

		$first  = Provider_Webhook_Test::deliver( $body, Provider_Webhook_Test::SECRET, $now );
		$replay = Provider_Webhook_Test::deliver( $body, Provider_Webhook_Test::SECRET, $now );

		self::assertSame( 200, $first->get_status() );
		self::assertSame( array( 200, $first->get_data() ), array( $replay->get_status(), $replay->get_data() ) );
		global $wpdb;
		$receipts = $wpdb->get_results( 'SELECT * FROM ' . Schema_Manager::table( 'webhook_receipts' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
		self::assertCount( 1, $receipts );
		self::assertSame( array( 'receipt_key', 'expires_at' ), array_keys( $receipts[0] ), 'Only a one-way key and its expiry are stored.' );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $receipts[0]['receipt_key'] );
		self::assertSame( $now + 300, (int) $receipts[0]['expires_at'] );
		self::assertSame( array(), $this->outbound, 'A delivery never reaches the provider from the request.' );
	}

	public function test_requests_are_rate_limited_per_client(): void {
		$statuses = array();
		for ( $i = 0; $i < 121; $i++ ) {
			$statuses[] = rest_get_server()->dispatch( new WP_REST_Request( 'GET', Provider_Webhook_Test::ROUTE ) )->get_status();
		}

		self::assertSame( array_fill( 0, 120, 200 ), array_slice( $statuses, 0, 120 ) );
		self::assertSame( 429, $statuses[120] );
	}

	public function test_only_connection_managers_can_change_webhooks(): void {
		( new Provider_Connection_Repository() )->save( Provider_Connection::create( 'mailchimp', Encryption::encrypt( str_repeat( 'c0ffee', 5 ) . 'c0-us20' ), '' ) );
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );

		foreach ( Provider_Webhook_Handler::ACTIONS as $action ) {
			self::assertNull( Provider_Webhook_Handler::handle( 'mailchimp', $action ), $action );
		}
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		self::assertNull( Provider_Webhook_Handler::handle( 'mailchimp', 'delete_everything' ) );
		self::assertNull( Provider_Webhook_Handler::handle( 'brevo', 'enable' ) );

		self::assertSame( array(), $this->outbound );
		self::assertCount( 1, ( new Webhook_Subscription_Repository() )->all( 'mailchimp' ) );
	}

	private static function assert_refused( WP_REST_Response $response, int $status, string $label ): void {
		self::assertSame( $status, $response->get_status(), $label );
		$data = $response->get_data();
		self::assertSame( 'The webhook delivery was not accepted.', $data['message'] ?? null, $label );
		self::assertStringNotContainsString( 'mc0042', (string) wp_json_encode( $data ), $label );
		self::assertStringNotContainsString( 'signature', strtolower( (string) wp_json_encode( $data ) ), $label );
	}

	private static function assert_nothing_recorded(): void {
		global $wpdb;
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema_Manager::table( 'webhook_receipts' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema_Manager::table( 'jobs' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
	}

	private function truncate(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
	}
}
