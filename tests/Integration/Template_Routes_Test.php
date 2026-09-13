<?php
/**
 * Template revision restore route tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;

/**
 * Tests for the template revision restore REST endpoint.
 */
final class Template_Routes_Test extends Test_Case {
	private const ROUTE = '/campaignbridge/v1/templates/%d/revisions/%d/restore';

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		do_action( 'rest_api_init' );
		Routes::register();
	}

	/**
	 * Create a template whose earlier state WordPress saved as a real revision.
	 *
	 * @return array{template_id: int, revision_id: int}
	 */
	private function make_template_with_revision(): array {
		$template_id = $this->factory->post->create(
			array(
				'post_type'    => 'cb_templates',
				'post_title'   => 'Draft Title',
				'post_content' => 'Draft content',
			)
		);

		// Each update makes WordPress save a revision of the resulting state.
		wp_update_post(
			array(
				'ID'           => $template_id,
				'post_title'   => 'Revised Title',
				'post_content' => 'Revised content',
			)
		);
		$revision_id = (int) array_key_first( wp_get_post_revisions( $template_id ) );

		wp_update_post(
			array(
				'ID'           => $template_id,
				'post_title'   => 'Current Title',
				'post_content' => 'Current content',
			)
		);

		return array(
			'template_id' => $template_id,
			'revision_id' => $revision_id,
		);
	}

	/**
	 * Create a REST request with a valid wp_rest nonce.
	 *
	 * @param string $route REST route.
	 * @return WP_REST_Request
	 */
	private function make_nonce_request( string $route ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'X-WP-Nonce', \wp_create_nonce( 'wp_rest' ) );
		return $request;
	}

	/**
	 * Test that the revision restore route is registered.
	 */
	public function test_the_route_is_registered(): void {
		$expected_route = '/campaignbridge/v1/templates/(?P<id>\d+)/revisions/(?P<revision_id>\d+)/restore';
		$this->assertArrayHasKey( $expected_route, rest_get_server()->get_routes() );
	}

	/**
	 * Test that unauthenticated requests are rejected with 401.
	 */
	public function test_unauthenticated_request_is_rejected(): void {
		$ids      = $this->make_template_with_revision();
		$route    = sprintf( self::ROUTE, $ids['template_id'], $ids['revision_id'] );
		$request  = new WP_REST_Request( 'POST', $route );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test that users without the template capability are rejected with 403.
	 */
	public function test_user_without_capability_is_rejected(): void {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$ids      = $this->make_template_with_revision();
		$route    = sprintf( self::ROUTE, $ids['template_id'], $ids['revision_id'] );
		$request  = new WP_REST_Request( 'POST', $route );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test that restoring a nonexistent template returns 404.
	 */
	public function test_nonexistent_template_returns_404(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$request  = $this->make_nonce_request( sprintf( self::ROUTE, 99999, 1 ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test that restoring a nonexistent revision returns 404.
	 */
	public function test_nonexistent_revision_returns_404(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$template_id = $this->factory->post->create(
			array(
				'post_type'    => 'cb_templates',
				'post_title'   => 'Test Template',
				'post_content' => 'Content',
			)
		);

		$request  = $this->make_nonce_request( sprintf( self::ROUTE, $template_id, 99999 ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test that a revision from a different post is rejected with 400.
	 */
	public function test_revision_from_different_post_is_rejected(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		// A real revision belonging to Template A.
		$template_a  = $this->make_template_with_revision();
		$revision_id = $template_a['revision_id'];

		$template_b = $this->factory->post->create(
			array(
				'post_type'    => 'cb_templates',
				'post_title'   => 'Template B',
				'post_content' => 'Content B',
			)
		);

		// Try to restore Template A's revision onto Template B.
		$request  = $this->make_nonce_request( sprintf( self::ROUTE, $template_b, $revision_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Test that a successful restore updates the template content.
	 */
	public function test_successful_restore_updates_template_content(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$ids = $this->make_template_with_revision();

		$request  = $this->make_nonce_request( sprintf( self::ROUTE, $ids['template_id'], $ids['revision_id'] ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		// Verify the template content was updated.
		$template = get_post( $ids['template_id'] );
		$this->assertSame( 'Revised Title', $template->post_title );
		$this->assertSame( 'Revised content', $template->post_content );
	}

	/**
	 * Test that a request with an invalid nonce is rejected with 403.
	 */
	public function test_invalid_nonce_is_rejected(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		$ids     = $this->make_template_with_revision();
		$request = new WP_REST_Request( 'POST', sprintf( self::ROUTE, $ids['template_id'], $ids['revision_id'] ) );
		$request->set_header( 'X-WP-Nonce', 'invalid-nonce-value' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'invalid_nonce', $response->get_data()['code'] );
	}
}
