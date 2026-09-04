<?php
/**
 * Immutable post snapshot port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies the frozen post data a compile reads.
 *
 * Renderers never fetch live content, so everything a post binding needs is
 * resolved once, ahead of rendering, and handed to the compiler as an
 * immutable snapshot. Workflow depends on this port; the WordPress reader that
 * satisfies it lives in the repository layer.
 */
interface Post_Snapshot_Source {
	/**
	 * Resolve every requested post into a snapshot record.
	 *
	 * A reference that cannot be resolved is omitted rather than faked. The
	 * compiler then reports a missing snapshot against the exact block path,
	 * which is more useful than a partially invented record.
	 *
	 * @param array<int, array{id: int, type: string}> $references Requested posts.
	 * @return array<int|string, array<string, mixed>> Snapshots keyed by post id.
	 *         PHP casts a numeric key to int, which the compiler looks up
	 *         with the same coercion.
	 */
	public function posts( array $references ): array;
}
