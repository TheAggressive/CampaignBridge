<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed ports and values use explicit signatures and class-level documentation.
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
	public function slug(): string;

	public function capabilities(): Provider_Capabilities;

	/**
	 * Non-reversible identity of the connected account, used to partition
	 * cached references, or null when the settings cannot identify one.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function account_key( array $settings ): ?string;

	/** @param array<string, mixed> $settings Decrypted provider settings. */
	public function discover_audiences( array $settings ): Discovery_Batch|Provider_Error;

	/** @param array<string, mixed> $settings Decrypted provider settings. */
	public function discover_merge_fields( array $settings, string $audience_id ): Discovery_Batch|Provider_Error;

	/** @param array<string, mixed> $settings Decrypted provider settings. */
	public function discover_segments( array $settings, string $audience_id ): Discovery_Batch|Provider_Error;
}
