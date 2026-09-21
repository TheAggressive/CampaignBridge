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
use CampaignBridge\Services\Email\Design\Email_Design_Factory;
use CampaignBridge\Services\Email\Email_Block_Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds only CampaignBridge-owned behavior to Core's post editor. */
final class Native_Editor {
	/** Register native editor hooks. */
	public static function init(): void {
		\add_filter( 'allowed_block_types_all', array( __CLASS__, 'allowed_block_types' ), 10, 2 );
		\add_filter( 'block_editor_settings_all', array( __CLASS__, 'editor_settings' ), 10, 2 );
		\add_filter( 'block_bindings_supported_attributes_core/image', array( __CLASS__, 'image_binding_attributes' ) );
		\add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Permit the Brand Logo variation to bind Core Image's saved link target.
	 *
	 * Core already permits url and alt. CampaignBridge validates href against
	 * its own bounded contract before compiling it to canonical linkUrl.
	 *
	 * @param array<int, string> $attributes Core supported attributes.
	 * @return array<int, string>
	 */
	public static function image_binding_attributes( array $attributes ): array {
		$attributes[] = 'href';

		return array_values( array_unique( $attributes ) );
	}

	/**
	 * Limit template authoring to the supported email block contract.
	 *
	 * The contract lists the WordPress Core blocks CampaignBridge normalizes
	 * and its own email blocks; every other Core or third-party block stays
	 * out of the template inserter.
	 *
	 * @param array<int, string>|bool  $allowed_blocks Existing allowlist.
	 * @param \WP_Block_Editor_Context $context        Current editor context.
	 * @return array<int, string>|bool
	 */
	public static function allowed_block_types( array|bool $allowed_blocks, \WP_Block_Editor_Context $context ): array|bool {
		if ( ! self::is_template_context( $context ) ) {
			return $allowed_blocks;
		}

		return Email_Block_Contract::names();
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

		$kit                                        = ( new Brand_Kit_Repository() )->get();
		$design                                     = Email_Design_Factory::resolve( $kit );
		$settings                                   = Editor_Design_Settings::apply( $settings, $design );
		$settings['campaignbridgePostTypeArchives'] = self::post_type_archives();
		$settings['campaignbridgeBrandLogo']        = $kit->logo();

		return $settings;
	}

	/**
	 * Publish the post-type archive permalinks the post binding source previews.
	 *
	 * A post type archive link has no REST representation, so the editor reads
	 * it from settings rather than inventing a store. The compiler never uses
	 * this value: it resolves `postTypeArchiveUrl` from the immutable snapshot.
	 *
	 * @return array<string, string> Archive permalinks keyed by post type slug.
	 */
	private static function post_type_archives(): array {
		$archives = array();
		foreach ( \get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			$url = \get_post_type_archive_link( (string) $post_type );
			if ( is_string( $url ) ) {
				$archives[ (string) $post_type ] = \esc_url_raw( $url );
			}
		}

		return $archives;
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
