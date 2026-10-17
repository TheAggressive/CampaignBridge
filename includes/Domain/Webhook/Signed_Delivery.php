<?php
/**
 * Proof that a webhook delivery is authentic and fresh.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A delivery whose signature matched a stored secret inside the provider's
 * freshness window.
 *
 * The key identifies this exact signed delivery without revealing it, so a
 * replay inside the window can be recognized; after `expires_at` a replay
 * fails the freshness check on its own.
 */
final class Signed_Delivery {
	/**
	 * Build the proof.
	 *
	 * @param string $key        One-way key of the signed delivery.
	 * @param int    $expires_at Unix time after which the signature is no longer fresh.
	 */
	public function __construct(
		private readonly string $key,
		private readonly int $expires_at
	) {}

	/**
	 * One-way key of the signed delivery.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Unix time after which the signature is no longer fresh.
	 */
	public function expires_at(): int {
		return $this->expires_at;
	}
}
