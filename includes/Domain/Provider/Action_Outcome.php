<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
/**
 * Normalized outcome of one non-idempotent remote action.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What is known after a remote action such as a test send, schedule, or
 * unschedule, never more.
 *
 * - `accepted`: the provider accepted the action.
 * - `failed`: the provider definitely refused it; nothing changed remotely.
 * - `ambiguous`: the action may or may not have taken effect. It is
 *   reported and never retried blindly.
 */
final class Action_Outcome {
	public const ACCEPTED  = 'accepted';
	public const FAILED    = 'failed';
	public const AMBIGUOUS = 'ambiguous';

	private function __construct(
		private readonly string $status,
		private readonly ?Provider_Error $error
	) {}

	public static function accepted(): self {
		return new self( self::ACCEPTED, null );
	}

	/** Classify a failed action by what the error proves. */
	public static function from_error( Provider_Error $error ): self {
		return new self( Provider_Error_Category::may_have_applied( $error->category() ) ? self::AMBIGUOUS : self::FAILED, $error );
	}

	public function status(): string {
		return $this->status;
	}

	public function error(): ?Provider_Error {
		return $this->error;
	}
}
