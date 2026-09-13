<?php
/**
 * Template revision restore route.
 *
 * @package CampaignBridge
 *
 * phpcs:disable WordPress.Files.FileName
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restore a saved revision of an email template.
 *
 * WordPress core exposes revision *reading* through the REST API but does not
 * ship a restore endpoint. This route fills that gap for the `cb_templates`
 * post type, enforcing the CampaignBridge template capability and restoring
 * both post content and revisionable meta in a single operation.
 */
final class Template_Routes extends Abstract_Rest_Controller {
	private const RESTORE_PATH = '/templates/(?P<id>\d+)/revisions/(?P<revision_id>\d+)/restore';

	/**
	 * Check whether the current user can edit templates.
	 *
	 * @return bool
	 */
	public static function can_edit_templates(): bool {
		return \current_user_can( Capabilities::EDIT_TEMPLATES );
	}

	/**
	 * Register the revision restore endpoint.
	 */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::RESTORE_PATH,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restore_revision' ),
				'permission_callback' => array( __CLASS__, 'can_edit_templates' ),
				'args'                => array(
					'id'          => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'revision_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}

	/**
	 * Restore the specified revision onto the parent template.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_revision( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Verify the REST API nonce for CSRF protection.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return self::create_error( 'invalid_nonce', __( 'Security validation failed.', 'campaignbridge' ), Rest_Constants::HTTP_FORBIDDEN );
		}

		$template_id = (int) $request->get_param( 'id' );
		$revision_id = (int) $request->get_param( 'revision_id' );

		$template = \get_post( $template_id );
		if ( ! $template instanceof \WP_Post || 'cb_templates' !== $template->post_type ) {
			return self::create_error( 'template_not_found', __( 'Email template not found.', 'campaignbridge' ), Rest_Constants::HTTP_NOT_FOUND );
		}

		$revision = \get_post( $revision_id );
		if ( ! $revision instanceof \WP_Post || 'revision' !== $revision->post_type ) {
			return self::create_error( 'revision_not_found', __( 'Revision not found.', 'campaignbridge' ), Rest_Constants::HTTP_NOT_FOUND );
		}

		// Guard against restoring a revision that belongs to a different post.
		if ( (int) $revision->post_parent !== $template_id ) {
			return self::create_error( 'revision_mismatch', __( 'This revision does not belong to the specified template.', 'campaignbridge' ), Rest_Constants::HTTP_BAD_REQUEST );
		}

		// Restore the post content (title, content, excerpt, etc.).
		$result = \wp_restore_post_revision( $revision_id );
		if ( is_wp_error( $result ) ) {
			return self::create_error( 'restore_failed', __( 'The revision could not be restored.', 'campaignbridge' ), Rest_Constants::HTTP_INTERNAL_SERVER_ERROR );
		}

		// Restore revisionable meta fields (wp_restore_post_revision only copies
		// post fields, not meta). Copy known revisionable meta from the revision
		// onto the parent template.
		$revisionable_meta = array(
			'_cb_template_schema_version',
			'_cb_template_blocks',
			'_cb_template_brand_kit_id',
		);
		foreach ( $revisionable_meta as $meta_key ) {
			$value = \get_post_meta( $revision_id, $meta_key, true );
			if ( '' !== $value ) {
				\update_post_meta( $template_id, $meta_key, $value );
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Revision restored successfully.', 'campaignbridge' ),
			)
		);
	}
}
