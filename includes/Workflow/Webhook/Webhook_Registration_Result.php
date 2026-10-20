<?php
/**
 * Outcome of enabling, rotating, or disabling provider webhooks.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Webhook;

use CampaignBridge\Domain\Campaign\Provider_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many audiences changed, and the first normalized error, if any.
 *
 * Audiences are handled one at a time, so a failure on one does not undo
 * the others; the operator can repeat the action to finish.
 */
final class Webhook_Registration_Result {
	public const NOT_CONNECTED = 'not_connected';

	/**
	 * Build the result.
	 *
	 * @param int                 $changed  Audiences whose webhook was created or removed.
	 * @param int                 $failed   Audiences that could not be changed.
	 * @param Provider_Error|null $error    The first provider error.
	 * @param string|null         $reason   Why nothing could be attempted.
	 */
	private function __construct(
		private readonly int $changed,
		private readonly int $failed,
		private readonly ?Provider_Error $error,
		private readonly ?string $reason
	) {}

	/**
	 * The outcome of handling every audience.
	 *
	 * @param int                 $changed Audiences whose webhook was created or removed.
	 * @param int                 $failed  Audiences that could not be changed.
	 * @param Provider_Error|null $error   The first provider error.
	 */
	public static function completed( int $changed, int $failed, ?Provider_Error $error ): self {
		return new self( $changed, $failed, $error, null );
	}

	/**
	 * Nothing could be attempted because the provider is not connected.
	 */
	public static function not_connected(): self {
		return new self( 0, 0, null, self::NOT_CONNECTED );
	}

	/**
	 * Nothing could be attempted because the provider refused the first request.
	 *
	 * @param Provider_Error $error Normalized provider error.
	 */
	public static function failed( Provider_Error $error ): self {
		return new self( 0, 1, $error, null );
	}

	/**
	 * Whether every audience is in the requested state.
	 */
	public function is_success(): bool {
		return null === $this->reason && 0 === $this->failed;
	}

	/**
	 * Audiences whose webhook was created or removed.
	 */
	public function changed(): int {
		return $this->changed;
	}

	/**
	 * Audiences that could not be changed.
	 */
	public function failed_count(): int {
		return $this->failed;
	}

	/**
	 * The first provider error.
	 */
	public function error(): ?Provider_Error {
		return $this->error;
	}

	/**
	 * Why nothing could be attempted, or null.
	 */
	public function reason(): ?string {
		return $this->reason;
	}
}
