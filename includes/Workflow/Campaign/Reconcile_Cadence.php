<?php
/**
 * When to look at a campaign's provider state next.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The background reconciliation schedule.
 *
 * A scheduled campaign is first checked two minutes after its send time; a
 * sending campaign often while the send is fresh and then less often, up to
 * hourly; an unknown outcome soon, after which the reconciler's own wait
 * (the settle window, or what the provider asked for) takes over. Only these
 * three states are watched: every other state is either terminal or needs no
 * provider read.
 */
final class Reconcile_Cadence {
	public const WATCHED = array( Campaign_State::SCHEDULED, Campaign_State::SENDING, Campaign_State::UNKNOWN );

	private const AFTER_SEND_TIME = 120;
	private const SHORTEST        = 300;
	private const LONGEST         = 3600;
	private const UNKNOWN_FIRST   = 60;

	/**
	 * Whether background reconciliation watches campaigns in this state.
	 *
	 * @param string $state Campaign lifecycle state.
	 */
	public static function is_watched( string $state ): bool {
		return in_array( $state, self::WATCHED, true );
	}

	/**
	 * Whether a watched campaign should be read now: a scheduled one only once its send time has passed.
	 *
	 * @param Campaign $campaign The campaign as read.
	 * @param string   $now      Current UTC timestamp.
	 */
	public static function is_due( Campaign $campaign, string $now ): bool {
		if ( Campaign_State::SCHEDULED !== $campaign->state() || null === $campaign->scheduled_for() ) {
			return true;
		}

		return (int) strtotime( $now ) >= (int) strtotime( $campaign->scheduled_for() ) + self::AFTER_SEND_TIME;
	}

	/**
	 * Seconds until the campaign should be checked again.
	 *
	 * @param Campaign $campaign The campaign as read.
	 * @param string   $now      Current UTC timestamp.
	 */
	public static function next_delay( Campaign $campaign, string $now ): int {
		$now_time = (int) strtotime( $now );
		if ( Campaign_State::SCHEDULED === $campaign->state() ) {
			$send_time = null === $campaign->scheduled_for() ? $now_time : (int) strtotime( $campaign->scheduled_for() );
			$until     = $send_time + self::AFTER_SEND_TIME - $now_time;

			return $until > 0 ? $until : self::SHORTEST;
		}
		if ( Campaign_State::SENDING === $campaign->state() ) {
			$age = max( 0, $now_time - (int) strtotime( $campaign->updated_at() ) );

			return max( self::SHORTEST, min( self::LONGEST, intdiv( $age, 2 ) ) );
		}

		return self::UNKNOWN_FIRST;
	}
}
