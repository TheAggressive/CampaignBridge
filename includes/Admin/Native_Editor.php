<?php
/**
 * Native WordPress editor integration for CampaignBridge templates.
 *
 * @package CampaignBridge\Admin
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds only CampaignBridge-owned behavior to Core's post editor. */
final class Native_Editor {
	/** Register native editor hooks. */
	public static function init(): void {
		\add_filter( 'allowed_block_types_all', array( __CLASS__, 'allowed_block_types' ), 10, 2 );
		\add_filter( 'block_editor_settings_all', array( __CLASS__, 'editor_settings' ), 10, 2 );
		\add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Limit template authoring to the canonical compiler registry.
	 *
	 * @param array<int, string>|bool  $allowed_blocks Existing allowlist.
	 * @param \WP_Block_Editor_Context $context        Current editor context.
	 * @return array<int, string>|bool
	 */
	public static function allowed_block_types( array|bool $allowed_blocks, \WP_Block_Editor_Context $context ): array|bool {
		if ( ! self::is_template_context( $context ) ) {
			return $allowed_blocks;
		}

		return Compiler_Factory::registry()->block_names();
	}

	/**
	 * Replace theme-derived design controls with the resolved email design.
	 *
	 * @param array<string, mixed>     $settings Core editor settings.
	 * @param \WP_Block_Editor_Context $context  Current editor context.
	 * @return array<string, mixed>
	 */
	public static function editor_settings( array $settings, \WP_Block_Editor_Context $context ): array {
		if ( ! self::is_template_context( $context ) ) {
			return $settings;
		}

		$design = Email_Design_Factory::resolve( ( new Brand_Kit_Repository() )->get() );

		return Editor_Design_Settings::apply( $settings, $design );
	}

	/** Load the small native extension and CampaignBridge-owned preview styles. */
	public static function enqueue_assets(): void {
		$screen = \get_current_screen();
		if ( ! $screen || Post_Type_Email_Template::POST_TYPE !== $screen->post_type ) {
			return;
		}

		Asset_Manager::enqueue_asset_script(
			'campaignbridge-native-editor',
			'dist/scripts/editor/native-editor.asset.php'
		);
		Asset_Manager::enqueue_asset_style(
			'campaignbridge-native-editor-styles',
			'dist/styles/editor/preview.asset.php'
		);
		\wp_set_script_translations( 'campaignbridge-native-editor', 'campaignbridge' );
	}

	/**
	 * Whether a block editor context belongs to the email template post type.
	 *
	 * @param \WP_Block_Editor_Context $context Current editor context.
	 */
	private static function is_template_context( \WP_Block_Editor_Context $context ): bool {
		return $context->post instanceof \WP_Post
			&& Post_Type_Email_Template::POST_TYPE === $context->post->post_type;
	}
}
