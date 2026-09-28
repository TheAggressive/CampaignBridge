<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed persistence ports use explicit signatures and focused contract comments.
/**
 * Durable campaign snapshot persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores immutable review inputs and exact compiled artifacts. */
interface Campaign_Snapshot_Source {
	public function get( string $id ): ?Campaign_Snapshot;

	/** Insert only; an existing identity or campaign revision is never replaced. */
	public function add( Campaign_Snapshot $snapshot ): bool;

	/** @return array<int, Campaign_Snapshot> */
	public function for_campaign( string $campaign_id, int $limit = 50 ): array;
}
