<?php
/**
 * Accept one provider webhook delivery.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Webhook;

use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference_Source;
use CampaignBridge\Domain\Provider\Provider_Webhook_Receiver;
use CampaignBridge\Domain\Webhook\Webhook_Receipt_Source;
use CampaignBridge\Domain\Webhook\Webhook_Subscription_Source;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Reconcile_Job;
use CampaignBridge\Workflow\Campaign\Reconcile_Cadence;
use CampaignBridge\Workflow\Job\Job_Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns an authentic webhook delivery into a prompt reconciliation.
 *
 * A delivery is only a hint. It never changes a campaign: when it names a
 * campaign that is being watched, one reconciliation of that campaign is
 * queued to run now, and reconciliation reads the provider before anything
 * changes. Hints for the same campaign collapse into one queued run.
 * Polling stays the source of correctness, so a delivery that is lost,
 * refused, or late only delays an update.
 *
 * The body is checked against every stored signing secret before anything is
 * read from it. A replay inside the signature's freshness window is
 * recognized by its receipt; after the window it fails the signature check.
 */
final class Webhook_Intake {
	public const ACCEPTED    = 'accepted';
	public const DUPLICATE   = 'duplicate';
	public const IGNORED     = 'ignored';
	public const REFUSED     = 'refused';
	public const MALFORMED   = 'malformed';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * Build the intake.
	 *
	 * @param Provider_Webhook_Receiver        $receiver      Provider signature and payload rules.
	 * @param Webhook_Subscription_Source      $subscriptions Stored webhooks and their encrypted secrets.
	 * @param \Closure                         $decrypt       Decrypts one stored secret; throws when it cannot.
	 * @param Webhook_Receipt_Source           $receipts      Deliveries already accepted.
	 * @param Remote_Campaign_Reference_Source $references    Provider campaign IDs to local campaigns.
	 * @param Campaign_Source                  $campaigns     Campaign storage.
	 * @param Job_Queue                        $queue         Queues the hinted reconciliation.
	 * @param Campaign_Clock                   $clock         Source of the current time.
	 * @phpstan-param \Closure(string): string $decrypt
	 */
	public function __construct(
		private readonly Provider_Webhook_Receiver $receiver,
		private readonly Webhook_Subscription_Source $subscriptions,
		private readonly \Closure $decrypt,
		private readonly Webhook_Receipt_Source $receipts,
		private readonly Remote_Campaign_Reference_Source $references,
		private readonly Campaign_Source $campaigns,
		private readonly Job_Queue $queue,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * Accept, ignore, or refuse one delivery.
	 *
	 * @param string                $raw_body The exact request body.
	 * @param array<string, string> $headers  Request headers, lowercase with underscores.
	 * @return string One of the outcome constants.
	 */
	public function receive( string $raw_body, array $headers ): string {
		$now     = (int) strtotime( $this->clock->now() );
		$secrets = $this->secrets();
		if ( array() === $secrets ) {
			return self::REFUSED;
		}
		$delivery = $this->receiver->authenticate( $raw_body, $headers, $secrets, $now );
		unset( $secrets );
		if ( null === $delivery ) {
			return self::REFUSED;
		}

		$event = $this->receiver->event( $raw_body );
		if ( null === $event ) {
			return self::MALFORMED;
		}
		$first = $this->receipts->record( $delivery->key(), $delivery->expires_at(), $now );
		if ( null === $first ) {
			return self::UNAVAILABLE;
		}
		if ( ! $first ) {
			return self::DUPLICATE;
		}

		$remote_id = $event->remote_campaign_id();
		if ( null === $remote_id ) {
			return self::IGNORED;
		}
		$reference = $this->references->find_remote( $this->receiver->slug(), $remote_id );
		$campaign  = null === $reference ? null : $this->campaigns->get( $reference->campaign_id() );
		if ( null === $campaign || ! Reconcile_Cadence::is_watched( $campaign->state() ) ) {
			// Not a CampaignBridge campaign, or one with nothing left to settle.
			return self::IGNORED;
		}

		return null === $this->queue->enqueue( Campaign_Reconcile_Job::HINT_TYPE, 'campaign', $campaign->id() )
			? self::UNAVAILABLE
			: self::ACCEPTED;
	}

	/**
	 * Every stored secret that can be decrypted, for this check only.
	 *
	 * @return array<int, string>
	 */
	private function secrets(): array {
		$secrets = array();
		foreach ( $this->subscriptions->all( $this->receiver->slug() ) as $subscription ) {
			try {
				$secret = ( $this->decrypt )( $subscription->signing_secret() );
			} catch ( \Throwable ) {
				continue;
			}
			if ( '' !== $secret ) {
				$secrets[] = $secret;
			}
		}

		return $secrets;
	}
}
