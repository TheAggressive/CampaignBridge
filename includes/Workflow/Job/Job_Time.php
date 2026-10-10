<?php
/**
 * Timestamp arithmetic for jobs.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds seconds to canonical UTC timestamps. */
final class Job_Time {
	/**
	 * A canonical UTC timestamp a number of seconds after another.
	 *
	 * @param string $timestamp UTC timestamp.
	 * @param int    $seconds   Seconds to add.
	 */
	public static function after( string $timestamp, int $seconds ): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $timestamp ) + $seconds );
	}
}
