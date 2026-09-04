<?php
/**
 * Compiled email preview route.
 *
 * @package CampaignBridge
 *
 * phpcs:disable WordPress.Files.FileName
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Storage;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Workflow\Email\Template_Preview;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles unsaved editor content and returns the canonical artifact.
 *
 * The response is the compiler's own output. A document that fails validation
 * returns its diagnostics with a 200 and no HTML rather than a generic 500,
 * because a rejected document is a result the editor must display, not a
 * server fault.
 */
class Preview_Routes extends Abstract_Rest_Controller {
	private const ENDPOINT_PATH = '/preview';

	private const MAX_CONTENT_BYTES = 512000;

	/**
	 * Metadata keys a preview accepts.
	 *
	 * Anything else is dropped rather than forwarded into the render context,
	 * so a caller cannot smuggle arbitrary keys into a compile.
	 */
	private const ALLOWED_METADATA = array(
		'title',
		'language',
		'background_color',
		'unsubscribe_url',
	);

	/**
	 * Register the preview endpoint.
	 */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_PATH,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'template_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'content'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'metadata'    => array(
						'type'     => 'object',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Compile the submitted content.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_request( WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$rate_limit = Rate_Limiter::check_rate_limit_authenticated( 'preview' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$template_id = absint( $req->get_param( 'template_id' ) );
		$template    = get_post( $template_id );

		if ( ! $template instanceof \WP_Post ) {
			return self::create_error( 'template_not_found', 'Email template not found', Rest_Constants::HTTP_NOT_FOUND );
		}

		// Previewing renders this template's content, so it is gated on the
		// capability to edit that specific template rather than a global one.
		if ( ! current_user_can( 'edit_post', $template_id ) ) {
			return self::create_error( 'rest_forbidden', 'You cannot preview this email template', Rest_Constants::HTTP_FORBIDDEN );
		}

		$content = (string) $req->get_param( 'content' );
		if ( self::MAX_CONTENT_BYTES < strlen( $content ) ) {
			return self::create_error( 'content_too_large', 'The submitted template content is too large to compile' );
		}

		$result = ( new Template_Preview( new Post_Snapshot_Repository() ) )->compile(
			$content,
			$this->metadata( $req, $template_id )
		);

		$diagnostics = array_map(
			static fn( $diagnostic ): array => $diagnostic->to_array(),
			$result->diagnostics()
		);

		return new \WP_REST_Response(
			array(
				'html'             => $result->html(),
				'text'             => $result->text(),
				'diagnostics'      => $diagnostics,
				'assets'           => $result->assets(),
				'compiler_version' => $result->compiler_version(),
				'profile_version'  => $result->profile_version(),
				'fingerprint'      => $result->fingerprint(),
			)
		);
	}

	/**
	 * Build the render metadata for this template.
	 *
	 * Caller-supplied values are narrowed to the documented keys, then the
	 * template's own stored settings win for anything security or compliance
	 * relevant so a preview cannot be pointed at another unsubscribe URL.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $req         Request object.
	 * @param int                                   $template_id Template identifier.
	 * @return array<string, mixed>
	 */
	private function metadata( WP_REST_Request $req, int $template_id ): array {
		$supplied = $req->get_param( 'metadata' );
		$metadata = array();

		if ( is_array( $supplied ) ) {
			foreach ( self::ALLOWED_METADATA as $key ) {
				if ( isset( $supplied[ $key ] ) && is_scalar( $supplied[ $key ] ) ) {
					$metadata[ $key ] = (string) $supplied[ $key ];
				}
			}
		}

		// The key already carries the plugin prefix, so the wrapper passes it
		// through unchanged while keeping the storage boundary intact.
		$stored = Storage::get_post_meta( $template_id, 'campaignbridge_unsubscribe_url', true );
		if ( is_string( $stored ) && '' !== $stored ) {
			$metadata['unsubscribe_url'] = $stored;
		}

		if ( ! isset( $metadata['title'] ) ) {
			$metadata['title'] = (string) get_the_title( $template_id );
		}

		return $metadata;
	}
}
