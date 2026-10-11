<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile,CampaignBridge.Standard.Sniffs.Database
/**
 * Durable jobs: storage, claiming, and running.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_State;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Job\Job_Handler;
use CampaignBridge\Workflow\Job\Job_Lease;
use CampaignBridge\Workflow\Job\Job_Outcome;
use CampaignBridge\Workflow\Job\Job_Queue;
use CampaignBridge\Workflow\Job\Job_Runner;
use CampaignBridge\Workflow\Job\Job_Time;

final class Job_Test_Clock implements Campaign_Clock {
	public int $now = 1790000000;

	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->now );
	}
}

/** A scripted handler that records what the runner asked of it. */
final class Scripted_Job_Handler implements Job_Handler {
	public int $handled = 0;

	public int $abandoned = 0;

	/** @var array<int, Job_Outcome|\Throwable> */
	public array $script = array();

	public function __construct( private readonly bool $safe ) {}

	public function type(): string {
		return 'scripted';
	}

	public function retry_safe(): bool {
		return $this->safe;
	}

	public function handle( Job $job, Job_Lease $lease ): Job_Outcome {
		++$this->handled;
		$next = array_shift( $this->script ) ?? Job_Outcome::succeeded();
		if ( $next instanceof \Throwable ) {
			throw $next;
		}

		return $next;
	}

	public function abandoned( Job $job ): void {
		++$this->abandoned;
	}
}

/** Proves jobs are stored once, held by one worker, and never re-run unsafely. */
final class Job_Queue_Test extends Test_Case {
	private Job_Test_Clock $clock;

	private Job_Repository $jobs;

	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Schema_Manager::table( 'jobs' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
		$this->clock = new Job_Test_Clock();
		$this->jobs  = new Job_Repository();
	}

	public function test_the_same_work_is_queued_once_while_active(): void {
		$queue = new Job_Queue( $this->jobs, $this->clock );
		$first = $queue->enqueue( 'scripted', 'campaign', 'campaign-one', array( 'reason' => 'scheduled' ) );
		$again = $queue->enqueue( 'scripted', 'campaign', 'campaign-one' );
		$other = $queue->enqueue( 'scripted', 'campaign', 'campaign-two' );

		self::assertNotNull( $first );
		self::assertSame( $first->id(), $again?->id() );
		self::assertNotSame( $first->id(), $other?->id() );
		self::assertSame( array( 'reason' => 'scheduled' ), $this->jobs->get( $first->id() )?->payload() );

		$this->runner( new Scripted_Job_Handler( true ) )->run( 10 );
		$requeued = $queue->enqueue( 'scripted', 'campaign', 'campaign-one' );
		self::assertNotSame( $first->id(), $requeued?->id(), 'Finished work can be queued again.' );
	}

	public function test_payloads_never_carry_credentials(): void {
		foreach ( array( 'api_key', 'token', 'webhook_secret', 'password' ) as $key ) {
			try {
				Job::create( 'job-one', 'scripted', 'campaign', 'campaign-one', array( $key => 'x' ), 3, $this->clock->now(), $this->clock->now() );
				self::fail( "A payload key {$key} was accepted." );
			} catch ( \InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	public function test_two_workers_racing_for_one_job_cannot_both_win(): void {
		$job  = $this->queue_job();
		$take = new \ReflectionMethod( Job_Repository::class, 'take' );
		$now  = Schema_Manager::is_current() ? str_replace( array( 'T', 'Z' ), array( ' ', '' ), $this->clock->now() ) : '';
		$due  = "state = 'queued' AND run_after <= %s AND attempts < max_attempts";

		// Both selected the row as a candidate; only one conditional update lands.
		self::assertTrue( $take->invoke( $this->jobs, $job->id(), $due, $now, 'worker-a', $now, true ) );
		self::assertFalse( $take->invoke( $this->jobs, $job->id(), $due, $now, 'worker-b', $now, true ) );
		self::assertSame( 'worker-a', $this->jobs->get( $job->id() )?->lease_owner() );
		self::assertSame( array(), $this->claim( 'worker-b' ), 'A held job is not claimable.' );
	}

	public function test_an_expired_lease_moves_to_another_worker_and_locks_out_the_first(): void {
		$job = $this->queue_job();
		self::assertCount( 1, $this->claim( 'worker-a' ) );

		$this->clock->now += Job_Runner::LEASE_SECONDS + 1;
		$claims = $this->claim( 'worker-b' );

		self::assertCount( 1, $claims );
		self::assertTrue( $claims[0]->taken_over );
		self::assertSame( 2, $claims[0]->job->attempts() );
		self::assertFalse( $this->jobs->succeed( $job->id(), 'worker-a', $this->clock->now() ), 'The stopped worker can no longer finish it.' );
		self::assertTrue( $this->jobs->succeed( $job->id(), 'worker-b', $this->clock->now() ) );
	}

	public function test_a_heartbeat_keeps_the_lease(): void {
		$job = $this->queue_job();
		$this->claim( 'worker-a' );
		$this->clock->now += Job_Runner::LEASE_SECONDS - 10;
		self::assertTrue( $this->jobs->heartbeat( $job->id(), 'worker-a', Job_Time::after( $this->clock->now(), Job_Runner::LEASE_SECONDS ), $this->clock->now() ) );
		self::assertFalse( $this->jobs->heartbeat( $job->id(), 'worker-b', Job_Time::after( $this->clock->now(), Job_Runner::LEASE_SECONDS ), $this->clock->now() ), 'Only the owner extends it.' );

		$this->clock->now += 30;
		self::assertSame( array(), $this->claim( 'worker-b' ) );
	}

	public function test_success_releases_the_job(): void {
		$job     = $this->queue_job();
		$handler = new Scripted_Job_Handler( false );

		self::assertSame( 1, $this->runner( $handler )->run( 10 )['succeeded'] );
		$stored = $this->jobs->get( $job->id() );
		self::assertSame( Job_State::SUCCEEDED, $stored?->state() );
		self::assertNull( $stored->lease_owner() );
		self::assertNull( $stored->dedupe_key() );
	}

	public function test_retries_back_off_and_end_dead_with_the_last_error(): void {
		$job             = $this->queue_job( 3 );
		$handler         = new Scripted_Job_Handler( true );
		$handler->script = array( Job_Outcome::retry( 'provider_busy' ), Job_Outcome::retry( 'provider_busy' ), Job_Outcome::retry( 'provider_down' ) );
		$runner          = $this->runner( $handler );

		self::assertSame( 1, $runner->run( 10 )['retried'] );
		self::assertSame( Job_Time::after( $this->clock->now(), 60 ), $this->jobs->get( $job->id() )?->run_after() );
		self::assertSame( 0, $runner->run( 10 )['claimed'], 'Not due before its backoff.' );

		$this->clock->now += 60;
		$runner->run( 10 );
		self::assertSame( Job_Time::after( $this->clock->now(), 120 ), $this->jobs->get( $job->id() )?->run_after() );

		$this->clock->now += 120;
		self::assertSame( 1, $runner->run( 10 )['dead'] );
		$stored = $this->jobs->get( $job->id() );
		self::assertSame( array( Job_State::DEAD, 'provider_down', 3 ), array( $stored?->state(), $stored?->last_error(), $stored?->attempts() ) );
	}

	public function test_a_retry_safe_handler_that_throws_is_retried_without_storing_the_message(): void {
		$job             = $this->queue_job();
		$handler         = new Scripted_Job_Handler( true );
		$handler->script = array( new \RuntimeException( 'secret api_key=abc in a provider response' ) );

		self::assertSame( 1, $this->runner( $handler )->run( 10 )['retried'] );
		$stored = $this->jobs->get( $job->id() );
		self::assertSame( 'handler_exception', $stored?->last_error() );
		self::assertStringNotContainsString( 'secret', (string) wp_json_encode( $stored->to_array() ) );
	}

	public function test_a_reported_failure_is_permanent_and_an_unknown_type_is_dead(): void {
		$failing         = $this->queue_job();
		$handler         = new Scripted_Job_Handler( true );
		$handler->script = array( Job_Outcome::failed( 'campaign_missing' ) );
		$this->runner( $handler )->run( 10 );
		self::assertSame( Job_State::FAILED, $this->jobs->get( $failing->id() )?->state() );

		$orphan = ( new Job_Queue( $this->jobs, $this->clock ) )->enqueue( 'retired_type', 'campaign', 'campaign-one' );
		$this->runner( $handler )->run( 10 );
		self::assertSame( array( Job_State::DEAD, 'no_handler' ), array( $this->jobs->get( (string) $orphan?->id() )?->state(), $this->jobs->get( (string) $orphan?->id() )?->last_error() ) );
	}

	public function test_a_crashed_worker_does_not_get_an_unsafe_job_run_twice(): void {
		$job     = $this->queue_job();
		$handler = new Scripted_Job_Handler( false );
		// Worker A claims the job and stops before recording anything.
		$this->claim( 'worker-a' );
		$this->clock->now += Job_Runner::LEASE_SECONDS + 1;

		$report = $this->runner( $handler )->run( 10 );

		self::assertSame( 0, $handler->handled, 'The unsafe handler is never run on the taken-over job.' );
		self::assertSame( 1, $handler->abandoned );
		self::assertSame( 1, $report['dead'] );
		self::assertSame( array( Job_State::DEAD, 'abandoned' ), array( $this->jobs->get( $job->id() )?->state(), $this->jobs->get( $job->id() )?->last_error() ) );
	}

	public function test_a_crashed_worker_s_retry_safe_job_runs_again(): void {
		$job     = $this->queue_job();
		$handler = new Scripted_Job_Handler( true );
		$this->claim( 'worker-a' );
		$this->clock->now += Job_Runner::LEASE_SECONDS + 1;

		$this->runner( $handler )->run( 10 );

		self::assertSame( 1, $handler->handled );
		self::assertSame( 0, $handler->abandoned );
		self::assertSame( Job_State::SUCCEEDED, $this->jobs->get( $job->id() )?->state() );
	}

	public function test_an_unsafe_handler_that_throws_is_abandoned_not_retried(): void {
		$job             = $this->queue_job();
		$handler         = new Scripted_Job_Handler( false );
		$handler->script = array( new \RuntimeException( 'timeout after the provider accepted' ) );

		$this->runner( $handler )->run( 10 );
		$this->clock->now += 3600;
		$this->runner( $handler )->run( 10 );

		self::assertSame( 1, $handler->handled );
		self::assertSame( 1, $handler->abandoned );
		self::assertSame( Job_State::DEAD, $this->jobs->get( $job->id() )?->state() );
	}

	public function test_a_crash_on_the_last_attempt_ends_the_job(): void {
		$job     = $this->queue_job( 1 );
		$handler = new Scripted_Job_Handler( true );
		$this->claim( 'worker-a' );
		$this->clock->now += Job_Runner::LEASE_SECONDS + 1;

		$this->runner( $handler )->run( 10 );

		self::assertSame( 0, $handler->handled, 'No attempts were left for another run.' );
		self::assertSame( 1, $handler->abandoned );
		self::assertSame( array( Job_State::DEAD, 'attempts_exhausted' ), array( $this->jobs->get( $job->id() )?->state(), $this->jobs->get( $job->id() )?->last_error() ) );
	}

	public function test_stalled_work_and_purging_read_stored_state(): void {
		$overdue = $this->queue_job();
		$held    = ( new Job_Queue( $this->jobs, $this->clock ) )->enqueue( 'scripted', 'campaign', 'campaign-held' );
		$this->clock->now += 600;
		$this->claim( 'worker-a', 1 );
		$this->clock->now += Job_Runner::LEASE_SECONDS + 1;

		$stalled = $this->jobs->stalled( Job_Time::after( $this->clock->now(), -300 ), $this->clock->now() );
		self::assertSame( 1, $stalled['overdue'] );
		self::assertSame( 1, $stalled['expired_leases'] );
		self::assertNotNull( $stalled['oldest_overdue'] );
		self::assertSame(
			array(
				'claimed' => 1,
				'queued'  => 1,
			),
			$this->sorted( $this->jobs->counts_by_state() )
		);

		$this->runner( new Scripted_Job_Handler( true ) )->run( 10 );
		self::assertSame( 0, $this->jobs->purge_succeeded( Job_Time::after( $this->clock->now(), -60 ), 10 ), 'Recent successes stay.' );
		self::assertSame( 2, $this->jobs->purge_succeeded( Job_Time::after( $this->clock->now(), 60 ), 10 ) );
		self::assertNull( $this->jobs->get( $overdue->id() ) );
		self::assertNull( $this->jobs->get( (string) $held?->id() ) );
	}

	private function queue_job( int $max_attempts = 5 ): Job {
		$job = ( new Job_Queue( $this->jobs, $this->clock ) )->enqueue( 'scripted', 'campaign', 'campaign-one', array(), 0, $max_attempts );
		self::assertNotNull( $job );

		return $job;
	}

	/** @return array<int, \CampaignBridge\Domain\Job\Job_Claim> */
	private function claim( string $owner, int $limit = 10 ): array {
		return $this->jobs->claim( $owner, $this->clock->now(), Job_Time::after( $this->clock->now(), Job_Runner::LEASE_SECONDS ), $limit );
	}

	private function runner( Job_Handler $handler ): Job_Runner {
		return new Job_Runner( $this->jobs, array( $handler ), $this->clock, 'worker-test' );
	}

	/**
	 * @param array<string, int> $counts Counts by state.
	 * @return array<string, int>
	 */
	private function sorted( array $counts ): array {
		ksort( $counts );

		return $counts;
	}
}
