<?php
/**
 * Snapshot reference collection.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Finds the posts a block tree binds to, before anything is rendered. */
final class Snapshot_References {
	private const BINDING_BLOCK = 'campaignbridge/post-card';

	/**
	 * Collect every distinct post a parsed block tree binds to.
	 *
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @return array<int, array{id: int, type: string}> Distinct references.
	 */
	public static function collect( array $blocks ): array {
		$found = array();
		self::walk( $blocks, $found );

		return array_values( $found );
	}

	/**
	 * Walk a block list, recording post bindings.
	 *
	 * @param array<int, mixed>                           $blocks Parsed blocks.
	 * @param array<string, array{id: int, type: string}> $found  Accumulated references.
	 */
	private static function walk( array $blocks, array &$found ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( self::BINDING_BLOCK === ( $block['blockName'] ?? null ) ) {
				$attributes = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$id         = (int) ( $attributes['postId'] ?? 0 );
				$type       = (string) ( $attributes['postType'] ?? 'post' );

				// A card with no selection yet is not a resolvable reference.
				if ( 0 < $id ) {
					$found[ $type . ':' . $id ] = array(
						'id'   => $id,
						'type' => $type,
					);
				}
			}

			$children = $block['innerBlocks'] ?? null;
			if ( is_array( $children ) ) {
				self::walk( $children, $found );
			}
		}
	}
}
