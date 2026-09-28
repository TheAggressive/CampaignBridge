<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed persistence ports use explicit signatures and focused contract comments.
/**
 * Campaign persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Loads and stores typed provider-neutral campaign records. */
interface Campaign_Source {
	/** Load a campaign by its identifier. */
	public function get( string $id ): ?Campaign;

	/** Insert a new campaign, refusing duplicate identifiers. */
	public function add( Campaign $campaign ): bool;

	/**
	 * Replace a campaign only when its stored version matches.
	 *
	 * The supplied replacement must carry expected version + 1.
	 */
	public function compare_and_swap( Campaign $replacement, int $expected_version ): bool;

	/**
	 * Return a bounded newest-first owner listing.
	 *
	 * @return array<int, Campaign>
	 */
	public function for_owner( int $owner_user_id, int $limit = 50, int $offset = 0 ): array;
}
