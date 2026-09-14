<?php
/**
 * Template duplication authorization.
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
 * Duplication reads the saved source in the edit context and creates the copy
 * through core REST. Both boundaries enforce Capabilities::EDIT_TEMPLATES
 * through the template post type's mapped capabilities, not a role.
 */
final class Template_Duplication_Authorization_Test extends Test_Case {
	private int $source_id = 0;

	/**
	 * Create a published source template as an administrator.
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
		$this->source_id = (int) $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'   => 'Authorization source',
				'status'  => 'publish',
				'content' => 'Source content',
				'meta'    => array( 'campaignbridge_subject' => 'Source subject' ),
			)
		)->get_data()['id'];
	}

	public function test_a_user_without_the_template_capability_cannot_read_the_source_to_duplicate(): void {
		// Editors can edit posts, but not CampaignBridge templates.
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );

		$response = $this->dispatch( 'GET', "/wp/v2/cb_templates/{$this->source_id}", array( 'context' => 'edit' ) );

		self::assertSame( 403, $response->get_status() );
		self::assertArrayNotHasKey( 'content', (array) $response->get_data() );
	}

	public function test_a_user_without_the_template_capability_cannot_create_a_duplicate(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		$before = $this->template_count();

		$response = $this->dispatch( 'POST', '/wp/v2/cb_templates', $this->duplicate_payload() );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'rest_cannot_create', $response->as_error()->get_error_code() );
		self::assertSame( $before, $this->template_count() );
	}

	public function test_the_template_capability_authorizes_duplication_without_a_privileged_role(): void {
		$author_id = $this->create_test_user( array( 'role' => 'author' ) );
		get_userdata( $author_id )->add_cap( Capabilities::EDIT_TEMPLATES );
		wp_set_current_user( $author_id );

		$read = $this->dispatch( 'GET', "/wp/v2/cb_templates/{$this->source_id}", array( 'context' => 'edit' ) );
		self::assertSame( 200, $read->get_status() );

		$response = $this->dispatch( 'POST', '/wp/v2/cb_templates', $this->duplicate_payload() );

		self::assertSame( 201, $response->get_status() );
		self::assertSame( 'draft', $response->get_data()['status'] );
		self::assertSame( 'Source subject', $response->get_data()['meta']['campaignbridge_subject'] );
		self::assertSame( $author_id, (int) get_post( (int) $response->get_data()['id'] )->post_author );
	}

	/**
	 * The create payload the editor sends for the source template.
	 *
	 * @return array<string, mixed>
	 */
	private function duplicate_payload(): array {
		return array(
			'status'  => 'draft',
			'title'   => 'Authorization source (Copy)',
			'content' => 'Source content',
			'meta'    => array( 'campaignbridge_subject' => 'Source subject' ),
		);
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
	 * Dispatch a REST request.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  REST route.
	 * @param array<string, mixed> $params Query parameters for GET, JSON body otherwise.
	 */
	private function dispatch( string $method, string $route, array $params ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $params ) );
		}

		return rest_do_request( $request );
	}
}
