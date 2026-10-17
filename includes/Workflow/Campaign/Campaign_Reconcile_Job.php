<?php
/**
 * Background reconciliation of one campaign.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Workflow\Job\Job_Handler;
use CampaignBridge\Workflow\Job\Job_Lease;
use CampaignBridge\Workflow\Job\Job_Outcome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches one scheduled, sending, or unknown campaign until it settles.
 *
 * Each run reconciles through the same reconciler, lock, and rules as an
 * operator's reconcile, acting for no user. It only reads the provider and
 * records what it reports; it never sends, schedules, or retries a delivery.
 * A campaign that is still in flight is looked at again on the cadence; one
 * that reaches a terminal state ends the watch. When reconciliation cannot
 * settle the campaign for a reason only an operator can resolve (the
 * provider campaign is missing, in an untracked state, inconclusive, in
 * contradiction, or duplicated), the watch stops with that reason, and the
 * campaign needs operator action until it changes.
 *
 * A hinted handler runs the same reconciliation once, as soon as a provider
 * webhook says the campaign changed, even before a scheduled send time: the
 * provider may have sent it early. It never stops or replaces the watch; it
 * only lets the watch find the campaign settled sooner.
 */
final class Campaign_Reconcile_Job implements Job_Handler {
	public const TYPE      = 'reconcile_campaign';
	public const HINT_TYPE = 'reconcile_campaign_hint';

	private const NOT_CONNECTED_WAIT = 1800;
	private const BUSY_WAIT          = 60;

	/**
	 * Build the reconciliation job handler.
	 *
	 * @param Campaign_Source $campaigns   Campaign storage.
	 * @param \Closure        $reconcilers The reconciler for a provider slug, or null when it has none.
	 * @param \Closure        $settings    Decrypted settings for a provider slug, for one run only; empty when not connected.
	 * @param Campaign_Clock  $clock       Source of the current time.
	 * @param bool            $hinted      Whether this runs once on a provider hint instead of watching.
	 * @phpstan-param \Closure(string): ?Campaign_Reconciler $reconcilers
	 * @phpstan-param \Closure(string): array<string, mixed> $settings
	 */
	public function __construct(
		private readonly Campaign_Source $campaigns,
		private readonly \Closure $reconcilers,
		private readonly \Closure $settings,
		private readonly Campaign_Clock $clock,
		private readonly bool $hinted = false
	) {}

	/**
	 * The dedupe key that keeps one watch per campaign.
	 *
	 * @param string $campaign_id Campaign ID.
	 */
	public static function dedupe_key( string $campaign_id ): string {
		return self::TYPE . ':campaign:' . $campaign_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function type(): string {
		return $this->hinted ? self::HINT_TYPE : self::TYPE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Reconciliation only reads the provider and records what it reports
	 * under the campaign's version and lock, so a run that stopped part way
	 * is safe to repeat.
	 */
	public function retry_safe(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Job $job, Job_Lease $lease ): Job_Outcome {
		$campaign = $this->campaigns->get( $job->target_id() );
		if ( null === $campaign || ! Reconcile_Cadence::is_watched( $campaign->state() ) ) {
			return Job_Outcome::succeeded();
		}
		if ( ! $this->hinted && ! Reconcile_Cadence::is_due( $campaign, $this->clock->now() ) ) {
			// The send time is still ahead; there is nothing to read yet.
			return Job_Outcome::again( Reconcile_Cadence::next_delay( $campaign, $this->clock->now() ) );
		}

		$provider   = (string) $campaign->provider();
		$reconciler = ( $this->reconcilers )( $provider );
		if ( ! $reconciler instanceof Campaign_Reconciler ) {
			return Job_Outcome::failed( 'reconciliation_unsupported' );
		}
		$settings = ( $this->settings )( $provider );
		if ( array() === $settings ) {
			// Waiting for the provider connection; nothing can be read until it is back.
			return $this->hinted ? Job_Outcome::succeeded() : Job_Outcome::again( self::NOT_CONNECTED_WAIT );
		}

		$result = $reconciler->reconcile_in_background( $campaign->id(), $settings );
		unset( $settings );
		if ( $this->hinted ) {
			return $result->is_success() ? Job_Outcome::succeeded() : $this->unsettled_hint( $result );
		}
		if ( $result->is_success() ) {
			$after = $this->campaigns->get( $campaign->id() );

			return null !== $after && Reconcile_Cadence::is_watched( $after->state() )
				? Job_Outcome::again( Reconcile_Cadence::next_delay( $after, $this->clock->now() ) )
				: Job_Outcome::succeeded();
		}

		return $this->unsettled( $result );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Never called for this retry-safe handler except after its last attempt;
	 * the campaign stays as it is, and the sweep starts a new watch.
	 */
	public function abandoned( Job $job ): void {}

	/**
	 * What to do after a hinted reconciliation that did not settle the campaign.
	 *
	 * Only a busy campaign or a passing provider failure is tried again;
	 * everything else is left to the watch.
	 *
	 * @param Campaign_Reconcile_Result $result The reconciliation outcome.
	 */
	private function unsettled_hint( Campaign_Reconcile_Result $result ): Job_Outcome {
		$error = $result->error();
		$code  = $error?->code();
		if ( null !== $error && ( Campaign_Workflow_Error::LOCKED === $code || Campaign_Workflow_Error::CONFLICT === $code ) ) {
			return Job_Outcome::again( $error->retry_after() ?? self::BUSY_WAIT );
		}
		$provider = $result->provider_error();
		if ( Campaign_Workflow_Error::PROVIDER_FAILED === $code && null !== $provider && $provider->is_retryable() ) {
			return Job_Outcome::retry( $provider->code(), $provider->retry_after() );
		}

		return Job_Outcome::succeeded();
	}

	/**
	 * What to do after a reconciliation that did not settle the campaign.
	 *
	 * @param Campaign_Reconcile_Result $result The reconciliation outcome.
	 */
	private function unsettled( Campaign_Reconcile_Result $result ): Job_Outcome {
		$error = $result->error();
		if ( null === $error ) {
			return Job_Outcome::retry( 'reconciliation_failed' );
		}
		$code = $error->code();
		if ( Campaign_Workflow_Error::NOT_FOUND === $code ) {
			return Job_Outcome::succeeded();
		}
		// Someone else is working on the campaign, or it changed under this check: look again shortly.
		if ( Campaign_Workflow_Error::LOCKED === $code || Campaign_Workflow_Error::CONFLICT === $code ) {
			return Job_Outcome::again( $error->retry_after() ?? self::BUSY_WAIT );
		}
		if ( Campaign_Workflow_Error::RECONCILIATION_REQUIRED === $code ) {
			$reason = $error->reason();
			if ( Campaign_Workflow_Error::REASON_IN_PROGRESS === $reason || null === $reason ) {
				return Job_Outcome::again( $error->retry_after() ?? self::BUSY_WAIT );
			}

			// Only an operator can resolve this; stop watching until the campaign changes.
			return Job_Outcome::failed( $reason );
		}
		if ( Campaign_Workflow_Error::PROVIDER_FAILED === $code ) {
			$provider = $result->provider_error();
			if ( null !== $provider && ! $provider->is_retryable() ) {
				return Job_Outcome::failed( $provider->code() );
			}

			return Job_Outcome::retry( null === $provider ? 'provider_failed' : $provider->code(), $provider?->retry_after() );
		}

		return Job_Outcome::failed( $code );
	}
}
