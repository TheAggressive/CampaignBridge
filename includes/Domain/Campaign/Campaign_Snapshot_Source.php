<?php
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
	/**
	 * One snapshot by ID.
	 *
	 * @param string $id Record ID.
	 */
	public function get( string $id ): ?Campaign_Snapshot;

	/**
	 * Insert only; an existing identity or campaign revision is never replaced.
	 *
	 * @param Campaign_Snapshot $snapshot The campaign snapshot.
	 */
	public function add( Campaign_Snapshot $snapshot ): bool;

	/**
	 * A campaign's snapshots, newest revision first.
	 *
	 * @param string $campaign_id Campaign ID.
	 * @param int    $limit       Maximum number of records.
	 * @return array<int, Campaign_Snapshot>
	 */
	public function for_campaign( string $campaign_id, int $limit = 50 ): array;
}
