<?php
/**
 * Native WordPress revision metadata for email templates.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Proves template metadata follows the real WordPress revision lifecycle:
 * core REST saves (the path core-data uses) create revisions, and restoring
 * a revision restores exactly the registered revisionable metadata.
 */
final class Template_Revision_Meta_Test extends Test_Case {
	private const RESTORE_ROUTE = '/campaignbridge/v1/templates/%d/revisions/%d/restore';

	/**
	 * Fields that define the reusable email, with a value for each state.
	 */
	private const REVISIONED = array(
		'campaignbridge_subject'             => array( 'Spring subject', 'Summer subject' ),
		'campaignbridge_preheader'           => array( 'Spring preheader', 'Summer preheader' ),
		'campaignbridge_sender_name'         => array( 'Spring Desk', 'Summer Desk' ),
		'campaignbridge_sender_email'        => array( 'spring@example.test', 'summer@example.test' ),
		'campaignbridge_view_online_enabled' => array( true, false ),
		'campaignbridge_view_online_url'     => array( 'https://example.test/spring', 'https://example.test/summer' ),
		'campaignbridge_unsubscribe_url'     => array( 'https://example.test/unsubscribe/spring', 'https://example.test/unsubscribe/summer' ),
		'campaignbridge_address_html'        => array( '<p>1 Spring Street</p>', '<p>2 Summer Avenue</p>' ),
		'campaignbridge_utm_enabled'         => array( true, false ),
		'campaignbridge_utm_template'        => array( 'utm_source=spring', 'utm_source=summer' ),
		'campaignbridge_footer_enabled'      => array( true, false ),
		'campaignbridge_footer_pattern'      => array( 'spring-footer', 'summer-footer' ),
	);

	/**
	 * Fields that organize or target a template, with a value for each state.
	 */
	private const NOT_REVISIONED = array(
		'campaignbridge_template_category' => array( 'newsletter', 'promotional' ),
		'campaignbridge_audience_tags'     => array( 'spring-list', 'summer-list' ),
	);

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

	public function test_registered_revision_meta_matches_the_template_meta_audit(): void {
		self::assertTrue( post_type_supports( 'cb_templates', 'revisions' ) );

		$registered = array_filter(
			array_keys( get_registered_meta_keys( 'post', 'cb_templates' ) ),
			static fn ( string $key ): bool => str_starts_with( $key, 'campaignbridge_' )
		);
		$classified = array_merge( array_keys( self::REVISIONED ), array_keys( self::NOT_REVISIONED ) );
		sort( $registered );
		sort( $classified );

		// Every CampaignBridge template field must be deliberately classified.
		self::assertSame( $classified, $registered );

		$revisioned_keys = wp_post_revision_meta_keys( 'cb_templates' );
		foreach ( array_keys( self::REVISIONED ) as $key ) {
			self::assertContains( $key, $revisioned_keys, $key );
		}
		foreach ( array_keys( self::NOT_REVISIONED ) as $key ) {
			self::assertNotContains( $key, $revisioned_keys, $key );
		}
	}

	public function test_a_normal_save_creates_revisions_holding_historical_content_and_meta(): void {
		$template_id = $this->create_template();

		$this->save_state( $template_id, 0 );
		$this->save_state( $template_id, 1 );

		$revision_a = $this->revision_with_content( $template_id, $this->content( 0 ) );
		$revision_b = $this->revision_with_content( $template_id, $this->content( 1 ) );

		foreach ( array( $revision_a, $revision_b ) as $revision ) {
			self::assertSame( 'revision', $revision->post_type );
			self::assertSame( $template_id, (int) $revision->post_parent );
			self::assertFalse( wp_is_post_autosave( $revision ) );
		}

		$this->assert_revisioned_meta_state( $revision_a->ID, 0 );
		$this->assert_revisioned_meta_state( $revision_b->ID, 1 );

		// Organizational and targeting fields never enter revision history.
		foreach ( array_keys( self::NOT_REVISIONED ) as $key ) {
			self::assertFalse( metadata_exists( 'post', $revision_a->ID, $key ), $key );
			self::assertFalse( metadata_exists( 'post', $revision_b->ID, $key ), $key );
		}
	}

	public function test_only_a_revisionable_meta_change_creates_a_revision(): void {
		$template_id = $this->create_template();
		$this->save_state( $template_id, 0 );
		$count = count( wp_get_post_revisions( $template_id ) );

		$this->update_template( $template_id, array( 'meta' => array( 'campaignbridge_template_category' => 'welcome' ) ) );
		$this->update_template( $template_id, array( 'meta' => array( 'campaignbridge_audience_tags' => 'vip' ) ) );
		self::assertCount( $count, wp_get_post_revisions( $template_id ) );

		$this->update_template( $template_id, array( 'meta' => array( 'campaignbridge_subject' => 'Only the subject changed' ) ) );

		$revisions = wp_get_post_revisions( $template_id );
		self::assertCount( $count + 1, $revisions );
		self::assertSame(
			'Only the subject changed',
			get_post_meta( (int) array_key_first( $revisions ), 'campaignbridge_subject', true )
		);
	}

	public function test_restore_restores_content_and_revisioned_meta_but_not_organizational_meta(): void {
		$template_id = $this->create_template();
		$this->save_state( $template_id, 0 );
		$this->save_state( $template_id, 1 );
		$revision_a = $this->revision_with_content( $template_id, $this->content( 0 ) );

		$response = $this->restore( $template_id, $revision_a->ID );
		self::assertSame( 200, $response->get_status() );

		$template = $this->get_template( $template_id );
		self::assertSame( $this->content( 0 ), $template['content']['raw'] );
		foreach ( self::REVISIONED as $key => $values ) {
			self::assertSame( $values[0], $template['meta'][ $key ], $key );
			self::assertCount( 1, get_post_meta( $template_id, $key ), "{$key} must stay single" );
		}
		foreach ( self::NOT_REVISIONED as $key => $values ) {
			self::assertSame( $values[1], $template['meta'][ $key ], $key );
		}
	}

	public function test_restore_removes_revisioned_meta_the_revision_did_not_have(): void {
		$template_id = $this->create_template();
		$this->update_template(
			$template_id,
			array(
				'content' => 'Before a preheader existed',
				'meta'    => array( 'campaignbridge_subject' => 'Original subject' ),
			)
		);
		$this->update_template(
			$template_id,
			array(
				'content' => 'After adding a preheader',
				'meta'    => array( 'campaignbridge_preheader' => 'Added later' ),
			)
		);
		$original = $this->revision_with_content( $template_id, 'Before a preheader existed' );
		self::assertFalse( metadata_exists( 'post', $original->ID, 'campaignbridge_preheader' ) );

		self::assertSame( 200, $this->restore( $template_id, $original->ID )->get_status() );

		self::assertFalse( metadata_exists( 'post', $template_id, 'campaignbridge_preheader' ) );
		self::assertSame( 'Original subject', get_post_meta( $template_id, 'campaignbridge_subject', true ) );
	}

	public function test_the_newest_revision_after_restore_matches_the_restored_template(): void {
		$template_id = $this->create_template();
		$this->save_state( $template_id, 0 );
		$this->save_state( $template_id, 1 );
		$revision_a = $this->revision_with_content( $template_id, $this->content( 0 ) );

		self::assertSame( 200, $this->restore( $template_id, $revision_a->ID )->get_status() );

		// History must describe the template as it now is, content and meta.
		$newest = get_post( (int) array_key_first( wp_get_post_revisions( $template_id ) ) );
		self::assertInstanceOf( WP_Post::class, $newest );
		self::assertSame( $this->content( 0 ), $newest->post_content );
		$this->assert_revisioned_meta_state( $newest->ID, 0 );
	}

	/**
	 * Create an empty published template through the core REST endpoint.
	 */
	private function create_template(): int {
		$response = $this->dispatch(
			'POST',
			'/wp/v2/cb_templates',
			array(
				'title'  => 'Revision meta template',
				'status' => 'publish',
			)
		);
		self::assertSame( 201, $response->get_status() );

		return (int) $response->get_data()['id'];
	}

	/**
	 * Save one full content and metadata state through the core REST endpoint.
	 *
	 * @param int $template_id Template ID.
	 * @param int $state       State index.
	 */
	private function save_state( int $template_id, int $state ): void {
		$meta = array();
		foreach ( array_merge( self::REVISIONED, self::NOT_REVISIONED ) as $key => $values ) {
			$meta[ $key ] = $values[ $state ];
		}

		$template = $this->update_template(
			$template_id,
			array(
				'content' => $this->content( $state ),
				'meta'    => $meta,
			)
		);

		// The REST layer accepted and stored every intended value.
		foreach ( $meta as $key => $value ) {
			self::assertSame( $value, $template['meta'][ $key ], $key );
		}
	}

	/**
	 * Update a template through the core REST endpoint.
	 *
	 * @param int                  $template_id Template ID.
	 * @param array<string, mixed> $body        Request body.
	 * @return array<string, mixed> Response data.
	 */
	private function update_template( int $template_id, array $body ): array {
		$response = $this->dispatch( 'PUT', "/wp/v2/cb_templates/{$template_id}", $body );
		self::assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		return $response->get_data();
	}

	/**
	 * Read a template in edit context through the core REST endpoint.
	 *
	 * @param int $template_id Template ID.
	 * @return array<string, mixed> Response data.
	 */
	private function get_template( int $template_id ): array {
		$request = new WP_REST_Request( 'GET', "/wp/v2/cb_templates/{$template_id}" );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		self::assertSame( 200, $response->get_status() );

		return $response->get_data();
	}

	/**
	 * Restore a revision through the CampaignBridge restore route.
	 *
	 * @param int $template_id Template ID.
	 * @param int $revision_id Revision ID.
	 */
	private function restore( int $template_id, int $revision_id ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', sprintf( self::RESTORE_ROUTE, $template_id, $revision_id ) );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		return rest_get_server()->dispatch( $request );
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

	/**
	 * Find the WordPress-created revision holding the given content.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $content     Post content.
	 */
	private function revision_with_content( int $template_id, string $content ): WP_Post {
		foreach ( wp_get_post_revisions( $template_id ) as $revision ) {
			if ( $content === $revision->post_content && ! wp_is_post_autosave( $revision ) ) {
				return $revision;
			}
		}

		self::fail( "No revision holds content: {$content}" );
	}

	/**
	 * Assert a post holds exactly one stored value per revisioned field for a state.
	 *
	 * @param int $post_id Post or revision ID.
	 * @param int $state   State index.
	 */
	private function assert_revisioned_meta_state( int $post_id, int $state ): void {
		foreach ( self::REVISIONED as $key => $values ) {
			$stored = get_post_meta( $post_id, $key );
			self::assertCount( 1, $stored, $key );
			// Booleans are stored as "1" or "" by the metadata API.
			$expected = is_bool( $values[ $state ] ) ? ( $values[ $state ] ? '1' : '' ) : $values[ $state ];
			self::assertSame( $expected, $stored[0], $key );
		}
	}

	/**
	 * Email block content for a state.
	 *
	 * @param int $state State index.
	 */
	private function content( int $state ): string {
		$text = 0 === $state ? 'Spring issue' : 'Summer issue';

		return '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/text {"content":"' . $text . '"} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';
	}
}
