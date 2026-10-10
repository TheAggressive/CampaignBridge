<?php
/**
 * Result of reconciling a campaign with its provider.
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
 * What reconciliation established, and which unresolved attempts it settled.
 *
 * A failure still carries the campaign, reference, and blocking attempt, so
 * callers can show what remains unresolved.
 */
final class Campaign_Reconcile_Result implements Campaign_Remote_Result {
	/**
	 * Build the campaign reconcile result.
	 *
	 * @param Campaign|null                  $campaign       The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference      The campaign's remote reference.
	 * @param array<int, Delivery_Attempt>   $resolved       Attempts settled from provider evidence.
	 * @param Delivery_Attempt|null          $attempt        The delivery attempt.
	 * @param Campaign_Workflow_Error|null   $error          Why the operation was refused or failed, when it was.
	 * @param Provider_Error|null            $provider_error The normalized provider error, when there is one.
	 */
	private function __construct(
		private readonly ?Campaign $campaign,
		private readonly ?Remote_Campaign_Reference $reference,
		private readonly array $resolved,
		private readonly ?Delivery_Attempt $attempt,
		private readonly ?Campaign_Workflow_Error $error,
		private readonly ?Provider_Error $provider_error
	) {}

	/**
	 * A reconciliation that settled the campaign.
	 *
	 * @param Campaign                       $campaign  The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference The campaign's remote reference.
	 * @param array<int, Delivery_Attempt>   $resolved  Attempts settled from provider evidence.
	 */
	public static function success( Campaign $campaign, ?Remote_Campaign_Reference $reference, array $resolved ): self {
		return new self( $campaign, $reference, $resolved, null, null, null );
	}

	/**
	 * A reconciliation that could not settle the campaign.
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
		return new self( $campaign, $reference, array(), $attempt, $error, $provider_error );
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
	 * The result's resolved.
	 *
	 * @return array<int, Delivery_Attempt>
	 */
	public function resolved_attempts(): array {
		return $this->resolved;
	}

	/** The attempt that remains unresolved after a failure. */
	public function attempt(): ?Delivery_Attempt {
		return $this->attempt;
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}

	/**
	 * {@inheritDoc}
	 */
	public function provider_error(): ?Provider_Error {
		return $this->provider_error;
	}
}
