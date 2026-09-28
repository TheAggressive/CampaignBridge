<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Stable provider-neutral campaign workflow result.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Email\Compile_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Typed result shared by future REST, Abilities, CLI, and UI adapters. */
final class Campaign_Workflow_Result {
	private function __construct(
		private readonly bool $success,
		private readonly ?Campaign $campaign,
		private readonly ?Campaign_Snapshot $snapshot,
		private readonly ?Compile_Result $compile_result,
		private readonly ?Campaign_Workflow_Error $error,
		private readonly bool $idempotent
	) {}

	public static function success(
		Campaign $campaign,
		?Campaign_Snapshot $snapshot = null,
		?Compile_Result $compile_result = null,
		bool $idempotent = false
	): self {
		return new self( true, $campaign, $snapshot, $compile_result, null, $idempotent );
	}

	public static function failure(
		Campaign_Workflow_Error $error,
		?Campaign $campaign = null,
		?Compile_Result $compile_result = null
	): self {
		return new self( false, $campaign, null, $compile_result, $error, false );
	}

	public function is_success(): bool {
		return $this->success;
	}

	public function campaign(): ?Campaign {
		return $this->campaign;
	}

	public function snapshot(): ?Campaign_Snapshot {
		return $this->snapshot;
	}

	public function compile_result(): ?Compile_Result {
		return $this->compile_result;
	}

	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}

	public function is_idempotent_replay(): bool {
		return $this->idempotent;
	}
}
