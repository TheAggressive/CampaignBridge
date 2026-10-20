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

	/**
	 * Campaigns needing a watch, least recently updated first.
	 *
	 * Exclude active watches and campaigns whose latest watch failed or died
	 * since the campaign last changed, before applying the limit.
	 *
	 * @param array<int, string> $states   Campaign lifecycle states.
	 * @param string             $job_type Reconciliation job type.
	 * @param int                $limit    Maximum number of records.
	 * @return array<int, Campaign>
	 */
	public function needing_watch( array $states, string $job_type, int $limit ): array;
}
