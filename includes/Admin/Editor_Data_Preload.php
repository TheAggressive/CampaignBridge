<?php
/**
 * REST preloading for the standalone email editor.
 *
 * @package CampaignBridge\Admin
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use WP_Block_Editor_Context;
use WP_Post;

/**
 * Prime the same api-fetch middleware used by WordPress's core editors.
 */
final class Editor_Data_Preload {
	/**
	 * Add authorized template data to core's REST preloading middleware.
	 */
	public static function preload_for_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor navigation parameter.
		$template_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		if ( ! $template_id ) {
			return;
		}

		$template = get_post( $template_id );
		if (
			! $template instanceof WP_Post ||
			Post_Type_Email_Template::POST_TYPE !== $template->post_type ||
			! current_user_can( 'edit_post', $template_id )
		) {
			return;
		}

		$template_route = rest_get_route_for_post( $template );
		if ( ! is_string( $template_route ) || '' === $template_route ) {
			return;
		}

		$preload_paths = array(
			'/wp/v2/types?context=view',
			add_query_arg( 'context', 'edit', $template_route ),
			'/wp/v2/cb_templates?per_page=100&status=draft,publish&context=edit&_fields=id,title,status,date',
			sprintf(
				'/campaignbridge/v1/editor-settings?post_type=%s&post_id=%d',
				Post_Type_Email_Template::POST_TYPE,
				$template_id
			),
			'/campaignbridge/v1/post-types',
		);
		block_editor_rest_api_preload(
			$preload_paths,
			new WP_Block_Editor_Context( array( 'post' => $template ) )
		);
	}
}
