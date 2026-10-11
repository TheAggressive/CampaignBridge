<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures and the class contract document these methods.
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
	public static function after( string $timestamp, int $seconds ): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $timestamp ) + $seconds );
	}
}
