<?php // phpcs:disable Squiz.Commenting.FunctionComment
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

	public static function sent( Campaign $campaign, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, Campaign_Snapshot $snapshot, Test_Delivery $delivery ): self {
		return new self( $campaign, $reference, $attempt, $snapshot, $delivery, null, null, false );
	}

	public static function replay( Campaign $campaign, ?Remote_Campaign_Reference $reference, Delivery_Attempt $attempt ): self {
		return new self( $campaign, $reference, $attempt, null, null, null, null, true );
	}

	public static function failure(
		Campaign_Workflow_Error $error,
		?Campaign $campaign = null,
		?Remote_Campaign_Reference $reference = null,
		?Delivery_Attempt $attempt = null,
		?Provider_Error $provider_error = null
	): self {
		return new self( $campaign, $reference, $attempt, null, null, $error, $provider_error, false );
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

	/** The approved snapshot whose remote draft was tested; null on replay. */
	public function snapshot(): ?Campaign_Snapshot {
		return $this->snapshot;
	}

	/** The validated request; null on replay. */
	public function delivery(): ?Test_Delivery {
		return $this->delivery;
	}

	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}

	public function provider_error(): ?Provider_Error {
		return $this->provider_error;
	}

	/** Whether a recorded test was returned instead of sending another. */
	public function is_idempotent_replay(): bool {
		return $this->replay;
	}
}
