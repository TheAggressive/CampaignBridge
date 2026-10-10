<?php
/**
 * Enqueue background work.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_Source;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Producers ask for work through the queue, never by inserting rows.
 *
 * Work for one target is queued once: while a job of the same type for the
 * same target is active, enqueueing again returns that job instead of adding
 * a second one.
 */
final class Job_Queue {
	public const DEFAULT_ATTEMPTS = 5;

	/**
	 * Build the job queue.
	 *
	 * @param Job_Source     $jobs  Job storage.
	 * @param Campaign_Clock $clock Source of the current time.
	 */
	public function __construct(
		private readonly Job_Source $jobs,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * Queue one job, or return the active job already queued for the same work.
	 *
	 * @param string                              $type          Job type: a lowercase identifier.
	 * @param string                              $target_type   Kind of record the event is about.
	 * @param string                              $target_id     ID of the record the event is about.
	 * @param array<string, string|int|bool|null> $payload       What the handler needs; never credentials.
	 * @param int                                 $delay_seconds Seconds to wait, or null for the default backoff.
	 * @param int                                 $max_attempts  Maximum number of runs.
	 */
	public function enqueue( string $type, string $target_type, string $target_id, array $payload = array(), int $delay_seconds = 0, int $max_attempts = self::DEFAULT_ATTEMPTS ): ?Job {
		$dedupe = $type . ':' . $target_type . ':' . $target_id;
		$active = $this->jobs->find_active( $dedupe );
		if ( null !== $active ) {
			return $active;
		}

		$now = $this->clock->now();
		$job = Job::create(
			'job-' . bin2hex( random_bytes( 16 ) ),
			$type,
			$target_type,
			$target_id,
			$payload,
			$max_attempts,
			Job_Time::after( $now, max( 0, $delay_seconds ) ),
			$now,
			$dedupe
		);
		if ( $this->jobs->add( $job ) ) {
			return $job;
		}

		// Another producer queued the same work first.
		return $this->jobs->find_active( $dedupe );
	}
}
