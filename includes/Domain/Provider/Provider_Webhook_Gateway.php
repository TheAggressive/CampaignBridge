<?php
/**
 * Remote webhook registration port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Webhook\Webhook_Created;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and removes the provider webhooks that point at this site.
 *
 * Adapters receive decrypted settings for one call only and return only
 * normalized outcomes. Creating is never replayed by the HTTP layer: a
 * webhook whose creation was not confirmed is found by its URL and removed
 * before another is created.
 */
interface Provider_Webhook_Gateway {
	/**
	 * The provider this gateway talks to.
	 */
	public function slug(): string;

	/**
	 * The provider's IDs of the webhooks on one audience that point at a URL.
	 *
	 * @param array<string, mixed> $settings    Decrypted provider settings.
	 * @param string               $audience_id The provider's audience ID.
	 * @param string               $url         The webhook URL.
	 * @return array<int, string>|Provider_Error
	 */
	public function find( array $settings, string $audience_id, string $url ): array|Provider_Error;

	/**
	 * Create a signed webhook for campaign events on one audience.
	 *
	 * @param array<string, mixed> $settings    Decrypted provider settings.
	 * @param string               $audience_id The provider's audience ID.
	 * @param string               $url         The webhook URL.
	 */
	public function create( array $settings, string $audience_id, string $url ): Webhook_Created|Provider_Error;

	/**
	 * Remove one webhook; one that is already gone counts as removed.
	 *
	 * @param array<string, mixed> $settings    Decrypted provider settings.
	 * @param string               $audience_id The provider's audience ID.
	 * @param string               $remote_id   The provider's webhook ID.
	 * @return Provider_Error|null Null when the webhook is gone.
	 */
	public function remove( array $settings, string $audience_id, string $remote_id ): ?Provider_Error;
}
