<?php
/**
 * Campaigns in states background reconciliation watches.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads campaigns by state across every owner, for background work only. */
interface Watched_Campaign_Source {
	/**
	 * Campaigns in any of the states, least recently updated first.
	 *
	 * @param array<int, string> $states Campaign lifecycle states.
	 * @param int                $limit  Maximum number of records.
	 * @return array<int, Campaign>
	 */
	public function watched( array $states, int $limit ): array;
}
