<?php
/**
 * Outcome of a remote draft handoff.
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
 * Typed handoff result shared by REST and future adapters.
 *
 * A failure may still carry the remote reference and attempt: after a
 * partial or ambiguous outcome, callers need to know what already exists.
 */
final class Campaign_Draft_Result implements Campaign_Remote_Result {
	/**
	 * Build the campaign draft result.
	 *
	 * @param Campaign|null                  $campaign       The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference      The campaign's remote reference.
	 * @param Delivery_Attempt|null          $attempt        The delivery attempt.
	 * @param Campaign_Workflow_Error|null   $error          Why the operation was refused or failed, when it was.
	 * @param Provider_Error|null            $provider_error The normalized provider error, when there is one.
	 * @param bool                           $replay         Whether this answers a repeated idempotency key.
	 */
	private function __construct(
		private readonly ?Campaign $campaign,
		private readonly ?Remote_Campaign_Reference $reference,
		private readonly ?Delivery_Attempt $attempt,
		private readonly ?Campaign_Workflow_Error $error,
		private readonly ?Provider_Error $provider_error,
		private readonly bool $replay
	) {}

	/**
	 * A draft handoff that completed, or replayed an earlier completion.
	 *
	 * @param Campaign                  $campaign  The campaign as read.
	 * @param Remote_Campaign_Reference $reference The campaign's remote reference.
	 * @param Delivery_Attempt|null     $attempt   The delivery attempt.
	 * @param bool                      $replay    Whether this answers a repeated idempotency key.
	 */
	public static function success( Campaign $campaign, Remote_Campaign_Reference $reference, ?Delivery_Attempt $attempt, bool $replay ): self {
		return new self( $campaign, $reference, $attempt, null, null, $replay );
	}

	/**
	 * A draft handoff that was refused or failed.
	 *
	 * @param Campaign_Workflow_Error        $error          Why the operation was refused or failed.
	 * @param Campaign|null                  $campaign       The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference      The campaign's remote reference.
	 * @param Delivery_Attempt|null          $attempt        The delivery attempt.
	 * @param Provider_Error|null            $provider_error The normalized provider error, when there is one.
	 */
	public static function failure(
		Campaign_Workflow_Error $error,
		?Campaign $campaign = null,
		?Remote_Campaign_Reference $reference = null,
		?Delivery_Attempt $attempt = null,
		?Provider_Error $provider_error = null
	): self {
		return new self( $campaign, $reference, $attempt, $error, $provider_error, false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_success(): bool {
		return null === $this->error;
	}

	/**
	 * {@inheritDoc}
	 */
	public function campaign(): ?Campaign {
		return $this->campaign;
	}

	/**
	 * {@inheritDoc}
	 */
	public function reference(): ?Remote_Campaign_Reference {
		return $this->reference;
	}

	/**
	 * {@inheritDoc}
	 */
	public function attempt(): ?Delivery_Attempt {
		return $this->attempt;
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}

	/** The normalized provider failure behind a provider_failed or unknown outcome. */
	public function provider_error(): ?Provider_Error {
		return $this->provider_error;
	}

	/** Whether an existing remote draft was returned instead of creating one. */
	public function is_idempotent_replay(): bool {
		return $this->replay;
	}
}
