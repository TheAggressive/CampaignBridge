<?php
/**
 * Template metadata authorization and sanitization.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Proves Capabilities::EDIT_TEMPLATES, not a WordPress role, controls every
 * template metadata mutation path, and that stored values stay sanitized.
 */
final class Template_Meta_Authorization_Test extends Test_Case {
	private int $template_id = 0;

	/**
	 * Create a published template with a real revision as an administrator.
	 */
	public function setUp(): void {
		parent::setUp();
		// The WordPress test case unregisters custom post types and restores
		// hooks between tests; register the template model as plugin init does.
		Post_Type_Email_Template::register_post_type();
		Post_Type_Email_Template::register_meta_fields();
		do_action( 'rest_api_init' );
		Routes::register();

		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$this->template_id = (int) $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'  => 'Authorization template',
				'status' => 'publish',
			)
		)->get_data()['id'];
		$this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$this->template_id}",
			array(
				'content' => 'Original content',
				'meta'    => array( 'campaignbridge_subject' => 'Original subject' ),
			)
		);
	}

	public function test_every_template_meta_key_is_guarded_by_the_template_capability(): void {
		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		$granted   = $this->create_test_user( array( 'role' => 'author' ) );
		get_userdata( $granted )->add_cap( Capabilities::EDIT_TEMPLATES );

		foreach ( Post_Type_Email_Template::get_meta_field_keys() as $key ) {
			wp_set_current_user( $editor_id );
			self::assertFalse( current_user_can( 'edit_post_meta', $this->template_id, $key ), $key );

			wp_set_current_user( $granted );
			self::assertTrue( current_user_can( 'edit_post_meta', $this->template_id, $key ), $key );
		}
	}

	public function test_a_user_without_the_template_capability_cannot_change_revisioned_meta(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		$revisions = count( wp_get_post_revisions( $this->template_id ) );

		$response = $this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$this->template_id}",
			array( 'meta' => array( 'campaignbridge_subject' => 'Hijacked subject' ) )
		);

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'Original subject', get_post_meta( $this->template_id, 'campaignbridge_subject', true ) );
		self::assertCount( $revisions, wp_get_post_revisions( $this->template_id ) );
	}

	public function test_a_user_without_the_template_capability_cannot_autosave_meta(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/cb_templates/{$this->template_id}/autosaves",
			array(
				'content' => 'Hijacked autosave',
				'meta'    => array( 'campaignbridge_subject' => 'Hijacked subject' ),
			)
		);

		self::assertSame( 403, $response->get_status() );
		self::assertFalse( wp_get_post_autosave( $this->template_id ) );
		self::assertSame( 'Original subject', get_post_meta( $this->template_id, 'campaignbridge_subject', true ) );
	}

	public function test_a_user_without_the_template_capability_cannot_restore_revisioned_meta(): void {
		$revision_id = (int) array_key_first( wp_get_post_revisions( $this->template_id ) );
		$this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$this->template_id}",
			array( 'meta' => array( 'campaignbridge_subject' => 'Current subject' ) )
		);

		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', "/campaignbridge/v1/templates/{$this->template_id}/revisions/{$revision_id}/restore" );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_get_server()->dispatch( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'Current subject', get_post_meta( $this->template_id, 'campaignbridge_subject', true ) );
	}

	public function test_rest_saves_store_sanitized_template_meta(): void {
		$response = $this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$this->template_id}",
			array(
				'meta' => array(
					'campaignbridge_subject'           => '<b>Bold</b> subject',
					'campaignbridge_sender_email'      => 'not an email',
					'campaignbridge_view_online_url'   => 'javascript:alert(1)',
					'campaignbridge_address_html'      => '<p>1 Main St</p><script>alert(1)</script>',
					'campaignbridge_template_category' => '<script>alert(1)</script>',
				),
			)
		);
		self::assertSame( 200, $response->get_status() );

		self::assertSame( 'Bold subject', get_post_meta( $this->template_id, 'campaignbridge_subject', true ) );
		self::assertSame( '', get_post_meta( $this->template_id, 'campaignbridge_sender_email', true ) );
		self::assertSame( '', get_post_meta( $this->template_id, 'campaignbridge_view_online_url', true ) );
		self::assertSame( '<p>1 Main St</p>alert(1)', get_post_meta( $this->template_id, 'campaignbridge_address_html', true ) );
		// The category enum falls back instead of storing arbitrary markup.
		self::assertSame( 'general', get_post_meta( $this->template_id, 'campaignbridge_template_category', true ) );
	}

	/**
	 * Dispatch a REST request with body parameters.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  REST route.
	 * @param array<string, mixed> $body   Body parameters.
	 */
	private function dispatch( string $method, string $route, array $body ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $body );

		return rest_get_server()->dispatch( $request );
	}
}
