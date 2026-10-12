<?php
/**
 * Whether background reconciliation is watching a campaign.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_Source;
use CampaignBridge\Domain\Job\Job_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the operator should know about automatic checks for one campaign.
 *
 * Watching: a check is queued, and `next_check_at` says when. Stopped: the
 * last automatic check could not settle the campaign and will not be
 * repeated until the campaign changes; `reason` says why, so the campaign
 * needs operator action. Idle: the campaign is not in a watched state, or no
 * check has been queued yet.
 */
final class Reconcile_Watch {
	public const WATCHING = 'watching';
	public const STOPPED  = 'stopped';
	public const IDLE     = 'idle';

	/**
	 * Describe one watch state.
	 *
	 * @param string      $state         Watching, stopped, or idle.
	 * @param string|null $next_check_at When the next check is due, while watching.
	 * @param string|null $reason        Why checks stopped, when they did.
	 * @param string|null $since         When checks stopped, when they did.
	 */
	private function __construct(
		public readonly string $state,
		public readonly ?string $next_check_at,
		public readonly ?string $reason,
		public readonly ?string $since
	) {}

	/**
	 * The watch state of one campaign, read from its reconciliation jobs.
	 *
	 * @param Job_Source $jobs     Job storage.
	 * @param Campaign   $campaign The campaign as read.
	 */
	public static function of( Job_Source $jobs, Campaign $campaign ): self {
		if ( ! Reconcile_Cadence::is_watched( $campaign->state() ) ) {
			return new self( self::IDLE, null, null, null );
		}
		$active = $jobs->find_active( Campaign_Reconcile_Job::dedupe_key( $campaign->id() ) );
		if ( null !== $active ) {
			return new self( self::WATCHING, $active->run_after(), null, null );
		}
		$latest = $jobs->latest_for_target( Campaign_Reconcile_Job::TYPE, 'campaign', $campaign->id() );
		if ( null !== $latest && self::stopped( $latest, $campaign ) ) {
			return new self( self::STOPPED, null, $latest->last_error(), $latest->updated_at() );
		}

		return new self( self::IDLE, null, null, null );
	}

	/**
	 * Whether a finished check stopped watching a campaign that has not changed since.
	 *
	 * @param Job      $job      The campaign's latest reconciliation job.
	 * @param Campaign $campaign The campaign as read.
	 */
	public static function stopped( Job $job, Campaign $campaign ): bool {
		return in_array( $job->state(), array( Job_State::FAILED, Job_State::DEAD ), true ) && $job->updated_at() >= $campaign->updated_at();
	}

	/**
	 * The watch as the REST API publishes it.
	 *
	 * @return array{state: string, next_check_at: string|null, reason: string|null, since: string|null}
	 */
	public function to_array(): array {
		return array(
			'state'         => $this->state,
			'next_check_at' => $this->next_check_at,
			'reason'        => $this->reason,
			'since'         => $this->since,
		);
	}
}
