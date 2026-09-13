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

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restore a saved revision of an email template.
 *
 * WordPress core exposes revision reading through the REST API but ships no
 * restore endpoint. This route restores a WordPress-created revision onto its
 * `cb_templates` parent.
 *
 * Authentication is standard WordPress REST authentication (a logged-in
 * cookie with its REST nonce, Application Passwords, or any other configured
 * method), enforced by the REST server before this route runs. Authorization
 * uses the template post type's mapped capabilities, both for the post type
 * and for the specific template.
 */
final class Template_Routes extends Abstract_Rest_Controller {
	private const RESTORE_PATH = '/templates/(?P<id>\d+)/revisions/(?P<revision_id>\d+)/restore';

	public const ERROR_UNAUTHENTICATED      = 'unauthenticated';
	public const ERROR_FORBIDDEN            = 'forbidden';
	public const ERROR_TEMPLATE_NOT_FOUND   = 'template_not_found';
	public const ERROR_REVISIONS_DISABLED   = 'revisions_disabled';
	public const ERROR_REVISION_NOT_FOUND   = 'revision_not_found';
	public const ERROR_REVISION_MISMATCH    = 'revision_mismatch';
	public const ERROR_REVISION_IS_AUTOSAVE = 'revision_is_autosave';
	public const ERROR_RESTORE_FAILED       = 'restore_failed';

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
				'permission_callback' => array( $this, 'can_restore_revision' ),
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
	 * Authorize restoring revisions of the requested template.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return true|WP_Error
	 */
	public function can_restore_revision( WP_REST_Request $request ): true|WP_Error {
		if ( ! \is_user_logged_in() ) {
			return self::create_error(
				self::ERROR_UNAUTHENTICATED,
				__( 'Sign in to restore email template revisions.', 'campaignbridge' ),
				Rest_Constants::HTTP_UNAUTHORIZED
			);
		}

		// The post type registration maps its edit capabilities to the
		// CampaignBridge template capability. Checking it before looking up the
		// template keeps unauthorized callers from probing template IDs.
		$post_type = \get_post_type_object( Post_Type_Email_Template::POST_TYPE );
		if ( null === $post_type || ! \current_user_can( $post_type->cap->edit_posts ) ) {
			return self::forbidden();
		}

		$template = self::find_template( (int) $request->get_param( 'id' ) );
		if ( null === $template ) {
			return self::template_not_found();
		}

		if ( ! \current_user_can( 'edit_post', $template->ID ) ) {
			return self::forbidden();
		}

		return true;
	}

	/**
	 * Restore the specified revision onto the parent template.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_revision( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$template_id = (int) $request->get_param( 'id' );
		$revision_id = (int) $request->get_param( 'revision_id' );

		$template = self::find_template( $template_id );
		if ( null === $template ) {
			return self::template_not_found();
		}

		if ( ! \wp_revisions_enabled( $template ) ) {
			return self::create_error(
				self::ERROR_REVISIONS_DISABLED,
				__( 'Revisions are not available for this email template.', 'campaignbridge' ),
				Rest_Constants::HTTP_CONFLICT
			);
		}

		$revision = \wp_get_post_revision( $revision_id );
		if ( ! $revision instanceof WP_Post ) {
			return self::create_error(
				self::ERROR_REVISION_NOT_FOUND,
				__( 'Revision not found.', 'campaignbridge' ),
				Rest_Constants::HTTP_NOT_FOUND
			);
		}

		if ( (int) $revision->post_parent !== $template_id ) {
			return self::create_error(
				self::ERROR_REVISION_MISMATCH,
				__( 'This revision does not belong to the specified email template.', 'campaignbridge' ),
				Rest_Constants::HTTP_BAD_REQUEST
			);
		}

		// An autosave is unsaved recovery state, not template history. Restoring
		// it would publish that state as if an operator had saved it.
		if ( false !== \wp_is_post_autosave( $revision ) ) {
			return self::create_error(
				self::ERROR_REVISION_IS_AUTOSAVE,
				__( 'Autosaves cannot be restored as template revisions.', 'campaignbridge' ),
				Rest_Constants::HTTP_BAD_REQUEST
			);
		}

		// WordPress restores the post fields and then, through its
		// `wp_restore_post_revision` action, every meta key registered with
		// `revisions_enabled`. That action fires only after the post update
		// succeeds, so a failed restore writes no meta. The template meta
		// registration is the only list of revisioned keys.
		//
		// The post update would also save a revision before that meta is copied
		// back, pairing the restored content with the replaced meta. Defer that
		// one revision for this template until the restore has completed.
		$defer_revision = static function ( bool $post_has_changed, WP_Post $latest_revision, WP_Post $post ) use ( $template_id ): bool {
			unset( $latest_revision );
			return $template_id === $post->ID ? false : $post_has_changed;
		};

		\add_filter( 'wp_save_post_revision_post_has_changed', $defer_revision, PHP_INT_MAX, 3 );
		try {
			$result = \wp_restore_post_revision( $revision_id );
		} finally {
			\remove_filter( 'wp_save_post_revision_post_has_changed', $defer_revision, PHP_INT_MAX );
		}

		// WordPress reports failure as null, false, 0, or a WP_Error; success is
		// exactly the restored template's ID.
		if ( $template_id !== $result ) {
			return self::create_error(
				self::ERROR_RESTORE_FAILED,
				__( 'The revision could not be restored.', 'campaignbridge' ),
				Rest_Constants::HTTP_INTERNAL_SERVER_ERROR
			);
		}

		// Record the fully restored content and meta as the newest revision.
		\wp_save_post_revision( $template_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Revision restored successfully.', 'campaignbridge' ),
			)
		);
	}

	/**
	 * Load an email template by ID, rejecting every other post type.
	 *
	 * @param int $template_id Template ID.
	 */
	private static function find_template( int $template_id ): ?WP_Post {
		$template = \get_post( $template_id );

		return $template instanceof WP_Post && Post_Type_Email_Template::POST_TYPE === $template->post_type
			? $template
			: null;
	}

	/**
	 * Error for a caller who may not restore this template's revisions.
	 */
	private static function forbidden(): WP_Error {
		return self::create_error(
			self::ERROR_FORBIDDEN,
			__( 'You are not allowed to restore revisions of this email template.', 'campaignbridge' ),
			Rest_Constants::HTTP_FORBIDDEN
		);
	}

	/**
	 * Error for a missing template or a post that is not an email template.
	 */
	private static function template_not_found(): WP_Error {
		return self::create_error(
			self::ERROR_TEMPLATE_NOT_FOUND,
			__( 'Email template not found.', 'campaignbridge' ),
			Rest_Constants::HTTP_NOT_FOUND
		);
	}
}
