<?php
/**
 * Outcome of a campaign test send.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Provider\Test_Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed test-send result shared by REST and future adapters.
 *
 * A new send carries the snapshot it tested and the bounded request; a
 * replay of a recorded key carries only the recorded attempt, because test
 * recipients are never stored.
 */
final class Campaign_Test_Result implements Campaign_Remote_Result {
	/**
	 * Build the campaign test result.
	 *
	 * @param Campaign|null                  $campaign       The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference      The campaign's remote reference.
	 * @param Delivery_Attempt|null          $attempt        The delivery attempt.
	 * @param Campaign_Snapshot|null         $snapshot       The campaign snapshot.
	 * @param Test_Delivery|null             $delivery       The test delivery request.
	 * @param Campaign_Workflow_Error|null   $error          Why the operation was refused or failed, when it was.
	 * @param Provider_Error|null            $provider_error The normalized provider error, when there is one.
	 * @param bool                           $replay         Whether this answers a repeated idempotency key.
	 */
	private function __construct(
		private readonly ?Campaign $campaign,
		private readonly ?Remote_Campaign_Reference $reference,
		private readonly ?Delivery_Attempt $attempt,
		private readonly ?Campaign_Snapshot $snapshot,
		private readonly ?Test_Delivery $delivery,
		private readonly ?Campaign_Workflow_Error $error,
		private readonly ?Provider_Error $provider_error,
		private readonly bool $replay
	) {}

	/**
	 * A test the provider accepted.
	 *
	 * @param Campaign                  $campaign  The campaign as read.
	 * @param Remote_Campaign_Reference $reference The campaign's remote reference.
	 * @param Delivery_Attempt          $attempt   The delivery attempt.
	 * @param Campaign_Snapshot         $snapshot  The campaign snapshot.
	 * @param Test_Delivery             $delivery  The test delivery request.
	 */
	public static function sent( Campaign $campaign, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, Campaign_Snapshot $snapshot, Test_Delivery $delivery ): self {
		return new self( $campaign, $reference, $attempt, $snapshot, $delivery, null, null, false );
	}

	/**
	 * A repeated test request answered from its original attempt.
	 *
	 * @param Campaign                       $campaign  The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference The campaign's remote reference.
	 * @param Delivery_Attempt               $attempt   The delivery attempt.
	 */
	public static function replay( Campaign $campaign, ?Remote_Campaign_Reference $reference, Delivery_Attempt $attempt ): self {
		return new self( $campaign, $reference, $attempt, null, null, null, null, true );
	}

	/**
	 * A test that was refused or failed.
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
		return new self( $campaign, $reference, $attempt, null, null, $error, $provider_error, false );
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

	/** The approved snapshot whose remote draft was tested; null on replay. */
	public function snapshot(): ?Campaign_Snapshot {
		return $this->snapshot;
	}

	/** The validated request; null on replay. */
	public function delivery(): ?Test_Delivery {
		return $this->delivery;
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

	/** Whether a recorded test was returned instead of sending another. */
	public function is_idempotent_replay(): bool {
		return $this->replay;
	}
}
