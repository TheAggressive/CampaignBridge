<?php
/**
 * Compiled preview endpoint tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Font_Registry;
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

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		get_userdata( $user_id )->add_cap( Capabilities::EDIT_TEMPLATES );
		wp_set_current_user( $user_id );
	}

	public function test_the_route_is_registered(): void {
		self::assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	public function test_compiles_content_into_the_documented_response_shape(): void {
		$response = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:core/heading {"content":"Hello","level":2} /-->'
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

	public function test_unsaved_design_fonts_compile_with_the_current_preview(): void {
		$slug     = Brand_Kit::custom_font_slug( 'Campaign Display' );
		$registry = Design_Font_Registry::from_array(
			array(
				'fonts' => array(
					array(
						'slug'    => $slug,
						'name'    => 'Campaign Display',
						'family'  => 'Campaign Display,Georgia,serif',
						'weights' => array( 400, 700 ),
						'url'     => 'https://fonts.googleapis.com/css2?family=Campaign+Display:wght@400;700&display=swap',
					),
				),
				'slots' => array( 'heading' => $slug ),
			)
		);
		$response = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:core/heading {"content":"Campaign heading","level":2} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			$registry->to_json()
		);
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( array(), $data['diagnostics'] );
		self::assertStringContainsString( 'font-family:Campaign Display,Georgia,serif', $data['html'] );
		self::assertStringContainsString( 'family=Campaign+Display', $data['html'] );
	}

	public function test_unsaved_design_font_compiles_as_an_explicit_block_override(): void {
		$slug     = Brand_Kit::custom_font_slug( 'Campaign Display' );
		$registry = Design_Font_Registry::from_array(
			array(
				'fonts' => array(
					array(
						'slug'    => $slug,
						'name'    => 'Campaign Display',
						'family'  => 'Campaign Display,Georgia,serif',
						'weights' => array( 400, 700 ),
						'url'     => 'https://fonts.googleapis.com/css2?family=Campaign+Display:wght@400;700&display=swap',
					),
				),
				'slots' => array(),
			)
		);
		$response = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:core/heading {"content":"Explicit campaign heading","level":2,"fontFamily":"'
			. $slug
			. '"} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			$registry->to_json()
		);
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( array(), $data['diagnostics'] );
		self::assertStringContainsString( 'font-family:Campaign Display,Georgia,serif', $data['html'] );
		self::assertStringContainsString( 'family=Campaign+Display', $data['html'] );
	}

	public function test_unsaved_design_font_on_a_button_overrides_the_button_slot(): void {
		$explicit = Brand_Kit::custom_font_slug( 'Campaign Display' );
		$slotted  = Brand_Kit::custom_font_slug( 'Campaign Slot' );
		$registry = Design_Font_Registry::from_array(
			array(
				'fonts' => array(
					array(
						'slug'    => $explicit,
						'name'    => 'Campaign Display',
						'family'  => 'Campaign Display,Georgia,serif',
						'weights' => array( 400, 700 ),
						'url'     => 'https://fonts.googleapis.com/css2?family=Campaign+Display:wght@400;700&display=swap',
					),
					array(
						'slug'    => $slotted,
						'name'    => 'Campaign Slot',
						'family'  => 'Campaign Slot,Arial,sans-serif',
						'weights' => array( 400, 700 ),
						'url'     => 'https://fonts.googleapis.com/css2?family=Campaign+Slot:wght@400;700&display=swap',
					),
				),
				'slots' => array( 'button' => $slotted ),
			)
		);
		$response = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"fontFamily":"' . $explicit . '"} -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link has-' . $explicit . '-font-family wp-element-button" href="https://example.com/offer">Explicit campaign button</a></div>'
			. '<!-- /wp:button --></div><!-- /wp:buttons -->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			$registry->to_json()
		);
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( array(), $data['diagnostics'] );
		self::assertMatchesRegularExpression(
			'/<a [^>]*style="[^"]*font-family:Campaign Display,Georgia,serif;[^"]*"[^>]*>Explicit campaign button<\/a>/',
			$data['html']
		);
		self::assertStringNotContainsString( 'Campaign Slot', $data['html'] );
		self::assertSame( 1, substr_count( $data['html'], 'family=Campaign+Display' ) );
		self::assertSame( 0, substr_count( $data['html'], 'family=Campaign+Slot' ) );
	}

	public function test_sample_view_is_null_without_provider_tokens(): void {
		$data = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:core/heading {"content":"Hello","level":2} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->'
		)->get_data();

		self::assertArrayHasKey( 'sample', $data );
		self::assertNull( $data['sample'] );
	}

	public function test_sample_view_personalizes_without_changing_the_canonical_artifact(): void {
		$data = $this->preview(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:paragraph --><p>Hi {{cb:subscriber.first_name}} <a href="{{cb:campaign.unsubscribe_url}}">leave</a></p><!-- /wp:paragraph -->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->'
		)->get_data();

		self::assertSame( array(), $data['diagnostics'] );
		self::assertStringContainsString( 'Hi {{cb:subscriber.first_name}}', $data['html'] );
		self::assertStringContainsString( 'href="{{cb:campaign.unsubscribe_url}}"', $data['html'] );
		self::assertStringContainsString( 'Hi {{cb:subscriber.first_name}}', $data['text'] );

		self::assertSame( array( 'html', 'text' ), array_keys( $data['sample'] ) );
		self::assertStringContainsString( 'Hi Alex', $data['sample']['html'] );
		self::assertStringContainsString( 'href="https://example.com/campaignbridge-preview/unsubscribe"', $data['sample']['html'] );
		self::assertStringContainsString( 'Hi Alex', $data['sample']['text'] );
		self::assertStringNotContainsString( '{{cb:', $data['sample']['html'] . $data['sample']['text'] );
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
		self::assertNull( $data['sample'] );
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
		$request->set_param( 'content', '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:core/spacer /--><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' );
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
	 * @param string      $content      Serialized block markup.
	 * @param string|null $design_fonts Optional unsaved design-font registry.
	 */
	private function preview( string $content, ?string $design_fonts = null ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'template_id', $this->template_id );
		$request->set_param( 'content', $content );
		if ( null !== $design_fonts ) {
			$request->set_param( 'design_fonts', $design_fonts );
		}

		return rest_get_server()->dispatch( $request );
	}
}
