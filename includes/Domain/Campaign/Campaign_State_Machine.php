<?php
/**
 * Campaign state transition validation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guards campaign state transitions.
 *
 * The lifecycle is:
 * draft → ready_for_review → approved → provider_draft → scheduled|sending → sent
 *
 * Recovery paths allow retry from failed or unknown states back to
 * provider_draft. Cancellation is permitted from any non-terminal state.
 */
final class Campaign_State_Machine {
	/**
	 * Allowed transitions keyed by source state.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const TRANSITIONS = array(
		Campaign_State::DRAFT            => array( Campaign_State::READY_FOR_REVIEW, Campaign_State::CANCELLED, Campaign_State::FAILED ),
		Campaign_State::READY_FOR_REVIEW => array( Campaign_State::APPROVED, Campaign_State::DRAFT, Campaign_State::CANCELLED, Campaign_State::FAILED ),
		Campaign_State::APPROVED         => array( Campaign_State::PROVIDER_DRAFT, Campaign_State::READY_FOR_REVIEW, Campaign_State::CANCELLED, Campaign_State::FAILED ),
		Campaign_State::PROVIDER_DRAFT   => array( Campaign_State::SCHEDULED, Campaign_State::SENDING, Campaign_State::APPROVED, Campaign_State::CANCELLED, Campaign_State::FAILED, Campaign_State::UNKNOWN ),
		Campaign_State::SCHEDULED        => array( Campaign_State::SENDING, Campaign_State::CANCELLED, Campaign_State::FAILED, Campaign_State::UNKNOWN ),
		Campaign_State::SENDING          => array( Campaign_State::SENT, Campaign_State::FAILED, Campaign_State::UNKNOWN ),
		Campaign_State::SENT             => array(),
		Campaign_State::FAILED           => array( Campaign_State::PROVIDER_DRAFT, Campaign_State::DRAFT, Campaign_State::CANCELLED ),
		Campaign_State::CANCELLED        => array(),
		Campaign_State::UNKNOWN          => array( Campaign_State::PROVIDER_DRAFT, Campaign_State::FAILED, Campaign_State::CANCELLED ),
	);

	/**
	 * Determine whether a transition is allowed.
	 *
	 * @param string $from Current state.
	 * @param string $to   Target state.
	 */
	public static function can_transition( string $from, string $to ): bool {
		if ( ! Campaign_State::is_valid( $from ) || ! Campaign_State::is_valid( $to ) ) {
			return false;
		}

		return in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
	}

	/**
	 * Get all states reachable from the given state.
	 *
	 * @param string $from Current state.
	 * @return array<int, string>
	 */
	public static function allowed_transitions( string $from ): array {
		if ( ! Campaign_State::is_valid( $from ) ) {
			return array();
		}

		return self::TRANSITIONS[ $from ] ?? array();
	}

	/**
	 * Validate a transition, throwing on violation.
	 *
	 * @param string $from Current state.
	 * @param string $to   Target state.
	 *
	 * @throws \InvalidArgumentException When the transition is not allowed.
	 */
	public static function assert_transition( string $from, string $to ): void {
		if ( ! self::can_transition( $from, $to ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Campaign state transition from %s to %s is not allowed.', $from, $to )
			);
		}
	}
}
