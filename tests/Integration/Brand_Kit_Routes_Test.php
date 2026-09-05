<?php
/**
 * Brand kit REST route tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;

final class Brand_Kit_Routes_Test extends Test_Case {
	private const ROUTE = '/campaignbridge/v1/brand-kit';

	public function setUp(): void {
		parent::setUp();
		do_action( 'rest_api_init' );
		Routes::register();
		( new Brand_Kit_Repository() )->clear();
	}

	public function tearDown(): void {
		( new Brand_Kit_Repository() )->clear();
		parent::tearDown();
	}

	public function test_the_route_is_registered(): void {
		$this->assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	public function test_get_returns_the_seven_slots(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request  = new WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Brand_Kit::SOURCE_DEFAULTS, $data['source'] );
		$this->assertCount( 7, $data['slots'] );
		$this->assertSame( Brand_Kit::SLOTS, array_column( $data['slots'], 'id' ) );
	}

	public function test_put_updates_one_slot_and_marks_the_kit_custom(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'id', Brand_Kit::SLOT_BRAND );
		$request->set_param( 'color', '#ff5500' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Brand_Kit::SOURCE_CUSTOM, $data['source'] );

		$brand = null;
		foreach ( $data['slots'] as $slot ) {
			if ( Brand_Kit::SLOT_BRAND === $slot['id'] ) {
				$brand = $slot['color'];
			}
		}

		$this->assertSame( '#ff5500', $brand );
		$this->assertSame( '#ff5500', ( new Brand_Kit_Repository() )->get()->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_put_rejects_a_colour_that_is_not_portable(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'id', Brand_Kit::SLOT_BRAND );
		$request->set_param( 'color', 'oklch(0.7 0.1 200)' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_writing_the_same_colour_twice_is_not_a_server_error(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$first  = $this->put_color( Brand_Kit::SLOT_BRAND, '#123456' );
		$second = $this->put_color( Brand_Kit::SLOT_BRAND, '#123456' );

		self::assertSame( 200, $first->get_status(), 'first write' );
		self::assertSame( 200, $second->get_status(), 'identical rewrite' );
	}

	/**
	 * Dispatch a brand colour update.
	 *
	 * @param string $slug  Slot slug.
	 * @param string $color Six-digit colour.
	 */
	private function put_color( string $slug, string $color ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'PUT', '/campaignbridge/v1/brand-kit' );
		$request->set_param( 'id', $slug );
		$request->set_param( 'color', $color );

		return rest_get_server()->dispatch( $request );
	}
}
