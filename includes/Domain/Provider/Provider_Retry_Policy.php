<?php
/**
 * Which provider requests may be repeated after a failure.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies every provider request by what repeating it could do.
 *
 * - Repeatable: reads, discovery, connection checks, reconciliation, and an
 *   exact update of an unsent draft. Repeating one changes nothing that was
 *   not already intended, so a transient failure, a 5xx, or a 429 may be
 *   retried with backoff.
 * - Create once: draft creation. The request itself is never replayed; a new
 *   attempt goes through the campaign's idempotency key, which first looks
 *   for a draft an unconfirmed attempt may have made.
 * - Never: anything that reaches recipients or changes delivery — test,
 *   schedule, unschedule, send, cancel. Each is attempted exactly once; an
 *   ambiguous outcome makes the campaign `unknown` until reconciliation reads
 *   what the provider did.
 *
 * Anything not listed is treated as never, so a new operation cannot become
 * retryable by omission.
 */
final class Provider_Retry_Policy {
	public const REPEATABLE  = 'repeatable';
	public const CREATE_ONCE = 'create_once';
	public const NEVER       = 'never';

	/** Reading a draft, or re-asserting its exact settings or content before delivery. */
	public const DRAFT_UPDATE = 'draft_update';

	private const CLASSES = array(
		Provider_Operation::VERIFY_CONNECTION          => self::REPEATABLE,
		Provider_Operation::DISCOVER_AUDIENCES         => self::REPEATABLE,
		Provider_Operation::DISCOVER_MERGE_FIELDS      => self::REPEATABLE,
		Provider_Operation::DISCOVER_SEGMENTS          => self::REPEATABLE,
		Provider_Operation::DISCOVER_SENDERS           => self::REPEATABLE,
		Provider_Operation::DISCOVER_TEMPLATE_SECTIONS => self::REPEATABLE,
		Provider_Operation::EXPORT                     => self::REPEATABLE,
		Provider_Operation::RECONCILE                  => self::REPEATABLE,
		Provider_Operation::REPORTS                    => self::REPEATABLE,
		self::DRAFT_UPDATE                             => self::REPEATABLE,
		Provider_Operation::CREATE_DRAFT               => self::CREATE_ONCE,
		Provider_Operation::SEND_TEST                  => self::NEVER,
		Provider_Operation::SCHEDULE                   => self::NEVER,
		Provider_Operation::UNSCHEDULE                 => self::NEVER,
		Provider_Operation::SEND                       => self::NEVER,
		Provider_Operation::CANCEL                     => self::NEVER,
	);

	/**
	 * How a request for one operation may be repeated.
	 *
	 * @param string $operation A provider operation, or DRAFT_UPDATE.
	 * @return string REPEATABLE, CREATE_ONCE, or NEVER.
	 */
	public static function for( string $operation ): string {
		return self::CLASSES[ $operation ] ?? self::NEVER;
	}

	/**
	 * Whether the HTTP layer may send the same request again after a failure.
	 *
	 * @param string $operation A provider operation, or DRAFT_UPDATE.
	 */
	public static function may_replay( string $operation ): bool {
		return self::REPEATABLE === self::for( $operation );
	}

	/**
	 * Every request kind the policy classifies explicitly.
	 *
	 * @return array<int, string>
	 */
	public static function classified(): array {
		return array_keys( self::CLASSES );
	}
}
