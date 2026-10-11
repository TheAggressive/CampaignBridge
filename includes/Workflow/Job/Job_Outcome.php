<?php
/**
 * What one job run achieved.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

use CampaignBridge\Domain\Campaign\Record_Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Success, a retry after a delay, or a permanent failure, with a stable error code. */
final class Job_Outcome {
	public const SUCCEEDED = 'succeeded';
	public const RETRY     = 'retry';
	public const FAILED    = 'failed';

	/**
	 * Build the job outcome.
	 *
	 * @param string      $kind          Outcome kind.
	 * @param string|null $error         Stable error code, when there is one.
	 * @param int|null    $delay_seconds Seconds to wait, or null for the default backoff.
	 */
	private function __construct(
		public readonly string $kind,
		public readonly ?string $error,
		public readonly ?int $delay_seconds
	) {}

	/**
	 * The job did its work.
	 */
	public static function succeeded(): self {
		return new self( self::SUCCEEDED, null, null );
	}

	/**
	 * Run again later; null leaves the delay to the runner's backoff.
	 *
	 * @param string   $error         Stable error code.
	 * @param int|null $delay_seconds Seconds to wait, or null for the default backoff.
	 */
	public static function retry( string $error, ?int $delay_seconds = null ): self {
		return new self( self::RETRY, Record_Validation::identifier( $error, 'Job error code' ), null === $delay_seconds ? null : max( 0, $delay_seconds ) );
	}

	/**
	 * The job cannot succeed; do not run it again.
	 *
	 * @param string $error Stable error code.
	 */
	public static function failed( string $error ): self {
		return new self( self::FAILED, Record_Validation::identifier( $error, 'Job error code' ), null );
	}
}
