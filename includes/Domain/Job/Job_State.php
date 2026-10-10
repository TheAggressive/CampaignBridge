<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures and the class contract document these methods.
/**
 * Background job lifecycle states.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a job is in its life.
 *
 * `queued` waits for `run_after`; `claimed` is held by one worker until its
 * lease expires; `succeeded`, `failed` (the handler reported a permanent
 * failure), and `dead` (attempts exhausted, abandoned by a crashed worker, or
 * no handler) never run again.
 */
final class Job_State {
	public const QUEUED    = 'queued';
	public const CLAIMED   = 'claimed';
	public const SUCCEEDED = 'succeeded';
	public const FAILED    = 'failed';
	public const DEAD      = 'dead';

	/** @return array<int, string> */
	public static function all(): array {
		return array( self::QUEUED, self::CLAIMED, self::SUCCEEDED, self::FAILED, self::DEAD );
	}

	/** Whether a job in this state can still run. */
	public static function is_active( string $state ): bool {
		return self::QUEUED === $state || self::CLAIMED === $state;
	}
}
