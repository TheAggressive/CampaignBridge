<?php
/**
 * A provider webhook delivery, reduced to what CampaignBridge acts on.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which provider campaign a delivery is about, if any.
 *
 * A webhook body is only a hint that something changed. Nothing else from
 * it is kept: the campaign is read from the provider before any state
 * changes, and deliveries about subscribers are ignored without being read
 * further.
 */
final class Webhook_Event {
	/**
	 * Build the event.
	 *
	 * @param string|null $remote_campaign_id The provider's campaign ID, or null when the delivery is not about a campaign.
	 */
	private function __construct(
		private readonly ?string $remote_campaign_id
	) {}

	/**
	 * A delivery about one provider campaign.
	 *
	 * @param string $remote_campaign_id The provider's campaign ID.
	 */
	public static function campaign( string $remote_campaign_id ): self {
		return new self( $remote_campaign_id );
	}

	/**
	 * A delivery CampaignBridge does not act on.
	 */
	public static function ignored(): self {
		return new self( null );
	}

	/**
	 * The provider's campaign ID, or null when the delivery is not about a campaign.
	 */
	public function remote_campaign_id(): ?string {
		return $this->remote_campaign_id;
	}
}
