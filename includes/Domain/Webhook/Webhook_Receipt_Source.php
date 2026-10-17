<?php
/**
 * Webhook delivery receipt port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remembers which signed deliveries were already accepted, for as long as a
 * replay of them could still pass the signature's freshness check.
 *
 * Only a one-way key is stored, never the delivery or anything from it.
 */
interface Webhook_Receipt_Source {
	/**
	 * Record a delivery the first time it is seen.
	 *
	 * @param string $key        One-way key of the delivery.
	 * @param int    $expires_at Unix time after which the delivery can no longer be replayed.
	 * @param int    $now        Current Unix time.
	 * @return bool|null True the first time, false for a repeat, or null when receipts cannot be recorded.
	 */
	public function record( string $key, int $expires_at, int $now ): ?bool;
}
