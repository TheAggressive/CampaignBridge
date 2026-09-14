<?php
/**
 * Atomic email template creation through the core REST API.
 *
 * @package CampaignBridge\REST
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use WP_Error;
use WP_Post;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core's posts controller inserts a template before it validates and stores
 * the request's metadata. A create that then fails would leave a template
 * with missing metadata behind while reporting an error. This guard deletes
 * the template a failed create request inserted, so a create either returns
 * the complete template or leaves nothing.
 */
final class Template_Create_Guard {
	/**
	 * Template IDs inserted by create requests still being handled, keyed by
	 * request object ID.
	 *
	 * @var array<int, int>
	 */
	private static array $created = array();

	/**
	 * Attach the guard to the core template REST lifecycle.
	 */
	public static function register(): void {
		$insert_hook = 'rest_insert_' . Post_Type_Email_Template::POST_TYPE;

		if ( false === has_action( $insert_hook, array( self::class, 'remember_created' ) ) ) {
			add_action( $insert_hook, array( self::class, 'remember_created' ), 10, 3 );
		}

		if ( false === has_filter( 'rest_request_after_callbacks', array( self::class, 'discard_failed_create' ) ) ) {
			add_filter( 'rest_request_after_callbacks', array( self::class, 'discard_failed_create' ), 10, 3 );
		}
	}

	/**
	 * Remember a template inserted by a create request.
	 *
	 * @param WP_Post                              $post     Inserted template.
	 * @param WP_REST_Request<array<string,mixed>> $request  Request object.
	 * @param bool                                 $creating Whether the request creates the template.
	 */
	public static function remember_created( WP_Post $post, WP_REST_Request $request, bool $creating ): void {
		if ( $creating ) {
			self::$created[ spl_object_id( $request ) ] = $post->ID;
		}
	}

	/**
	 * Delete the template a create request inserted when that request failed.
	 *
	 * @param mixed                                $response Handler result.
	 * @param array<string, mixed>                 $handler  Route handler.
	 * @param WP_REST_Request<array<string,mixed>> $request  Request object.
	 * @return mixed The unchanged handler result.
	 */
	public static function discard_failed_create( mixed $response, array $handler, WP_REST_Request $request ): mixed {
		unset( $handler );
		$request_id = spl_object_id( $request );
		if ( ! isset( self::$created[ $request_id ] ) ) {
			return $response;
		}

		$template_id = self::$created[ $request_id ];
		unset( self::$created[ $request_id ] );

		if ( $response instanceof WP_Error ) {
			wp_delete_post( $template_id, true );
		}

		return $response;
	}
}
