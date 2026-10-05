<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed persistence ports use explicit signatures and focused contract comments.
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
	public function get( string $id ): ?Delivery_Attempt;

	public function find_idempotency( string $campaign_id, string $operation, string $idempotency_key ): ?Delivery_Attempt;

	/** Insert one attempt; duplicate idempotency identities fail. */
	public function add( Delivery_Attempt $attempt ): bool;

	/** Update only the result fields for the same immutable attempt identity. */
	public function update_result( Delivery_Attempt $attempt ): bool;

	/** @return array<int, Delivery_Attempt> */
	public function for_campaign( string $campaign_id, int $limit = 50, int $offset = 0 ): array;

	/** Number of attempts recorded for one campaign. */
	public function count_for_campaign( string $campaign_id ): int;
}
