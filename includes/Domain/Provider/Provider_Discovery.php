<?php
/**
 * Provider discovery adapter port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only reference discovery implemented by a provider adapter.
 *
 * Each call receives decrypted settings for that one operation only and
 * returns normalized DTOs or a normalized error. Adapters never cache,
 * persist credentials, or return provider payloads.
 */
interface Provider_Discovery {
	/**
	 * The provider this adapter talks to.
	 */
	public function slug(): string;

	/**
	 * What the provider supports.
	 */
	public function capabilities(): Provider_Capabilities;

	/**
	 * Non-reversible identity of the connected account, used to partition cached references, or null when the settings cannot identify one.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function account_key( array $settings ): ?string;

	/**
	 * The account's audiences.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function discover_audiences( array $settings ): Discovery_Batch|Provider_Error;

	/**
	 * One audience's merge fields.
	 *
	 * @param array<string, mixed> $settings    Decrypted provider settings.
	 * @param string               $audience_id The provider's audience ID.
	 */
	public function discover_merge_fields( array $settings, string $audience_id ): Discovery_Batch|Provider_Error;

	/**
	 * One audience's segments and tags.
	 *
	 * @param array<string, mixed> $settings    Decrypted provider settings.
	 * @param string               $audience_id The provider's audience ID.
	 */
	public function discover_segments( array $settings, string $audience_id ): Discovery_Batch|Provider_Error;
}
