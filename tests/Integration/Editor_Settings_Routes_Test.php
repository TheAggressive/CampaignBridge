<?php
/**
 * Editor settings route integration tests.
 *
 * @package CampaignBridge\Tests\Integration
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\REST\Editor_Settings_Routes;
use CampaignBridge\REST\Rest_Constants;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_Block_Editor_Context;
use WP_REST_Request;

/**
 * Verify editor settings use the actual template security and block context.
 */
class Editor_Settings_Routes_Test extends Test_Case {
	/**
	 * The route should build core settings for the requested template.
	 */
	public function test_settings_use_requested_template_context(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$template_id = $this->create_test_post(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		$context_post_id = 0;
		$capture_context = static function ( array $settings, WP_Block_Editor_Context $context ) use ( &$context_post_id ): array {
			$context_post_id = $context->post ? (int) $context->post->ID : 0;
			return $settings;
		};
		add_filter( 'block_editor_settings_all', $capture_context, 10, 2 );

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $template_id );
		$response = ( new Editor_Settings_Routes() )->handle_request( $request );

		remove_filter( 'block_editor_settings_all', $capture_context, 10 );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $template_id, $context_post_id );
	}

	/**
	 * Core iframe assets must remain available to BlockCanvas.
	 */
	public function test_settings_include_resolved_iframe_assets(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$template_id = $this->create_test_post(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $template_id );
		$response = ( new Editor_Settings_Routes() )->handle_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '__unstableResolvedAssets', $data );
		$this->assertIsArray( $data['__unstableResolvedAssets'] );
		$this->assertArrayHasKey( 'styles', $data['__unstableResolvedAssets'] );
		$this->assertArrayHasKey( 'scripts', $data['__unstableResolvedAssets'] );
		$this->assertIsString( $data['__unstableResolvedAssets']['styles'] );
		$this->assertIsString( $data['__unstableResolvedAssets']['scripts'] );
		$this->assertStringContainsString( 'wp-edit-blocks', $data['__unstableResolvedAssets']['styles'] );
	}

	/**
	 * The compiler registry is the authority for blocks offered by the editor.
	 */
	public function test_settings_include_compiler_supported_block_types(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$template_id = $this->create_test_post(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $template_id );
		$response = ( new Editor_Settings_Routes() )->handle_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame(
			Compiler_Factory::registry()->block_names(),
			$data['allowedBlockTypes']
		);
	}

	/**
	 * Editor colour presets come from the brand kit, not the live theme.
	 */
	public function test_settings_include_the_brand_kit_palette(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$template_id = $this->create_test_post(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		( new Brand_Kit_Repository() )->save(
			Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) )
		);

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $template_id );
		$response = ( new Editor_Settings_Routes() )->handle_request( $request );
		$data     = $response->get_data();

		( new Brand_Kit_Repository() )->clear();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertFalse( $data['__experimentalFeatures']['color']['defaultPalette'] );
		$this->assertFalse( $data['__experimentalFeatures']['color']['gradients'] );
		$this->assertTrue( $data['disableCustomColors'] );
		$this->assertTrue( $data['disableCustomFontSizes'] );
		$this->assertTrue( $data['disableCustomSpacingSizes'] );
		$this->assertFalse( $data['__experimentalFeatures']['color']['custom'] );
		$this->assertFalse( $data['__experimentalFeatures']['typography']['customFontSize'] );
		$this->assertFalse( $data['__experimentalFeatures']['spacing']['customSpacingSize'] );

		$palette = $data['__experimentalFeatures']['color']['palette']['theme'];
		$slugs   = array_map( static fn( array $preset ): string => $preset['slug'], $palette );
		$this->assertSame( Brand_Kit::SLOTS, $slugs );

		$brand = null;
		foreach ( $palette as $preset ) {
			if ( Brand_Kit::SLOT_BRAND === $preset['slug'] ) {
				$brand = $preset['color'];
			}
		}

		$this->assertSame( '#ff5500', $brand );
	}

	/**
	 * Resolved assets cannot be requested for a template the user cannot edit.
	 */
	public function test_settings_reject_user_without_template_access(): void {
		$administrator_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator_id );
		$template_id = $this->create_test_post(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
				'post_author' => $administrator_id,
			)
		);

		$subscriber_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $template_id );
		$response = ( new Editor_Settings_Routes() )->handle_request( $request );

		$this->assertWPError( $response );
		$this->assertSame( 'rest_forbidden', $response->get_error_code() );
		$this->assertSame( 403, $response->get_error_data()['status'] );
	}

	/**
	 * The registered route refuses an administrator after the production limit.
	 */
	public function test_settings_route_rate_limits_each_user_after_the_configured_limit(): void {
		$this->assertSame( 30, Rest_Constants::RATE_LIMIT_REQUESTS );
		$this->assertSame( 60, Rest_Constants::RATE_LIMIT_WINDOW );

		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$template_id = $this->create_test_post(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$counter_key = Rest_Constants::CACHE_KEY_PREFIX_GENERAL . 'editor_settings_' . $user_id;

		for ( $i = 1; $i <= Rest_Constants::RATE_LIMIT_REQUESTS; $i++ ) {
			$this->assertSame( 200, $this->request_settings( $template_id )->get_status(), "Request {$i} is within the limit." );
		}
		$this->assertSame( Rest_Constants::RATE_LIMIT_REQUESTS, get_transient( $counter_key ) );

		$limited = $this->request_settings( $template_id );
		$this->assertSame( 429, $limited->get_status() );
		$this->assertSame( 'rate_limit_exceeded', $limited->get_data()['code'] );
		$this->assertSame( Rest_Constants::RATE_LIMIT_REQUESTS, get_transient( $counter_key ) );

		// The counter belongs to one user: another administrator is not refused.
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 200, $this->request_settings( $template_id )->get_status() );

		// The refused user stays refused until the counter itself is cleared.
		wp_set_current_user( $user_id );
		$this->assertSame( 429, $this->request_settings( $template_id )->get_status() );
		delete_transient( $counter_key );
		$this->assertSame( 200, $this->request_settings( $template_id )->get_status() );
	}

	/**
	 * Dispatch an editor-settings request through the registered REST route.
	 *
	 * @param int $template_id Template ID.
	 * @return \WP_REST_Response
	 */
	private function request_settings( int $template_id ): \WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $template_id );

		return rest_do_request( $request );
	}

	/**
	 * A post from another post type must not be accepted as a template context.
	 */
	public function test_settings_reject_mismatched_post_type(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$post_id = $this->create_test_post();

		$request = new WP_REST_Request( 'GET', '/campaignbridge/v1/editor-settings' );
		$request->set_param( 'post_type', Post_Type_Email_Template::POST_TYPE );
		$request->set_param( 'post_id', $post_id );
		$response = ( new Editor_Settings_Routes() )->handle_request( $request );

		$this->assertWPError( $response );
		$this->assertSame( 'template_not_found', $response->get_error_code() );
	}
}
