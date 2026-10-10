<?php
/**
 * Remote campaign reference persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maps local campaigns to normalized provider-owned identifiers. */
interface Remote_Campaign_Reference_Source {
	/**
	 * A campaign's reference for one provider.
	 *
	 * @param string $campaign_id Campaign ID.
	 * @param string $provider    Provider slug.
	 */
	public function get( string $campaign_id, string $provider ): ?Remote_Campaign_Reference;

	/**
	 * The reference that maps to one provider campaign.
	 *
	 * @param string $provider  Provider slug.
	 * @param string $remote_id The provider's campaign ID.
	 */
	public function find_remote( string $provider, string $remote_id ): ?Remote_Campaign_Reference;

	/**
	 * Insert one mapping; duplicate local/provider or provider/remote identities fail.
	 *
	 * @param Remote_Campaign_Reference $reference The campaign's remote reference.
	 */
	public function add( Remote_Campaign_Reference $reference ): bool;

	/**
	 * Update observations without changing either side of the identity mapping.
	 *
	 * @param Remote_Campaign_Reference $reference The campaign's remote reference.
	 */
	public function update_observation( Remote_Campaign_Reference $reference ): bool;
}
