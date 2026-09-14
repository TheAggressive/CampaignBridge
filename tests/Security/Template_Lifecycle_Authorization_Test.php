<?php
/**
 * Template publish authorization.
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
 * Publishing a template runs through core REST and the template post type's
 * mapped capabilities: Capabilities::EDIT_TEMPLATES authorizes it, while
 * generic edit_posts and manage_options play no part.
 */
final class Template_Lifecycle_Authorization_Test extends Test_Case {
	private int $template_id = 0;

	/**
	 * Create a draft template as an administrator.
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
				'title'   => 'Publish authorization',
				'status'  => 'draft',
				'content' => 'Draft content',
			)
		)->get_data()['id'];
	}

	public function test_the_template_post_type_maps_publishing_to_the_template_capability(): void {
		$capabilities = get_post_type_object( Post_Type_Email_Template::POST_TYPE )->cap;

		self::assertSame( Capabilities::EDIT_TEMPLATES, $capabilities->publish_posts );
		self::assertSame( Capabilities::EDIT_TEMPLATES, $capabilities->edit_published_posts );
		self::assertSame( Capabilities::EDIT_TEMPLATES, $capabilities->create_posts );
	}

	public function test_generic_edit_posts_cannot_publish_an_existing_template(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		self::assertTrue( current_user_can( 'edit_posts' ) );
		self::assertTrue( current_user_can( 'publish_posts' ) );

		$response = $this->dispatch( 'PUT', "/wp/v2/cb_templates/{$this->template_id}", array( 'status' => 'publish' ) );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'draft', get_post_status( $this->template_id ) );
	}

	public function test_generic_edit_posts_cannot_create_a_published_template(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		$before = $this->template_count();

		$response = $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'  => 'Published by an editor',
				'status' => 'publish',
			)
		);

		self::assertSame( 403, $response->get_status() );
		self::assertSame( $before, $this->template_count() );
	}

	public function test_the_template_capability_publishes_without_manage_options(): void {
		$author_id = $this->create_test_user( array( 'role' => 'author' ) );
		get_userdata( $author_id )->add_cap( Capabilities::EDIT_TEMPLATES );
		wp_set_current_user( $author_id );
		self::assertFalse( current_user_can( 'manage_options' ) );

		$response = $this->dispatch( 'PUT', "/wp/v2/cb_templates/{$this->template_id}", array( 'status' => 'publish' ) );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'publish', get_post_status( $this->template_id ) );
	}

	/**
	 * Count templates in every status.
	 */
	private function template_count(): int {
		return count(
			get_posts(
				array(
					'post_type'      => Post_Type_Email_Template::POST_TYPE,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 100,
				)
			)
		);
	}

	/**
	 * Dispatch a JSON REST request.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  REST route.
	 * @param array<string, mixed> $body   JSON body.
	 */
	private function dispatch( string $method, string $route, array $body ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_do_request( $request );
	}
}
