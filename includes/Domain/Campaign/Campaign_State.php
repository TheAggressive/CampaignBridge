<?php
/**
 * Campaign lifecycle state.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable campaign lifecycle states.
 *
 * The lifecycle is explicit and guarded:
 * draft → ready_for_review → approved → provider_draft → scheduled|sending → sent
 *
 * Failure and recovery states: failed, cancelled, unknown.
 * `unknown` prevents a timeout after an irreversible provider request from
 * being mistaken for a safe retry.
 */
final class Campaign_State {
	public const DRAFT            = 'draft';
	public const READY_FOR_REVIEW = 'ready_for_review';
	public const APPROVED         = 'approved';
	public const PROVIDER_DRAFT   = 'provider_draft';
	public const SCHEDULED        = 'scheduled';
	public const SENDING          = 'sending';
	public const SENT             = 'sent';
	public const FAILED           = 'failed';
	public const CANCELLED        = 'cancelled';
	public const UNKNOWN          = 'unknown';

	/**
	 * All valid states.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::DRAFT,
			self::READY_FOR_REVIEW,
			self::APPROVED,
			self::PROVIDER_DRAFT,
			self::SCHEDULED,
			self::SENDING,
			self::SENT,
			self::FAILED,
			self::CANCELLED,
			self::UNKNOWN,
		);
	}

	/**
	 * Determine whether a string is a valid campaign state.
	 *
	 * @param string $state State to check.
	 */
	public static function is_valid( string $state ): bool {
		return in_array( $state, self::all(), true );
	}

	/**
	 * Determine whether the campaign is in a terminal (non-transitionable) state.
	 *
	 * @param string $state State to check.
	 */
	public static function is_terminal( string $state ): bool {
		return in_array( $state, array( self::SENT, self::CANCELLED ), true );
	}

	/**
	 * Determine whether the state represents a failure condition.
	 *
	 * @param string $state State to check.
	 */
	public static function is_failure( string $state ): bool {
		return in_array( $state, array( self::FAILED, self::UNKNOWN ), true );
	}
}
