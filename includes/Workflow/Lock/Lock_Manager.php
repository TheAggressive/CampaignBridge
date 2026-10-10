<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures and the class contract document these methods.
/**
 * Hold a lock around one piece of work.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Lock;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Lock\Lock_Source;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Job\Job_Time;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializes work on one campaign, or one remote operation, across requests
 * and background workers.
 *
 * The lock is held for the whole operation, provider calls included, and
 * released in `finally`. When someone else holds it, the work does not run
 * and the caller gets a `locked` refusal with the seconds until the lock
 * expires, so nothing is silently skipped and no second provider call is
 * made. A lock left behind by a process that stopped expires after
 * TTL_SECONDS and the next caller takes it over; the takeover is audited.
 */
final class Lock_Manager {
	public const TTL_SECONDS = 300;

	/**
	 * This request's or worker's lock identity.
	 *
	 * @var string
	 */
	private readonly string $owner;

	public function __construct(
		private readonly Lock_Source $locks,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Clock $clock,
		?string $owner = null
	) {
		$this->owner = $owner ?? 'lock-' . bin2hex( random_bytes( 8 ) );
	}

	/**
	 * Run `$work` while holding the campaign's lock.
	 *
	 * @template T
	 * @param callable(): T                       $work Work that may contact the provider.
	 * @param callable(Campaign_Workflow_Error): T $busy Builds the refusal when the lock is held.
	 * @return T
	 */
	public function campaign( string $campaign_id, string $purpose, callable $work, callable $busy ): mixed {
		return $this->hold( 'campaign:' . $campaign_id, 'campaign', $campaign_id, $purpose, $work, $busy );
	}

	/**
	 * Run `$work` while holding the lock for one provider operation on one account.
	 *
	 * @template T
	 * @param callable(): T                       $work Work that contacts the provider.
	 * @param callable(Campaign_Workflow_Error): T $busy Builds the refusal when the lock is held.
	 * @return T
	 */
	public function remote( string $provider, string $account, string $operation, callable $work, callable $busy ): mixed {
		$purpose = substr( (string) strtok( $operation, "\0" ), 0, 64 );

		return $this->hold( 'remote:' . hash( 'sha256', $provider . "\0" . $account . "\0" . $operation ), 'provider', $provider, $purpose, $work, $busy );
	}

	/**
	 * @template T
	 * @param callable(): T                       $work
	 * @param callable(Campaign_Workflow_Error): T $busy
	 * @return T
	 */
	private function hold( string $name, string $target_type, string $target_id, string $purpose, callable $work, callable $busy ): mixed {
		$now      = $this->clock->now();
		$acquired = $this->locks->acquire( $name, $this->owner, $purpose, Job_Time::after( $now, self::TTL_SECONDS ), $now );
		if ( ! $acquired->acquired ) {
			$wait = null === $acquired->held_until ? self::TTL_SECONDS : max( 1, (int) strtotime( $acquired->held_until ) - (int) strtotime( $now ) );

			return $busy(
				new Campaign_Workflow_Error(
					Campaign_Workflow_Error::LOCKED,
					'Another request is already working on this. Nothing was done; try again shortly.',
					null,
					$wait
				)
			);
		}
		if ( null !== $acquired->interrupted_purpose ) {
			$this->audit_takeover( $target_type, $target_id, $purpose, $acquired->interrupted_purpose, $now );
		}

		try {
			return $work();
		} finally {
			$this->locks->release( $name, $this->owner );
		}
	}

	private function audit_takeover( string $target_type, string $target_id, string $purpose, string $interrupted, string $now ): void {
		try {
			$this->audits->add(
				Audit_Event::from_array(
					array(
						'schema_version' => Audit_Event::SCHEMA_VERSION,
						'id'             => 'audit-' . bin2hex( random_bytes( 16 ) ),
						'actor_user_id'  => null,
						'action'         => $target_type . '_lock_takeover',
						'target_type'    => $target_type,
						'target_id'      => $target_id,
						'result'         => 'success',
						'context'        => array(
							'operation'             => $purpose,
							'interrupted_operation' => $interrupted,
						),
						'created_at'     => $now,
					)
				)
			);
		} catch ( \InvalidArgumentException ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A malformed audit record must not block recovery of the lock.
		}
	}
}
