<?php
/**
 * WordPress post snapshot reader.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Email\Post_Snapshot_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Freezes published WordPress posts into compiler snapshots.
 *
 * This is the only place the email pipeline reads live post data. Everything
 * downstream sees an immutable array, so a compile cannot vary with a post
 * edited midway through rendering.
 */
final class Post_Snapshot_Repository implements Post_Snapshot_Source {
	private const EXCERPT_WORDS = 150;

	/**
	 * {@inheritDoc}
	 *
	 * @param array<int, array{id: int, type: string}> $references Requested posts.
	 * @return array<int|string, array<string, mixed>>
	 */
	public function posts( array $references ): array {
		$snapshots = array();

		foreach ( $references as $reference ) {
			$snapshot = $this->snapshot( (int) $reference['id'], (string) $reference['type'] );

			if ( null !== $snapshot ) {
				$snapshots[ (string) $reference['id'] ] = $snapshot;
			}
		}

		return $snapshots;
	}

	/**
	 * Freeze one post, or return null when it cannot be represented.
	 *
	 * @param int    $post_id   Post identifier.
	 * @param string $post_type Expected post type.
	 * @return array<string, mixed>|null
	 */
	private function snapshot( int $post_id, string $post_type ): ?array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || $post->post_type !== $post_type ) {
			return null;
		}

		// A reader must be able to open what the email links to.
		if ( 'publish' !== $post->post_status ) {
			return null;
		}

		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) ) {
			return null;
		}

		$snapshot = array(
			'title'   => (string) get_the_title( $post ),
			'excerpt' => $this->excerpt( $post ),
			'url'     => $permalink,
		);

		$image = $this->image( $post_id );
		if ( null !== $image ) {
			$snapshot['image'] = $image;
		}

		$parent_url = 0 < (int) $post->post_parent ? get_permalink( (int) $post->post_parent ) : null;
		if ( is_string( $parent_url ) ) {
			$snapshot['postParentUrl'] = $parent_url;
		}

		$archive_url = get_post_type_archive_link( $post_type );
		if ( is_string( $archive_url ) ) {
			$snapshot['postTypeArchiveUrl'] = $archive_url;
		}

		return $snapshot;
	}

	/**
	 * Resolve the excerpt without invoking frontend rendering filters.
	 *
	 * @param \WP_Post $post Source post.
	 */
	private function excerpt( \WP_Post $post ): string {
		$excerpt = trim( (string) $post->post_excerpt );

		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words(
				wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ),
				self::EXCERPT_WORDS,
				''
			);
		}

		return trim( $excerpt );
	}

	/**
	 * Resolve the featured image with the dimensions email markup requires.
	 *
	 * @param int $post_id Post identifier.
	 * @return array<string, mixed>|null
	 */
	private function image( int $post_id ): ?array {
		$attachment_id = (int) get_post_thumbnail_id( $post_id );
		if ( 1 > $attachment_id ) {
			return null;
		}

		$source = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $source ) || ! is_string( $source[0] ?? null ) ) {
			return null;
		}

		$width  = (int) ( $source[1] ?? 0 );
		$height = (int) ( $source[2] ?? 0 );

		// Email images need explicit positive dimensions; without them the
		// compiler would reject the block anyway.
		if ( 1 > $width || 1 > $height ) {
			return null;
		}

		return array(
			'url'    => (string) $source[0],
			'alt'    => (string) Storage::get_core_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'width'  => $width,
			'height' => $height,
		);
	}
}
