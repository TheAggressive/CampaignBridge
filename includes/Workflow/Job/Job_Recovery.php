<?php
/**
 * Authorized, audited operator recovery of background work.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign_Transaction;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_Operations_Source;
use CampaignBridge\Domain\Job\Job_State;
use CampaignBridge\Workflow\Operations\Operation_Refused;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Id_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Recovery queues work; it never executes a handler or bypasses an active lease. */
final class Job_Recovery {
	/**
	 * Build recovery without performing work.
	 *
	 * @param Job_Operations_Source   $jobs Job storage.
	 * @param Audit_Event_Source      $audits Audit storage.
	 * @param Campaign_Transaction    $transaction Atomic write boundary.
	 * @param Campaign_Id_Generator   $ids Identifier generator.
	 * @param Campaign_Clock          $clock Current time.
	 * @param array<int, Job_Handler> $handlers Registered production handlers.
	 */
	public function __construct(
		private readonly Job_Operations_Source $jobs,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Transaction $transaction,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock,
		private readonly array $handlers
	) {}

	/**
	 * Read one authorized page.
	 *
	 * @param Campaign_Actor $actor Resolved authority.
	 * @param string|null    $state Optional state.
	 * @param int            $limit Page size.
	 * @param int            $offset Records to skip.
	 * @return array<int, Job>
	 * @throws Operation_Refused On refusal.
	 */
	public function listing( Campaign_Actor $actor, ?string $state, int $limit, int $offset ): array {
		$this->authorize( $actor );
		if ( $limit < 1 || $limit > 100 || $offset < 0 || ( null !== $state && ! in_array( $state, Job_State::all(), true ) ) ) {
			throw new Operation_Refused( 'Use a valid job state, a limit from 1 to 100, and a non-negative offset.' );
		}

		return $this->jobs->listing( $state, $limit, $offset );
	}

	/**
	 * Read one authorized job.
	 *
	 * @param Campaign_Actor $actor Resolved authority.
	 * @param string         $id Job ID.
	 * @throws Operation_Refused On refusal.
	 */
	public function show( Campaign_Actor $actor, string $id ): Job {
		$this->authorize( $actor );
		$job = $this->jobs->get( $id );
		if ( null === $job ) {
			throw new Operation_Refused( 'Job was not found.' );
		}

		return $job;
	}

	/**
	 * Requeue failed or dead work after checking the handler's retry declaration.
	 *
	 * @param Campaign_Actor $actor Resolved authority.
	 * @param string         $id Job ID.
	 * @throws Operation_Refused On refusal or failed persistence.
	 */
	public function retry( Campaign_Actor $actor, string $id ): void {
		$job = $this->show( $actor, $id );
		if ( ! in_array( $job->state(), array( Job_State::FAILED, Job_State::DEAD ), true ) ) {
			throw new Operation_Refused( 'Only failed or dead jobs can be retried. Claimed and queued jobs remain under worker control.' );
		}
		$safe = false;
		foreach ( $this->handlers as $handler ) {
			if ( $handler->type() === $job->type() ) {
				$safe = $handler->retry_safe();
				break;
			}
		}
		if ( ! $safe ) {
			throw new Operation_Refused( 'The registered handler does not declare this job safe to retry.' );
		}
		$this->change( $actor, $job, true );
	}

	/**
	 * Stop queued work without interrupting an executing worker.
	 *
	 * @param Campaign_Actor $actor Resolved authority.
	 * @param string         $id Job ID.
	 * @throws Operation_Refused On refusal or failed persistence.
	 */
	public function cancel( Campaign_Actor $actor, string $id ): void {
		$job = $this->show( $actor, $id );
		if ( Job_State::QUEUED !== $job->state() ) {
			throw new Operation_Refused( 'Only queued jobs can be cancelled. A claimed job must finish or expire through its worker.' );
		}
		$this->change( $actor, $job, false );
	}

	/**
	 * Commit recovery and its named actor's audit event together.
	 *
	 * @param Campaign_Actor $actor Resolved authority.
	 * @param Job            $job Observed job.
	 * @param bool           $retry Whether to requeue rather than cancel.
	 * @throws Operation_Refused On conflict or persistence failure.
	 */
	private function change( Campaign_Actor $actor, Job $job, bool $retry ): void {
		$now   = $this->clock->now();
		$event = Audit_Event::from_array(
			array(
				'schema_version' => Audit_Event::SCHEMA_VERSION,
				'id'             => $this->ids->generate( 'audit' ),
				'actor_user_id'  => $actor->user_id(),
				'action'         => $retry ? 'job_retry' : 'job_cancel',
				'target_type'    => 'job',
				'target_id'      => $job->id(),
				'result'         => 'success',
				'context'        => array(
					'from_state' => $job->state(),
					'to_state'   => $retry ? Job_State::QUEUED : Job_State::FAILED,
				),
				'created_at'     => $now,
			)
		);
		if ( ! $this->transaction->run( fn (): bool => ( $retry ? $this->jobs->requeue( $job, $now ) : $this->jobs->cancel_queued( $job, $now ) ) && $this->audits->add( $event ) ) ) {
			throw new Operation_Refused( 'Recovery could not be recorded. The job may have changed or another job already owns its work; inspect it again.' );
		}
	}

	/**
	 * Require site-wide campaign management for operational access.
	 *
	 * @param Campaign_Actor $actor Resolved authority.
	 * @throws Operation_Refused When authority is missing.
	 */
	private function authorize( Campaign_Actor $actor ): void {
		if ( ! $actor->can_manage_all() ) {
			throw new Operation_Refused( 'CampaignBridge management capability is required.' );
		}
	}
}
