<?php
/**
 * Webhook subscription persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and stores the webhooks a provider holds for this site, one per audience.
 */
interface Webhook_Subscription_Source {
	/**
	 * Every webhook stored for one provider, keyed by audience ID.
	 *
	 * @param string $provider Provider slug.
	 * @return array<string, Webhook_Subscription>
	 */
	public function all( string $provider ): array;

	/**
	 * Store or replace the webhook for one audience.
	 *
	 * @param Webhook_Subscription $subscription The webhook.
	 */
	public function save( Webhook_Subscription $subscription ): bool;

	/**
	 * Forget the webhook for one audience.
	 *
	 * @param string $provider    Provider slug.
	 * @param string $audience_id The provider's audience ID.
	 */
	public function remove( string $provider, string $audience_id ): bool;
}
