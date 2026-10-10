<?php
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

	/**
	 * Build the action outcome.
	 *
	 * @param string              $status Outcome kind.
	 * @param Provider_Error|null $error  Normalized provider error, when there is one.
	 */
	private function __construct(
		private readonly string $status,
		private readonly ?Provider_Error $error
	) {}

	/**
	 * The provider accepted the action.
	 */
	public static function accepted(): self {
		return new self( self::ACCEPTED, null );
	}

	/**
	 * Classify a failed action by what the error proves.
	 *
	 * @param Provider_Error $error Normalized provider error.
	 */
	public static function from_error( Provider_Error $error ): self {
		return new self( Provider_Error_Category::may_have_applied( $error->category() ) ? self::AMBIGUOUS : self::FAILED, $error );
	}

	/**
	 * The outcome's status.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * The outcome's error.
	 */
	public function error(): ?Provider_Error {
		return $this->error;
	}
}
