<?php
/**
 * Template duplication through the core REST API.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes;
use CampaignBridge\REST\Template_Create_Guard;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Proves the duplication policy is explicit and complete, and that the
 * editor's duplicate payload creates an independent draft through the core
 * cb_templates REST controller.
 */
final class Template_Duplication_Test extends Test_Case {
	/**
	 * Reusable email definition fields and the source value for each.
	 */
	private const COPIED = array(
		'campaignbridge_subject'             => 'Launch subject',
		'campaignbridge_preheader'           => 'Launch preheader',
		'campaignbridge_sender_name'         => 'Launch Desk',
		'campaignbridge_sender_email'        => 'launch@example.test',
		'campaignbridge_view_online_enabled' => true,
		'campaignbridge_view_online_url'     => 'https://example.test/launch',
		'campaignbridge_unsubscribe_url'     => 'https://example.test/unsubscribe',
		'campaignbridge_address_html'        => '<p>1 Launch Street</p>',
		'campaignbridge_utm_enabled'         => true,
		'campaignbridge_utm_template'        => 'utm_source=launch',
		'campaignbridge_footer_enabled'      => true,
		'campaignbridge_footer_pattern'      => 'launch-footer',
	);

	/**
	 * Targeting and library fields a duplicate must not inherit.
	 */
	private const EXCLUDED = array(
		'campaignbridge_template_category' => 'newsletter',
		'campaignbridge_audience_tags'     => 'launch-list',
	);

	private const CONTENT = '<!-- wp:campaignbridge/container -->' .
		'<!-- wp:campaignbridge/section -->' .
		'<!-- wp:core/paragraph {"content":"Launch content"} /-->' .
		'<!-- /wp:campaignbridge/section -->' .
		'<!-- /wp:campaignbridge/container -->';

	/**
	 * Register the template model and REST lifecycle as plugin init does.
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

	public function test_every_registered_template_meta_key_has_an_explicit_duplication_decision(): void {
		self::assertSame( array(), self::unclassified_registered_keys() );

		$duplicable = Post_Type_Email_Template::get_duplicable_meta_keys();
		$expected   = array_keys( self::COPIED );
		sort( $duplicable );
		sort( $expected );
		self::assertSame( $expected, $duplicable );

		foreach ( array_keys( self::EXCLUDED ) as $key ) {
			self::assertFalse( Post_Type_Email_Template::get_meta_field_config( $key )['duplicate'], $key );
		}
	}

	public function test_a_new_unclassified_meta_key_fails_closed_and_fails_the_audit(): void {
		$key = 'campaignbridge_provider_campaign_id';
		register_post_meta(
			Post_Type_Email_Template::POST_TYPE,
			$key,
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		try {
			self::assertSame( array( $key ), self::unclassified_registered_keys() );
			self::assertNotContains( $key, Post_Type_Email_Template::get_duplicable_meta_keys() );
		} finally {
			unregister_post_meta( Post_Type_Email_Template::POST_TYPE, $key );
		}
	}

	/**
	 * The source's recovery autosave uses the real core autosaves endpoint,
	 * which defines DOING_AUTOSAVE for the rest of the PHP process and would
	 * disable revisions in unrelated tests, so this test runs in isolation.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_duplicate_payload_creates_an_independent_draft(): void {
		$source_id = $this->create_published_source_with_history_and_autosave();
		$source    = $this->get_template( $source_id );
		$revisions = wp_get_post_revisions( $source_id );
		self::assertNotEmpty( $revisions );
		$source_autosave = wp_get_post_autosave( $source_id );
		self::assertNotFalse( $source_autosave );

		$response = $this->dispatch( 'POST', '/wp/v2/cb_templates', self::duplicate_payload( $source ) );
		self::assertSame( 201, $response->get_status() );
		$copy_id = (int) $response->get_data()['id'];
		$copy    = $this->get_template( $copy_id );

		self::assertNotSame( $source_id, $copy_id );
		self::assertSame( 'draft', $copy['status'] );
		self::assertSame( 'Launch template (Copy)', $copy['title']['raw'] );
		self::assertSame( $source['content']['raw'], $copy['content']['raw'] );
		foreach ( self::COPIED as $key => $value ) {
			self::assertSame( $value, $copy['meta'][ $key ], $key );
		}
		foreach ( array_keys( self::EXCLUDED ) as $key ) {
			self::assertSame( '', $copy['meta'][ $key ], $key );
			self::assertFalse( metadata_exists( 'post', $copy_id, $key ), $key );
		}

		// WordPress assigns the copy's author and dates; nothing is inherited.
		$copy_post = get_post( $copy_id );
		self::assertSame( get_current_user_id(), (int) $copy_post->post_author );
		self::assertSame( '0000-00-00 00:00:00', $copy_post->post_date_gmt );

		// History stays with the source.
		self::assertSame( array(), wp_get_post_revisions( $copy_id ) );
		self::assertFalse( wp_get_post_autosave( $copy_id ) );
		self::assertSame( array_keys( $revisions ), array_keys( wp_get_post_revisions( $source_id ) ) );
		self::assertSame( $source_id, (int) wp_get_post_autosave( $source_id )->post_parent );

		// Creating the copy changed nothing on the source.
		$this->assert_same_template( $source, $this->get_template( $source_id ) );

		// Later edits to either template stay on that template.
		$this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$copy_id}",
			array(
				'content' => 'Copy content',
				'meta'    => array( 'campaignbridge_subject' => 'Copy subject' ),
			)
		);
		$this->assert_same_template( $source, $this->get_template( $source_id ) );

		$this->dispatch( 'PUT', "/wp/v2/cb_templates/{$source_id}", array( 'content' => 'Source content' ) );
		$copy = $this->get_template( $copy_id );
		self::assertSame( 'Copy content', $copy['content']['raw'] );
		self::assertSame( 'Copy subject', $copy['meta']['campaignbridge_subject'] );
	}

	public function test_the_create_guard_is_wired_to_the_rest_api(): void {
		self::assertNotFalse( has_action( 'rest_api_init', array( Template_Create_Guard::class, 'register' ) ) );
		self::assertNotFalse( has_action( 'rest_insert_cb_templates', array( Template_Create_Guard::class, 'remember_created' ) ) );
		self::assertNotFalse( has_filter( 'rest_request_after_callbacks', array( Template_Create_Guard::class, 'discard_failed_create' ) ) );
	}

	public function test_a_create_whose_meta_is_rejected_leaves_no_template(): void {
		$title    = 'Rejected meta copy';
		$response = $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'   => $title,
				'status'  => 'draft',
				'content' => self::CONTENT,
				'meta'    => array( 'campaignbridge_view_online_enabled' => 'not-a-boolean' ),
			)
		);

		self::assertSame( 400, $response->get_status() );
		self::assertSame( array(), self::templates_titled( $title ) );
	}

	public function test_a_create_whose_meta_cannot_be_stored_leaves_no_template(): void {
		$title       = 'Unstored meta copy';
		$fail_writes = static fn ( $check, $object_id, $meta_key ) => 'campaignbridge_subject' === $meta_key ? false : $check;
		add_filter( 'add_post_metadata', $fail_writes, 10, 3 );
		add_filter( 'update_post_metadata', $fail_writes, 10, 3 );

		$response = $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'   => $title,
				'status'  => 'draft',
				'content' => self::CONTENT,
				'meta'    => array( 'campaignbridge_subject' => 'Launch subject' ),
			)
		);

		remove_filter( 'add_post_metadata', $fail_writes, 10 );
		remove_filter( 'update_post_metadata', $fail_writes, 10 );

		self::assertSame( 500, $response->get_status() );
		self::assertSame( 'rest_meta_database_error', $response->as_error()->get_error_code() );
		self::assertSame( array(), self::templates_titled( $title ) );
	}

	public function test_a_failed_update_never_deletes_the_existing_template(): void {
		$source_id = $this->create_published_source();
		$source    = $this->get_template( $source_id );

		$response = $this->dispatch(
			'PUT',
			"/wp/v2/cb_templates/{$source_id}",
			array( 'meta' => array( 'campaignbridge_view_online_enabled' => 'not-a-boolean' ) )
		);

		self::assertSame( 400, $response->get_status() );
		$this->assert_same_template( $source, $this->get_template( $source_id ) );
	}

	/**
	 * Registered CampaignBridge template meta keys without an explicit
	 * boolean duplication decision.
	 *
	 * @return array<int, string>
	 */
	private static function unclassified_registered_keys(): array {
		$config     = Post_Type_Email_Template::get_meta_field_config();
		$registered = array_filter(
			array_keys( get_registered_meta_keys( 'post', Post_Type_Email_Template::POST_TYPE ) ),
			static fn ( string $key ): bool => str_starts_with( $key, 'campaignbridge_' )
		);

		return array_values(
			array_filter(
				$registered,
				static fn ( string $key ): bool => ! is_bool( $config[ $key ]['duplicate'] ?? null )
			)
		);
	}

	/**
	 * Build the create payload the editor sends for a saved template.
	 *
	 * @param array<string, mixed> $source Template in the edit context.
	 * @return array<string, mixed>
	 */
	private static function duplicate_payload( array $source ): array {
		$meta = array_intersect_key(
			$source['meta'],
			array_flip( Post_Type_Email_Template::get_duplicable_meta_keys() )
		);

		return array(
			'status'  => 'draft',
			'title'   => $source['title']['raw'] . ' (Copy)',
			'content' => $source['content']['raw'],
			'meta'    => $meta,
		);
	}

	/**
	 * Create a published template with a revision and a recovery autosave.
	 *
	 * Calls the core autosaves endpoint; only a test running in a separate
	 * process may use it.
	 */
	private function create_published_source_with_history_and_autosave(): int {
		$source_id = $this->create_published_source();
		$this->dispatch( 'POST', "/wp/v2/cb_templates/{$source_id}/autosaves", array( 'content' => 'Recovery content' ) );

		return $source_id;
	}

	/**
	 * Create a published template whose content update made a revision.
	 */
	private function create_published_source(): int {
		$source_id = (int) $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'   => 'Launch template',
				'status'  => 'publish',
				'content' => 'Draft content',
				'meta'    => array_merge( self::COPIED, self::EXCLUDED ),
			)
		)->get_data()['id'];
		$this->dispatch( 'PUT', "/wp/v2/cb_templates/{$source_id}", array( 'content' => self::CONTENT ) );

		return $source_id;
	}

	/**
	 * Assert that a template's status, title, content, and meta are unchanged.
	 *
	 * @param array<string, mixed> $expected Earlier edit-context template.
	 * @param array<string, mixed> $actual   Current edit-context template.
	 */
	private function assert_same_template( array $expected, array $actual ): void {
		self::assertSame( $expected['id'], $actual['id'] );
		self::assertSame( $expected['status'], $actual['status'] );
		self::assertSame( $expected['title']['raw'], $actual['title']['raw'] );
		self::assertSame( $expected['content']['raw'], $actual['content']['raw'] );
		self::assertSame( $expected['meta'], $actual['meta'] );
	}

	/**
	 * Read a template in the edit context.
	 *
	 * @return array<string, mixed>
	 */
	private function get_template( int $template_id ): array {
		$request = new WP_REST_Request( 'GET', "/wp/v2/cb_templates/{$template_id}" );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status() );

		return $response->get_data();
	}

	/**
	 * IDs of every template, in any status, with a title.
	 *
	 * @return array<int, int>
	 */
	private static function templates_titled( string $title ): array {
		return get_posts(
			array(
				'post_type'      => Post_Type_Email_Template::POST_TYPE,
				'post_status'    => 'any',
				'title'          => $title,
				'fields'         => 'ids',
				'posts_per_page' => 10,
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
