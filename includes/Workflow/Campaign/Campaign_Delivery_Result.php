<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Outcome of a remote delivery operation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed schedule and unschedule result shared by REST and future adapters.
 *
 * A failure may still carry the remote reference and attempt: after a
 * partial or ambiguous outcome, callers need to know what already exists.
 */
final class Campaign_Delivery_Result implements Campaign_Remote_Result {
	private function __construct(
		private readonly ?Campaign $campaign,
		private readonly ?Remote_Campaign_Reference $reference,
		private readonly ?Delivery_Attempt $attempt,
		private readonly ?Campaign_Workflow_Error $error,
		private readonly ?Provider_Error $provider_error,
		private readonly bool $replay
	) {}

	public static function success( Campaign $campaign, Remote_Campaign_Reference $reference, ?Delivery_Attempt $attempt, bool $replay ): self {
		return new self( $campaign, $reference, $attempt, null, null, $replay );
	}

	public static function failure(
		Campaign_Workflow_Error $error,
		?Campaign $campaign = null,
		?Remote_Campaign_Reference $reference = null,
		?Delivery_Attempt $attempt = null,
		?Provider_Error $provider_error = null
	): self {
		return new self( $campaign, $reference, $attempt, $error, $provider_error, false );
	}

	public function is_success(): bool {
		return null === $this->error;
	}

	public function campaign(): ?Campaign {
		return $this->campaign;
	}

	public function reference(): ?Remote_Campaign_Reference {
		return $this->reference;
	}

	public function attempt(): ?Delivery_Attempt {
		return $this->attempt;
	}

	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}

	/** The normalized provider failure behind a provider_failed or unknown outcome. */
	public function provider_error(): ?Provider_Error {
		return $this->provider_error;
	}

	/** Whether a recorded outcome was returned instead of contacting the provider. */
	public function is_idempotent_replay(): bool {
		return $this->replay;
	}
}
