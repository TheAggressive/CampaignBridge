<?php
/**
 * Runs due background jobs.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_Claim;
use CampaignBridge\Domain\Job\Job_Source;
use CampaignBridge\Domain\Job\Job_State;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claims a bounded batch of jobs and runs each through its handler.
 *
 * A job is run only by the worker holding its lease. A job taken over from a
 * worker that stopped is run again only if its handler is retry-safe;
 * otherwise, and whenever a handler that is not retry-safe throws part way,
 * the handler is told the job was abandoned and the job ends `dead`, because
 * running it again could repeat an irreversible effect. Retries back off
 * exponentially and end `dead` once attempts are used up. Error codes are
 * stored; exception messages and provider responses never are.
 */
final class Job_Runner {
	public const LEASE_SECONDS = 120;
	private const BACKOFF_BASE = 60;
	private const BACKOFF_CAP  = 3600;

	/**
	 * Registered handlers by the job type they run.
	 *
	 * @var array<string, Job_Handler>
	 */
	private array $handlers = array();

	/**
	 * This worker's lease identity, unique per runner.
	 *
	 * @var string
	 */
	private readonly string $owner;

	/**
	 * Build the job runner.
	 *
	 * @param Job_Source              $jobs     Job storage.
	 * @param array<int, Job_Handler> $handlers Handlers by the type they run.
	 * @param Campaign_Clock          $clock    Source of the current time.
	 * @param string|null             $owner    Lease or lock owner identity.
	 */
	public function __construct(
		private readonly Job_Source $jobs,
		array $handlers,
		private readonly Campaign_Clock $clock,
		?string $owner = null
	) {
		foreach ( $handlers as $handler ) {
			$this->handlers[ $handler->type() ] = $handler;
		}
		$this->owner = $owner ?? 'worker-' . bin2hex( random_bytes( 8 ) );
	}

	/**
	 * Run up to `$limit` due jobs.
	 *
	 * @param int $limit Maximum number of records.
	 * @return array{claimed: int, succeeded: int, retried: int, failed: int, dead: int}
	 */
	public function run( int $limit ): array {
		$report = array(
			'claimed'   => 0,
			'succeeded' => 0,
			'retried'   => 0,
			'failed'    => 0,
			'dead'      => 0,
		);
		$now    = $this->clock->now();
		foreach ( $this->jobs->claim( $this->owner, $now, Job_Time::after( $now, self::LEASE_SECONDS ), $limit ) as $claim ) {
			++$report['claimed'];
			$result = $this->run_one( $claim );
			if ( null !== $result ) {
				++$report[ $result ];
			}
		}

		return $report;
	}

	/**
	 * Run one claimed job and record its outcome.
	 *
	 * @param Job_Claim $claim The claimed job.
	 * @return 'succeeded'|'retried'|'failed'|'dead'|null The recorded result, or null when the lease was lost.
	 */
	private function run_one( Job_Claim $claim ): ?string {
		$job     = $claim->job;
		$handler = $this->handlers[ $job->type() ] ?? null;
		if ( null === $handler ) {
			return $this->end( $job, Job_State::DEAD, 'no_handler' );
		}
		if ( $claim->exhausted ) {
			$this->abandon( $handler, $job );
			return $this->end( $job, Job_State::DEAD, 'attempts_exhausted' );
		}
		if ( $claim->taken_over && ! $handler->retry_safe() ) {
			$this->abandon( $handler, $job );
			return $this->end( $job, Job_State::DEAD, 'abandoned' );
		}

		$lease = new Job_Lease(
			function () use ( $job ): bool {
				$now = $this->clock->now();
				return $this->jobs->heartbeat( $job->id(), $this->owner, Job_Time::after( $now, self::LEASE_SECONDS ), $now );
			}
		);
		try {
			$outcome = $handler->handle( $job, $lease );
		} catch ( \Throwable ) {
			if ( ! $handler->retry_safe() ) {
				$this->abandon( $handler, $job );
				return $this->end( $job, Job_State::DEAD, 'abandoned' );
			}
			$outcome = Job_Outcome::retry( 'handler_exception' );
		}

		if ( Job_Outcome::SUCCEEDED === $outcome->kind ) {
			return $this->jobs->succeed( $job->id(), $this->owner, $this->clock->now() ) ? 'succeeded' : null;
		}
		if ( Job_Outcome::FAILED === $outcome->kind ) {
			return $this->end( $job, Job_State::FAILED, (string) $outcome->error );
		}
		if ( ! $job->has_attempts_left() ) {
			return $this->end( $job, Job_State::DEAD, (string) $outcome->error );
		}

		$now   = $this->clock->now();
		$delay = $outcome->delay_seconds ?? self::backoff( $job->attempts() );

		return $this->jobs->retry( $job->id(), $this->owner, Job_Time::after( $now, $delay ), (string) $outcome->error, $now ) ? 'retried' : null;
	}

	/**
	 * End a held job as failed or dead.
	 *
	 * @param Job    $job   The job.
	 * @param string $state Job state.
	 * @param string $error Stable error code.
	 * @return 'failed'|'dead'|null
	 */
	private function end( Job $job, string $state, string $error ): ?string {
		if ( ! $this->jobs->finish( $job->id(), $this->owner, $state, $error, $this->clock->now() ) ) {
			return null;
		}

		return Job_State::FAILED === $state ? 'failed' : 'dead';
	}

	/**
	 * Tell the handler its job was abandoned, never letting the hook keep the job claimed.
	 *
	 * @param Job_Handler $handler The job's handler.
	 * @param Job         $job     The job.
	 */
	private function abandon( Job_Handler $handler, Job $job ): void {
		try {
			$handler->abandoned( $job );
		} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The job still ends; a failing hook must not keep it claimed.
		}
	}

	/**
	 * Seconds before retry `$attempts`: 1, 2, 4 … minutes, capped at an hour.
	 *
	 * @param int $attempts Delivery attempt storage.
	 */
	public static function backoff( int $attempts ): int {
		return min( self::BACKOFF_CAP, self::BACKOFF_BASE * ( 2 ** max( 0, $attempts - 1 ) ) );
	}
}
