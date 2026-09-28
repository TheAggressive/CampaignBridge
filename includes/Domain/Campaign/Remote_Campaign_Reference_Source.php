<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed persistence ports use explicit signatures and focused contract comments.
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
	public function get( string $campaign_id, string $provider ): ?Remote_Campaign_Reference;

	public function find_remote( string $provider, string $remote_id ): ?Remote_Campaign_Reference;

	/** Insert one mapping; duplicate local/provider or provider/remote identities fail. */
	public function add( Remote_Campaign_Reference $reference ): bool;

	/** Update observations without changing either side of the identity mapping. */
	public function update_observation( Remote_Campaign_Reference $reference ): bool;
}
