<?php
/**
 * Brand kit REST authorization tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;

final class Brand_Kit_Routes_Test extends Test_Case {
	private const ROUTE = '/campaignbridge/v1/brand-kit';

	public function setUp(): void {
		parent::setUp();
		do_action( 'rest_api_init' );
		Routes::register();
	}

	public function test_get_rejects_a_subscriber(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'subscriber' ) ) );

		$request  = new WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_put_rejects_a_subscriber(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'id', Brand_Kit::SLOT_BRAND );
		$request->set_param( 'color', '#ff5500' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
