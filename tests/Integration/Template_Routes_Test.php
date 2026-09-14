<?php
/**
 * Template revision restore route contract.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes;
use CampaignBridge\REST\Template_Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_Application_Passwords;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exercises the restore route through the REST server with WordPress-created
 * revisions and WordPress's own capability mapping.
 */
final class Template_Routes_Test extends Test_Case {
	private const ROUTE = '/campaignbridge/v1/templates/%d/revisions/%d/restore';

	private int $admin_id = 0;

	private ?string $original_remote_addr = null;

	/**
	 * Register the template model and routes, and create an administrator.
	 */
	public function setUp(): void {
		parent::setUp();
		// The WordPress test case unregisters custom post types and restores
		// hooks between tests; register the template model as plugin init does.
		Post_Type_Email_Template::register_post_type();
		Post_Type_Email_Template::register_meta_fields();
		do_action( 'rest_api_init' );
		Routes::register();
		$this->admin_id             = $this->create_test_user( array( 'role' => 'administrator' ) );
		$this->original_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : null;
	}

	/**
	 * Clear the simulated HTTP request details.
	 */
	public function tearDown(): void {
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		if ( null === $this->original_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}
		parent::tearDown();
	}

	public function test_the_route_is_registered(): void {
		$route = '/campaignbridge/v1/templates/(?P<id>\d+)/revisions/(?P<revision_id>\d+)/restore';
		self::assertArrayHasKey( $route, rest_get_server()->get_routes() );
	}

	public function test_an_administrator_restores_a_template_revision(): void {
		$ids = $this->make_template_with_revision();
		wp_set_current_user( $this->admin_id );

		$response = $this->restore( $ids['template_id'], $ids['revision_id'] );

		self::assertSame( 200, $response->get_status() );
		self::assertTrue( $response->get_data()['success'] );
		self::assertSame( 'Revised content', get_post( $ids['template_id'] )->post_content );
		self::assertSame( 'Revised Title', get_post( $ids['template_id'] )->post_title );
	}

	public function test_an_unauthenticated_request_is_rejected_without_changes(): void {
		$ids = $this->make_template_with_revision();
		wp_set_current_user( 0 );

		$this->assert_rejected_without_changes( $ids, Template_Routes::ERROR_UNAUTHENTICATED, 401 );
	}

	public function test_generic_edit_posts_does_not_grant_template_restore(): void {
		$ids       = $this->make_template_with_revision();
		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		self::assertTrue( user_can( $editor_id, 'edit_posts' ) );
		self::assertTrue( user_can( $editor_id, 'edit_others_posts' ) );
		self::assertFalse( user_can( $editor_id, Capabilities::EDIT_TEMPLATES ) );
		wp_set_current_user( $editor_id );

		$this->assert_rejected_without_changes( $ids, Template_Routes::ERROR_FORBIDDEN, 403 );
	}

	public function test_a_subscriber_is_rejected(): void {
		$ids = $this->make_template_with_revision();
		wp_set_current_user( $this->create_test_user( array( 'role' => 'subscriber' ) ) );

		$this->assert_rejected_without_changes( $ids, Template_Routes::ERROR_FORBIDDEN, 403 );
	}

	public function test_the_template_capability_alone_authorizes_restoring_another_users_template(): void {
		// A published template owned by the administrator.
		$ids     = $this->make_template_with_revision();
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $user_id )->add_cap( Capabilities::EDIT_TEMPLATES );
		wp_set_current_user( $user_id );

		// WordPress maps edit_post on this template to the template capability.
		self::assertTrue( current_user_can( 'edit_post', $ids['template_id'] ) );

		$response = $this->restore( $ids['template_id'], $ids['revision_id'] );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'Revised content', get_post( $ids['template_id'] )->post_content );
	}

	public function test_permission_is_checked_for_the_specific_template(): void {
		$denied  = $this->make_template_with_revision();
		$allowed = $this->make_template_with_revision();
		wp_set_current_user( $this->admin_id );

		// WordPress's own object-capability extension point denies one template.
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $user_id, array $args ) use ( $denied ): array {
				unset( $user_id );
				return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $denied['template_id']
					? array( 'do_not_allow' )
					: $caps;
			},
			10,
			4
		);

		$this->assert_rejected_without_changes( $denied, Template_Routes::ERROR_FORBIDDEN, 403 );
		self::assertSame( 200, $this->restore( $allowed['template_id'], $allowed['revision_id'] )->get_status() );
	}

	public function test_a_missing_template_is_not_found(): void {
		wp_set_current_user( $this->admin_id );

		$this->assert_error( $this->restore( 999999, 1 ), Template_Routes::ERROR_TEMPLATE_NOT_FOUND, 404 );
	}

	public function test_a_post_that_is_not_an_email_template_is_not_found(): void {
		$post = $this->make_blog_post_with_revision();
		wp_set_current_user( $this->admin_id );

		$this->assert_error( $this->restore( $post['post_id'], $post['revision_id'] ), Template_Routes::ERROR_TEMPLATE_NOT_FOUND, 404 );
		self::assertSame( 'Blog current', get_post( $post['post_id'] )->post_content );

		// A revision ID is not a template either.
		$ids = $this->make_template_with_revision();
		$this->assert_error( $this->restore( $ids['revision_id'], $ids['revision_id'] ), Template_Routes::ERROR_TEMPLATE_NOT_FOUND, 404 );
	}

	public function test_a_missing_revision_is_not_found_without_changes(): void {
		$ids = $this->make_template_with_revision();
		wp_set_current_user( $this->admin_id );

		$this->assert_rejected_without_changes(
			array(
				'template_id' => $ids['template_id'],
				'revision_id' => 999999,
			),
			Template_Routes::ERROR_REVISION_NOT_FOUND,
			404
		);

		// Another email template's ID is not a revision.
		$other = $this->make_template_with_revision();
		$this->assert_rejected_without_changes(
			array(
				'template_id' => $ids['template_id'],
				'revision_id' => $other['template_id'],
			),
			Template_Routes::ERROR_REVISION_NOT_FOUND,
			404
		);
	}

	public function test_a_revision_of_another_template_is_rejected_without_changes(): void {
		$target = $this->make_template_with_revision();
		$other  = $this->make_template_with_revision();
		wp_set_current_user( $this->admin_id );

		$this->assert_rejected_without_changes(
			array(
				'template_id' => $target['template_id'],
				'revision_id' => $other['revision_id'],
			),
			Template_Routes::ERROR_REVISION_MISMATCH,
			400
		);
		self::assertSame( 'Current content', get_post( $other['template_id'] )->post_content );
	}

	public function test_a_revision_of_a_normal_post_is_rejected_without_changes(): void {
		$target = $this->make_template_with_revision();
		$post   = $this->make_blog_post_with_revision();
		wp_set_current_user( $this->admin_id );

		$this->assert_rejected_without_changes(
			array(
				'template_id' => $target['template_id'],
				'revision_id' => $post['revision_id'],
			),
			Template_Routes::ERROR_REVISION_MISMATCH,
			400
		);
		self::assertSame( 'Blog current', get_post( $post['post_id'] )->post_content );
	}

	public function test_restore_is_refused_when_revisions_are_disabled_for_the_template(): void {
		$ids = $this->make_template_with_revision();
		wp_set_current_user( $this->admin_id );

		// WordPress's own revision limit disables revisions for this template.
		add_filter(
			'wp_revisions_to_keep',
			static fn ( int $num, \WP_Post $post ): int => $ids['template_id'] === $post->ID ? 0 : $num,
			10,
			2
		);
		self::assertFalse( wp_revisions_enabled( get_post( $ids['template_id'] ) ) );

		$this->assert_rejected_without_changes( $ids, Template_Routes::ERROR_REVISIONS_DISABLED, 409 );
	}

	public function test_an_invalid_template_id_is_rejected_by_the_route_schema_without_changes(): void {
		$ids = $this->make_template_with_revision();
		wp_set_current_user( $this->admin_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'POST', sprintf( self::ROUTE, 0, $ids['revision_id'] ) ) );

		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assert_template_unchanged( $ids['template_id'] );
	}

	public function test_application_password_authentication_restores_without_a_cookie_nonce(): void {
		$ids = $this->make_template_with_revision();
		$this->enable_application_passwords();
		list( $password ) = WP_Application_Passwords::create_new_application_password( $this->admin_id, array( 'name' => 'Restore route' ) );

		$this->authenticate_with_application_password( get_userdata( $this->admin_id )->user_login, $password );
		self::assertSame( $this->admin_id, get_current_user_id() );

		$response = $this->restore( $ids['template_id'], $ids['revision_id'] );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'Revised content', get_post( $ids['template_id'] )->post_content );
	}

	public function test_an_invalid_application_password_is_unauthenticated(): void {
		$ids = $this->make_template_with_revision();
		$this->enable_application_passwords();
		WP_Application_Passwords::create_new_application_password( $this->admin_id, array( 'name' => 'Restore route' ) );

		$this->authenticate_with_application_password( get_userdata( $this->admin_id )->user_login, 'wrong password value' );
		self::assertSame( 0, get_current_user_id() );

		$this->assert_rejected_without_changes( $ids, Template_Routes::ERROR_UNAUTHENTICATED, 401 );
	}

	/**
	 * Create a published template whose earlier state WordPress saved as a real revision.
	 *
	 * @return array{template_id: int, revision_id: int}
	 */
	private function make_template_with_revision(): array {
		$template_id = self::factory()->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $this->admin_id,
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
	 * Create a normal post with a WordPress-created revision.
	 *
	 * @return array{post_id: int, revision_id: int}
	 */
	private function make_blog_post_with_revision(): array {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_author'  => $this->admin_id,
				'post_content' => 'Blog original',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Blog revised',
			)
		);
		$revision_id = (int) array_key_first( wp_get_post_revisions( $post_id ) );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Blog current',
			)
		);

		return array(
			'post_id'     => $post_id,
			'revision_id' => $revision_id,
		);
	}

	/**
	 * Dispatch a restore request through the REST server.
	 *
	 * @param int $template_id Template ID.
	 * @param int $revision_id Revision ID.
	 */
	private function restore( int $template_id, int $revision_id ): WP_REST_Response {
		return rest_get_server()->dispatch( new WP_REST_Request( 'POST', sprintf( self::ROUTE, $template_id, $revision_id ) ) );
	}

	/**
	 * Assert a restore is refused with a stable error and changes nothing.
	 *
	 * @param array{template_id: int, revision_id: int} $ids    Template and revision IDs.
	 * @param string                                    $code   Expected error code.
	 * @param int                                       $status Expected HTTP status.
	 */
	private function assert_rejected_without_changes( array $ids, string $code, int $status ): void {
		$before = $this->template_state( $ids['template_id'] );

		$this->assert_error( $this->restore( $ids['template_id'], $ids['revision_id'] ), $code, $status );

		self::assertSame( $before, $this->template_state( $ids['template_id'] ) );
	}

	/**
	 * Assert a response is a normalized, non-sensitive route error.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param string           $code     Expected error code.
	 * @param int              $status   Expected HTTP status.
	 */
	private function assert_error( WP_REST_Response $response, string $code, int $status ): void {
		$data = $response->get_data();

		self::assertSame( $status, $response->get_status() );
		self::assertSame( $code, $data['code'] );
		self::assertIsString( $data['message'] );
		self::assertNotSame( '', $data['message'] );
		// Nothing beyond the HTTP status is exposed.
		self::assertSame( array( 'status' => $status ), $data['data'] );
		self::assertArrayNotHasKey( 'success', $data );
	}

	/**
	 * Assert a template still holds its current content and history.
	 *
	 * @param int $template_id Template ID.
	 */
	private function assert_template_unchanged( int $template_id ): void {
		self::assertSame( 'Current content', get_post( $template_id )->post_content );
		self::assertSame( 'Current Title', get_post( $template_id )->post_title );
	}

	/**
	 * Capture a template's content and revision history.
	 *
	 * @param int $template_id Template ID.
	 * @return array<string, mixed>
	 */
	private function template_state( int $template_id ): array {
		$post = get_post( $template_id );

		return array(
			'title'     => $post ? $post->post_title : null,
			'content'   => $post ? $post->post_content : null,
			'modified'  => $post ? $post->post_modified_gmt : null,
			'revisions' => array_keys( wp_get_post_revisions( $template_id, array( 'check_enabled' => false ) ) ),
		);
	}

	/**
	 * Make Application Passwords available for this non-SSL test request.
	 */
	private function enable_application_passwords(): void {
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		add_filter( 'application_password_is_api_request', '__return_true' );
	}

	/**
	 * Resolve the current user the way WordPress does for HTTP Basic credentials.
	 *
	 * @param string $username User login.
	 * @param string $password Application password.
	 */
	private function authenticate_with_application_password( string $username, string $password ): void {
		// A real HTTP request carries a client address, which WordPress records
		// as the application password's last use.
		$_SERVER['REMOTE_ADDR']   = '127.0.0.1';
		$_SERVER['PHP_AUTH_USER'] = $username;
		$_SERVER['PHP_AUTH_PW']   = $password;

		wp_set_current_user( 0 );
		wp_set_current_user( (int) wp_validate_application_password( false ) );
	}
}
