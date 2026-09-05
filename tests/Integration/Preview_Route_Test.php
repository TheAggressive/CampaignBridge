<?php
/**
 * Compiled preview endpoint tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\REST\Routes;
use WP_REST_Request;

final class Preview_Route_Test extends \WP_UnitTestCase {
	private const ROUTE = '/campaignbridge/v1/preview';

	private int $template_id = 0;

	public function set_up(): void {
		parent::set_up();

		do_action( 'rest_api_init' );
		Routes::register();

		$this->template_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Weekly update',
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_the_route_is_registered(): void {
		self::assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	public function test_compiles_content_into_the_documented_response_shape(): void {
		$response = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/heading {"content":"Hello","level":2} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->'
		);

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		foreach ( array( 'html', 'text', 'diagnostics', 'assets', 'compiler_version', 'profile_version', 'fingerprint' ) as $key ) {
			self::assertArrayHasKey( $key, $data );
		}

		self::assertStringContainsString( 'Hello', $data['html'] );
		self::assertSame( array(), $data['diagnostics'] );
		self::assertSame( 'universal@1', $data['profile_version'] );
	}

	public function test_a_rejected_document_returns_diagnostics_not_a_server_error(): void {
		$response = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:core/paragraph -->'
			. '<p>Nope</p><!-- /wp:core/paragraph --><!-- /wp:campaignbridge/container -->'
		);

		// A document the compiler refuses is a result the editor must show.
		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		self::assertSame( '', $data['html'] );
		self::assertNotEmpty( $data['diagnostics'] );
		self::assertSame( 'block.child.unsupported', $data['diagnostics'][0]['code'] );
	}

	public function test_the_template_unsubscribe_url_wins_over_a_supplied_one(): void {
		update_post_meta( $this->template_id, 'campaignbridge_unsubscribe_url', 'https://example.com/real' );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'template_id', $this->template_id );
		$request->set_param(
			'content',
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/compliance-footer {"address":"1 Example St"} /-->'
			. '<!-- /wp:campaignbridge/container -->'
		);
		$request->set_param( 'metadata', array( 'unsubscribe_url' => 'https://attacker.test/phish' ) );

		$data = rest_get_server()->dispatch( $request )->get_data();

		self::assertStringContainsString( 'https://example.com/real', $data['html'] );
		self::assertStringNotContainsString( 'attacker.test', $data['html'] );
	}

	public function test_unknown_metadata_keys_are_dropped(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'template_id', $this->template_id );
		$request->set_param( 'content', '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:campaignbridge/spacer /--><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' );
		$request->set_param(
			'metadata',
			array(
				'background_color' => '#ff0000',
				'evil'             => '<script>',
			) 
		);

		$data = rest_get_server()->dispatch( $request )->get_data();

		self::assertStringContainsString( '#ff0000', $data['html'] );
		self::assertStringNotContainsString( '<script>', $data['html'] );
	}

	public function test_requires_authentication(): void {
		wp_set_current_user( 0 );

		self::assertNotSame( 200, $this->preview( '<!-- wp:campaignbridge/container /-->' )->get_status() );
	}

	public function test_refuses_a_template_the_user_cannot_edit(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		self::assertNotSame( 200, $this->preview( '<!-- wp:campaignbridge/container /-->' )->get_status() );
	}

	public function test_reports_a_missing_template(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'template_id', 99999999 );
		$request->set_param( 'content', '<!-- wp:campaignbridge/container /-->' );

		self::assertSame( 404, rest_get_server()->dispatch( $request )->get_status() );
	}

	/**
	 * Dispatch a preview request for the seeded template.
	 *
	 * @param string $content Serialized block markup.
	 */
	private function preview( string $content ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'template_id', $this->template_id );
		$request->set_param( 'content', $content );

		return rest_get_server()->dispatch( $request );
	}
}
