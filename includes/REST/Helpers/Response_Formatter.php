<?php
/**
 * Response Formatter for CampaignBridge REST API.
 *
 * Handles response formatting and data filtering for REST API endpoints
 * with proper security considerations.
 *
 * @package CampaignBridge\REST\Helpers
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Response Formatter class.
 *
 * Provides methods for formatting REST API responses.
 */
class Response_Formatter {
	/**
	 * Format posts response data.
	 *
	 * @param array<int, int> $post_ids   Array of post IDs keyed by index.
	 * @param int             $max_words  Maximum words for the excerpt preview.
	 * @return list<array{id: int, label: string, excerptPreview: string}> Formatted posts data.
	 */
	public static function format_posts_response( array $post_ids, int $max_words = \CampaignBridge\REST\Rest_Constants::DEFAULT_EXCERPT_MAX_WORDS ): array {
		$repository = new \CampaignBridge\Repository\Post_Snapshot_Repository();

		$items = array();
		foreach ( (array) $post_ids as $pid ) {
			$title_value   = get_post_field( 'post_title', $pid );
			$title_raw     = is_scalar( $title_value ) ? (string) $title_value : '';
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$title_escaped = esc_html( $title_decoded ); // Escape HTML to prevent XSS.
			$items[]       = array(
				'id'             => (int) $pid,
				'label'          => $title_escaped,
				'excerptPreview' => $repository->resolve_excerpt_preview( (int) $pid, $max_words ),
			);
		}
		return $items;
	}
}
