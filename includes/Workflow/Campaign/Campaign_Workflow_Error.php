<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Stable provider-neutral campaign workflow error.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Error codes are adapter-safe and never expose storage or provider details. */
final class Campaign_Workflow_Error {
	public const NOT_FOUND            = 'not_found';
	public const INVALID_STATE        = 'invalid_state';
	public const CONFLICT             = 'conflict';
	public const INVALID_INPUT        = 'invalid_input';
	public const VALIDATION_FAILED    = 'validation_failed';
	public const MISSING_SNAPSHOT     = 'missing_snapshot';
	public const APPROVAL_NOT_ALLOWED = 'approval_not_allowed';
	public const FORBIDDEN            = 'forbidden';
	public const PERSISTENCE_FAILED   = 'persistence_failed';
	public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';

	/** A prior remote mutation has an unknown outcome and must be reconciled first. */
	public const RECONCILIATION_REQUIRED = 'reconciliation_required';

	/** The provider definitely refused or could not complete the operation. */
	public const PROVIDER_FAILED = 'provider_failed';

	/** A durable per-campaign operation quota is exhausted. */
	public const RATE_LIMITED = 'rate_limited';

	/** Reconciliation reasons: why a reconcile could not settle the campaign. */
	public const REASON_IN_PROGRESS      = 'in_progress';
	public const REASON_MISSING          = 'missing';
	public const REASON_UNTRACKED        = 'untracked';
	public const REASON_INCONCLUSIVE     = 'inconclusive';
	public const REASON_CONTRADICTION    = 'contradiction';
	public const REASON_DUPLICATE_DRAFTS = 'duplicate_drafts';

	private const REASONS = array( self::REASON_IN_PROGRESS, self::REASON_MISSING, self::REASON_UNTRACKED, self::REASON_INCONCLUSIVE, self::REASON_CONTRADICTION, self::REASON_DUPLICATE_DRAFTS );

	/**
	 * Build an error.
	 *
	 * @param string      $code        Stable error code.
	 * @param string      $message     Operator-safe message.
	 * @param string|null $reason      Stable reconciliation reason, when the code is reconciliation_required.
	 * @param int|null    $retry_after Seconds until reconciling again can settle it, when known.
	 * @throws \InvalidArgumentException When the code, message, reason, or delay is not recognized.
	 */
	public function __construct(
		private readonly string $code,
		private readonly string $message,
		private readonly ?string $reason = null,
		private readonly ?int $retry_after = null
	) {
		if (
			! in_array( $code, self::codes(), true ) || '' === $message || 512 < strlen( $message )
			|| ( null !== $reason && ! in_array( $reason, self::REASONS, true ) )
			|| ( null !== $retry_after && 0 > $retry_after )
		) {
			throw new \InvalidArgumentException( 'Campaign workflow error is invalid.' );
		}
	}

	/**
	 * Every reconciliation reason the contract can report.
	 *
	 * @return array<int, string>
	 */
	public static function reasons(): array {
		return self::REASONS;
	}

	/** Why reconciliation could not settle the campaign, when this is such a refusal. */
	public function reason(): ?string {
		return $this->reason;
	}

	/** Seconds until reconciling again can settle it, when known. */
	public function retry_after(): ?int {
		return $this->retry_after;
	}

	public function code(): string {
		return $this->code;
	}

	public function message(): string {
		return $this->message;
	}

	/** @return array<int, string> */
	private static function codes(): array {
		return array(
			self::NOT_FOUND,
			self::INVALID_STATE,
			self::CONFLICT,
			self::INVALID_INPUT,
			self::VALIDATION_FAILED,
			self::MISSING_SNAPSHOT,
			self::APPROVAL_NOT_ALLOWED,
			self::FORBIDDEN,
			self::PERSISTENCE_FAILED,
			self::IDEMPOTENCY_CONFLICT,
			self::RECONCILIATION_REQUIRED,
			self::PROVIDER_FAILED,
			self::RATE_LIMITED,
		);
	}
}
