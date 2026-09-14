<?php
/**
 * Native autosave interoperability with revisioned template metadata.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exercises the real core autosaves endpoint the editor calls.
 *
 * The endpoint defines DOING_AUTOSAVE for the rest of the PHP process, which
 * would disable revisions in unrelated tests, so each test runs in isolation.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class Template_Autosave_Revision_Meta_Test extends Test_Case {
	/**
	 * Set up an administrator and the REST routes.
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
	}

	public function test_published_autosave_holds_recovery_meta_without_touching_canonical_state_or_history(): void {
		$template_id = $this->create_template( 'publish' );
		$this->update_template( $template_id, 'First canonical', 'First subject', 'newsletter' );
		$this->update_template( $template_id, 'Canonical content', 'Canonical subject', 'newsletter' );
		$normal_revisions = $this->normal_revision_ids( $template_id );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/cb_templates/{$template_id}/autosaves",
			array(
				'content' => 'Recovery content',
				'meta'    => array(
					'campaignbridge_subject'           => 'Recovery subject',
					'campaignbridge_template_category' => 'welcome',
				),
			)
		);
		self::assertSame( 200, $response->get_status() );

		// The autosave is a separate recovery record holding revisioned meta only.
		$autosave = wp_get_post_autosave( $template_id );
		self::assertNotFalse( $autosave );
		self::assertSame( $autosave->ID, $response->get_data()['id'] );
		self::assertSame( 'Recovery content', $autosave->post_content );
		self::assertSame( 'Recovery subject', get_post_meta( $autosave->ID, 'campaignbridge_subject', true ) );
		self::assertFalse( metadata_exists( 'post', $autosave->ID, 'campaignbridge_template_category' ) );

		// The canonical template and its revision history are unchanged.
		self::assertSame( 'Canonical content', get_post( $template_id )->post_content );
		self::assertSame( 'Canonical subject', get_post_meta( $template_id, 'campaignbridge_subject', true ) );
		self::assertSame( 'newsletter', get_post_meta( $template_id, 'campaignbridge_template_category', true ) );
		self::assertSame( $normal_revisions, $this->normal_revision_ids( $template_id ) );

		// Restoring a normal revision uses canonical history, not the autosave.
		$first   = $this->normal_revision_with_content( $template_id, 'First canonical' );
		$restore = new WP_REST_Request( 'POST', "/campaignbridge/v1/templates/{$template_id}/revisions/{$first}/restore" );
		$restore->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		self::assertSame( 200, rest_get_server()->dispatch( $restore )->get_status() );

		self::assertSame( 'First canonical', get_post( $template_id )->post_content );
		self::assertSame( 'First subject', get_post_meta( $template_id, 'campaignbridge_subject', true ) );
		self::assertSame( 'Recovery subject', get_post_meta( $autosave->ID, 'campaignbridge_subject', true ) );
	}

	public function test_an_autosave_cannot_be_restored_as_template_history(): void {
		$template_id = $this->create_template( 'publish' );
		$this->update_template( $template_id, 'Canonical content', 'Canonical subject', 'newsletter' );
		$response = $this->dispatch(
			'POST',
			"/wp/v2/cb_templates/{$template_id}/autosaves",
			array(
				'content' => 'Recovery content',
				'meta'    => array( 'campaignbridge_subject' => 'Recovery subject' ),
			)
		);
		self::assertSame( 200, $response->get_status() );
		$autosave_id = (int) $response->get_data()['id'];
		self::assertNotFalse( wp_is_post_autosave( $autosave_id ) );

		$restore = new WP_REST_Request( 'POST', "/campaignbridge/v1/templates/{$template_id}/revisions/{$autosave_id}/restore" );
		$restore->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$result = rest_get_server()->dispatch( $restore );

		self::assertSame( 400, $result->get_status() );
		self::assertSame( 'revision_is_autosave', $result->get_data()['code'] );
		self::assertSame( 'Canonical content', get_post( $template_id )->post_content );
		self::assertSame( 'Canonical subject', get_post_meta( $template_id, 'campaignbridge_subject', true ) );
	}

	public function test_draft_autosave_updates_draft_fields_without_meta_or_a_revision(): void {
		$template_id = $this->create_template( 'draft' );
		$this->update_template( $template_id, 'Draft content', 'Draft subject', 'newsletter' );
		$revisions = wp_get_post_revisions( $template_id );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/cb_templates/{$template_id}/autosaves",
			array(
				'content' => 'Autosaved draft content',
				'meta'    => array( 'campaignbridge_subject' => 'Autosaved subject' ),
			)
		);
		self::assertSame( 200, $response->get_status() );

		// WordPress applies a draft author's autosave to the post itself...
		self::assertSame( $template_id, $response->get_data()['id'] );
		self::assertSame( 'Autosaved draft content', get_post( $template_id )->post_content );
		self::assertSame( 'draft', get_post_status( $template_id ) );
		// ...ignores the submitted meta (the editor must keep that edit dirty)...
		self::assertSame( 'Draft subject', get_post_meta( $template_id, 'campaignbridge_subject', true ) );
		self::assertSame( 'Draft subject', $response->get_data()['meta']['campaignbridge_subject'] );
		// ...and creates neither an autosave record nor a normal revision.
		self::assertFalse( wp_get_post_autosave( $template_id ) );
		self::assertSame( array_keys( $revisions ), array_keys( wp_get_post_revisions( $template_id ) ) );
	}

	/**
	 * Create a template through the core REST endpoint.
	 *
	 * @param string $status Post status.
	 */
	private function create_template( string $status ): int {
		$response = $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'  => 'Autosave meta template',
				'status' => $status,
			)
		);
		self::assertSame( 201, $response->get_status() );

		return (int) $response->get_data()['id'];
	}

	/**
	 * Save content and metadata through the core REST endpoint.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $content     Post content.
	 * @param string $subject     Subject meta.
	 * @param string $category    Category meta.
	 */
	private function update_template( int $template_id, string $content, string $subject, string $category ): void {
		$response = $this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$template_id}",
			array(
				'content' => $content,
				'meta'    => array(
					'campaignbridge_subject'           => $subject,
					'campaignbridge_template_category' => $category,
				),
			)
		);
		self::assertSame( 200, $response->get_status() );
	}

	/**
	 * IDs of revisions that are not autosaves.
	 *
	 * @param int $template_id Template ID.
	 * @return array<int, int>
	 */
	private function normal_revision_ids( int $template_id ): array {
		$ids = array();
		foreach ( wp_get_post_revisions( $template_id ) as $revision ) {
			if ( ! wp_is_post_autosave( $revision ) ) {
				$ids[] = $revision->ID;
			}
		}

		return $ids;
	}

	/**
	 * Find the normal revision holding the given content.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $content     Post content.
	 */
	private function normal_revision_with_content( int $template_id, string $content ): int {
		foreach ( wp_get_post_revisions( $template_id ) as $revision ) {
			if ( $content === $revision->post_content && ! wp_is_post_autosave( $revision ) ) {
				return $revision->ID;
			}
		}

		self::fail( "No normal revision holds content: {$content}" );
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
