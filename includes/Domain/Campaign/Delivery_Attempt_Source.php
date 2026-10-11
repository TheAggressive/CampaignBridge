<?php
/**
 * Delivery attempt persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores mutation attempts and provider-neutral outcomes. */
interface Delivery_Attempt_Source {
	/**
	 * One attempt by ID.
	 *
	 * @param string $id Record ID.
	 */
	public function get( string $id ): ?Delivery_Attempt;

	/**
	 * The attempt recorded for one campaign, operation, and idempotency key.
	 *
	 * @param string $campaign_id     Campaign ID.
	 * @param string $operation       Delivery operation.
	 * @param string $idempotency_key Client retry key; the same key replays the first outcome.
	 */
	public function find_idempotency( string $campaign_id, string $operation, string $idempotency_key ): ?Delivery_Attempt;

	/**
	 * Insert one attempt; duplicate idempotency identities fail.
	 *
	 * @param Delivery_Attempt $attempt The delivery attempt.
	 */
	public function add( Delivery_Attempt $attempt ): bool;

	/**
	 * Update only the result fields for the same immutable attempt identity.
	 *
	 * @param Delivery_Attempt $attempt The delivery attempt.
	 */
	public function update_result( Delivery_Attempt $attempt ): bool;

	/**
	 * A page of one campaign's attempts, newest first.
	 *
	 * @param string $campaign_id Campaign ID.
	 * @param int    $limit       Maximum number of records.
	 * @param int    $offset      Number of records to skip.
	 * @return array<int, Delivery_Attempt>
	 */
	public function for_campaign( string $campaign_id, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Number of attempts recorded for one campaign.
	 *
	 * @param string $campaign_id Campaign ID.
	 */
	public function count_for_campaign( string $campaign_id ): int;
}
