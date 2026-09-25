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
use CampaignBridge\Services\Email\Email_Block_Contract;
use CampaignBridge\Tests\Helpers\Test_Case;

/** Verify the native editor remains a scoped extension of Core. */
final class Native_Editor_Test extends Test_Case {
	/** Native hooks are registered by the plugin composition root. */
	public function test_native_editor_hooks_are_registered(): void {
		self::assertNotFalse( has_filter( 'allowed_block_types_all', array( Native_Editor::class, 'allowed_block_types' ) ) );
		self::assertNotFalse( has_filter( 'block_editor_settings_all', array( Native_Editor::class, 'editor_settings' ) ) );
		self::assertNotFalse( has_filter( 'block_bindings_supported_attributes_core/image', array( Native_Editor::class, 'image_binding_attributes' ) ) );
		self::assertNotFalse( has_action( 'enqueue_block_editor_assets', array( Native_Editor::class, 'enqueue_assets' ) ) );
	}

	/** The Brand Logo variation may bind Core Image's saved anchor href. */
	public function test_core_image_href_is_bindable_once(): void {
		self::assertSame( array( 'url', 'alt', 'href' ), Native_Editor::image_binding_attributes( array( 'url', 'alt', 'href' ) ) );
	}

	/** The email block contract is the only native-editor block allowlist. */
	public function test_template_allowed_blocks_come_from_the_email_block_contract(): void {
		$template = $this->factory->post->create_and_get(
			array(
				'post_type'   => Post_Type_Email_Template::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$context  = new \WP_Block_Editor_Context( array( 'post' => $template ) );

		$allowed = Native_Editor::allowed_block_types( true, $context );
		self::assertSame( Email_Block_Contract::names(), $allowed );
		self::assertIsArray( $allowed );

		$compiler = Compiler_Factory::registry()->block_names();
		sort( $compiler, SORT_STRING );
		$editor = $allowed;
		sort( $editor, SORT_STRING );
		self::assertSame( $compiler, $editor, 'Every insertable block must have an email renderer.' );
		self::assertContains( 'campaignbridge/navigation', $allowed );
		self::assertContains( 'campaignbridge/video', $allowed );

		foreach ( array( 'core/paragraph', 'core/heading', 'core/image', 'core/buttons', 'core/button', 'core/list', 'core/list-item', 'core/separator', 'core/spacer' ) as $core ) {
			self::assertContains( $core, $allowed );
			self::assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $core ), $core . ' must be the real WordPress Core block.' );
		}
		foreach ( array( 'core/group', 'core/cover', 'core/gallery', 'core/embed', 'core/video', 'core/html', 'core/shortcode', 'core/query', 'core/navigation', 'core/columns', 'core/column', 'core/quote', 'core/table' ) as $unsupported ) {
			self::assertNotContains( $unsupported, $allowed );
		}
		foreach ( array( 'campaignbridge/text', 'campaignbridge/heading', 'campaignbridge/image', 'campaignbridge/button', 'campaignbridge/divider', 'campaignbridge/spacer', 'campaignbridge/list', 'campaignbridge/list-item' ) as $obsolete ) {
			self::assertNotContains( $obsolete, $allowed );
			self::assertFalse( \WP_Block_Type_Registry::get_instance()->is_registered( $obsolete ), $obsolete . ' must not be registered.' );
		}
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
		self::assertArrayHasKey( 'campaignbridgeBrandLogo', $settings );
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
		$before = wp_scripts()->get_data( 'campaignbridge-native-editor', 'before' );
		self::assertIsArray( $before );
		self::assertStringContainsString( 'globalThis.campaignbridgeEditorDesign=', implode( '', $before ) );
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
