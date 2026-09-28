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

	public function __construct(
		private readonly string $code,
		private readonly string $message
	) {
		if ( ! in_array( $code, self::codes(), true ) || '' === $message || 512 < strlen( $message ) ) {
			throw new \InvalidArgumentException( 'Campaign workflow error is invalid.' );
		}
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
		);
	}
}
