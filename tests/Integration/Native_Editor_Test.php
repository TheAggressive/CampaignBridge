<?php
/**
 * Native template editor integration tests.
 *
 * @package CampaignBridge\Tests\Integration
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Native_Editor;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Tests\Helpers\Test_Case;

/** Verify the native editor remains a scoped extension of Core. */
final class Native_Editor_Test extends Test_Case {
	/** Native hooks are registered by the plugin composition root. */
	public function test_native_editor_hooks_are_registered(): void {
		self::assertNotFalse( has_filter( 'allowed_block_types_all', array( Native_Editor::class, 'allowed_block_types' ) ) );
		self::assertNotFalse( has_filter( 'block_editor_settings_all', array( Native_Editor::class, 'editor_settings' ) ) );
		self::assertNotFalse( has_action( 'enqueue_block_editor_assets', array( Native_Editor::class, 'enqueue_assets' ) ) );
	}

	/** The compiler registry is the only native-editor block allowlist. */
	public function test_template_allowed_blocks_come_from_the_compiler_registry(): void {
		$template = $this->factory->post->create_and_get(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$context  = new \WP_Block_Editor_Context( array( 'post' => $template ) );

		self::assertSame(
			Compiler_Factory::registry()->block_names(),
			Native_Editor::allowed_block_types( true, $context )
		);
	}

	/** Other post types retain their existing block policy and design. */
	public function test_non_template_editor_context_is_unchanged(): void {
		$post     = $this->factory->post->create_and_get();
		$context  = new \WP_Block_Editor_Context( array( 'post' => $post ) );
		$allowed  = array( 'core/paragraph' );
		$settings = array( 'colors' => array( array( 'slug' => 'theme-color' ) ) );

		self::assertSame( $allowed, Native_Editor::allowed_block_types( $allowed, $context ) );
		self::assertSame( $settings, Native_Editor::editor_settings( $settings, $context ) );
	}

	/** Native controls receive the resolved email design adapter. */
	public function test_template_editor_settings_use_the_email_design(): void {
		$template = $this->factory->post->create_and_get(
			array( 'post_type' => Post_Type_Email_Template::POST_TYPE )
		);
		$context  = new \WP_Block_Editor_Context( array( 'post' => $template ) );
		$settings = Native_Editor::editor_settings( array(), $context );

		self::assertNotEmpty( $settings['colors'] );
		self::assertSame( array(), $settings['gradients'] );
		self::assertTrue( $settings['disableCustomGradients'] );
		self::assertNotEmpty( $settings['styles'] );
	}

	/** The CPT provides a root container only for genuinely new documents. */
	public function test_template_post_type_uses_native_management_and_default_root(): void {
		$post_type = get_post_type_object( Post_Type_Email_Template::POST_TYPE );

		self::assertInstanceOf( \WP_Post_Type::class, $post_type );
		self::assertSame( 'campaignbridge', $post_type->show_in_menu );
		self::assertSame( 'campaignbridge/container', $post_type->template[0][0] );
		self::assertSame(
			array(
				'move'   => false,
				'remove' => true,
			),
			$post_type->template[0][1]['lock']
		);
	}

	/** Native extension assets never leak onto another post type's editor. */
	public function test_native_assets_are_scoped_to_template_editor(): void {
		wp_dequeue_script( 'campaignbridge-native-editor' );
		wp_dequeue_style( 'campaignbridge-native-editor-styles' );

		set_current_screen( 'post' );
		$screen            = get_current_screen();
		$screen->post_type = 'post';
		Native_Editor::enqueue_assets();
		self::assertFalse( wp_script_is( 'campaignbridge-native-editor', 'enqueued' ) );

		$screen->post_type = Post_Type_Email_Template::POST_TYPE;
		Native_Editor::enqueue_assets();
		self::assertTrue( wp_script_is( 'campaignbridge-native-editor', 'enqueued' ) );
		self::assertTrue( wp_style_is( 'campaignbridge-native-editor-styles', 'enqueued' ) );
		self::assertStringContainsString(
			'dist/styles/editor/preview.css',
			wp_styles()->registered['campaignbridge-native-editor-styles']->src
		);
	}

	/** The native editor does not expose the retired settings bootstrap route. */
	public function test_legacy_editor_settings_route_is_not_registered(): void {
		do_action( 'rest_api_init' );
		Routes::register();

		self::assertArrayNotHasKey(
			'/campaignbridge/v1/editor-settings',
			rest_get_server()->get_routes()
		);
	}
}
