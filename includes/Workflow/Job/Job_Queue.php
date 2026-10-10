<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures and the class contract document these methods.
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

	public function __construct(
		private readonly Job_Source $jobs,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * Queue one job, or return the active job already queued for the same work.
	 *
	 * @param array<string, string|int|bool|null> $payload What the handler needs; never credentials.
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
