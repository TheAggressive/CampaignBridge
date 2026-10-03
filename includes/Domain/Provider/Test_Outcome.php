<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
/**
 * Normalized outcome of one test delivery.
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
 * What is known after a test send, never more.
 *
 * - `sent`: the provider accepted the test for delivery.
 * - `failed`: the provider definitely refused it; nothing was sent.
 * - `ambiguous`: the test may or may not have been sent. A test is not
 *   idempotent, so this is reported and never retried blindly.
 */
final class Test_Outcome {
	public const SENT      = 'sent';
	public const FAILED    = 'failed';
	public const AMBIGUOUS = 'ambiguous';

	private function __construct(
		private readonly string $status,
		private readonly ?Provider_Error $error
	) {}

	public static function sent(): self {
		return new self( self::SENT, null );
	}

	/** Classify a failed test send by what the error proves. */
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
