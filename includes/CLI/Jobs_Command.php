<?php
/**
 * Job inspection and recovery commands.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\CLI;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Services\Operations\Operations_Factory;
use CampaignBridge\Workflow\Operations\Operation_Refused;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Inspect and recover durable jobs as an authorized site manager. */
final class Jobs_Command extends Command {
	/**
	 * List a bounded page of jobs as JSON, without payloads or credentials.
	 *
	 * ## OPTIONS
	 *
	 * [--state=<state>]
	 * : Filter by queued, claimed, succeeded, failed, or dead.
	 *
	 * [--limit=<limit>]
	 * : Page size from 1 to 100. Default: 50.
	 *
	 * [--offset=<offset>]
	 * : Non-negative number of records to skip. Default: 0.
	 *
	 * @subcommand list
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function listing( array $args, array $assoc_args ): void {
		$this->respond(
			function () use ( $assoc_args ): array {
				$actor  = $this->actor( Capabilities::MANAGE );
				$state  = isset( $assoc_args['state'] ) ? (string) $assoc_args['state'] : null;
				$limit  = $this->number( $assoc_args['limit'] ?? '50' );
				$offset = $this->number( $assoc_args['offset'] ?? '0' );

				return array_map( $this->row( ... ), Operations_Factory::jobs()->listing( $actor, $state, $limit, $offset ) );
			}
		);
	}

	/**
	 * Show one job's operational metadata as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID.
	 *
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function show( array $args, array $assoc_args ): void {
		$this->respond(
			function () use ( $args ): array {
				$actor = $this->actor( Capabilities::MANAGE );

				return $this->row( Operations_Factory::jobs()->show( $actor, $this->identifier( $args[0] ?? '' ) ) );
			}
		);
	}

	/**
	 * Requeue a failed or dead job only when its registered handler is retry-safe.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID. This queues work for the next cron tick; it does not execute it.
	 *
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function retry( array $args, array $assoc_args ): void {
		$this->respond(
			function () use ( $args ): array {
				$actor = $this->actor( Capabilities::MANAGE );
				$id    = $this->identifier( $args[0] ?? '' );
				Operations_Factory::jobs()->retry( $actor, $id );

				return array(
					'id'    => $id,
					'state' => 'queued',
				);
			}
		);
	}

	/**
	 * Cancel queued work without interrupting a claimed job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID.
	 *
	 * [--yes]
	 * : Confirm cancellation. Records failed with cancelled_by_operator.
	 *
	 * @param array<int, string>         $args Positional arguments.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function cancel( array $args, array $assoc_args ): void {
		$this->respond(
			function () use ( $args, $assoc_args ): array {
				$actor = $this->actor( Capabilities::MANAGE );
				if ( true !== ( $assoc_args['yes'] ?? false ) ) {
					throw new Operation_Refused( 'Supply --yes to confirm cancelling this queued job.' );
				}
				$id = $this->identifier( $args[0] ?? '' );
				Operations_Factory::jobs()->cancel( $actor, $id );

				return array(
					'id'         => $id,
					'state'      => 'failed',
					'last_error' => 'cancelled_by_operator',
				);
			}
		);
	}

	/**
	 * Omit payloads, arbitrary target values, dedupe keys, and lease-owner identities.
	 *
	 * @param Job $job Stored job.
	 * @return array<string, int|string|null>
	 */
	private function row( Job $job ): array {
		return array(
			'campaign_id'      => 'campaign' === $job->target_type() && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $job->target_id() ) ? $job->target_id() : null,
			'id'               => $job->id(),
			'type'             => $job->type(),
			'target_type'      => $job->target_type(),
			'state'            => $job->state(),
			'attempts'         => $job->attempts(),
			'max_attempts'     => $job->max_attempts(),
			'run_after'        => $job->run_after(),
			'lease_expires_at' => $job->lease_expires_at(),
			'last_error'       => $job->last_error(),
			'created_at'       => $job->created_at(),
			'updated_at'       => $job->updated_at(),
		);
	}

	/**
	 * Validate pagination without coercing malformed input.
	 *
	 * @param string|bool $value CLI option.
	 * @throws Operation_Refused On malformed numbers.
	 */
	private function number( string|bool $value ): int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(0|[1-9][0-9]{0,8})$/', $value ) ) {
			throw new Operation_Refused( 'Pagination values must be non-negative integers.' );
		}

		return (int) $value;
	}
}
