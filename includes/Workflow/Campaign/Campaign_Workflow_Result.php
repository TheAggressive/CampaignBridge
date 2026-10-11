<?php
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
	/**
	 * Build the campaign workflow result.
	 *
	 * @param bool                         $success        Whether the operation succeeded.
	 * @param Campaign|null                $campaign       The campaign as read.
	 * @param Campaign_Snapshot|null       $snapshot       The campaign snapshot.
	 * @param Compile_Result|null          $compile_result Compiler output, when there is one.
	 * @param Campaign_Workflow_Error|null $error          Why the operation was refused or failed, when it was.
	 * @param bool                         $idempotent     Whether this answers a repeated idempotency key.
	 */
	private function __construct(
		private readonly bool $success,
		private readonly ?Campaign $campaign,
		private readonly ?Campaign_Snapshot $snapshot,
		private readonly ?Compile_Result $compile_result,
		private readonly ?Campaign_Workflow_Error $error,
		private readonly bool $idempotent
	) {}

	/**
	 * A workflow operation that succeeded.
	 *
	 * @param Campaign               $campaign       The campaign as read.
	 * @param Campaign_Snapshot|null $snapshot       The campaign snapshot.
	 * @param Compile_Result|null    $compile_result Compiler output, when there is one.
	 * @param bool                   $idempotent     Whether this answers a repeated idempotency key.
	 */
	public static function success(
		Campaign $campaign,
		?Campaign_Snapshot $snapshot = null,
		?Compile_Result $compile_result = null,
		bool $idempotent = false
	): self {
		return new self( true, $campaign, $snapshot, $compile_result, null, $idempotent );
	}

	/**
	 * A workflow operation that was refused or failed.
	 *
	 * @param Campaign_Workflow_Error $error          Why the operation was refused or failed.
	 * @param Campaign|null           $campaign       The campaign as read.
	 * @param Compile_Result|null     $compile_result Compiler output, when there is one.
	 */
	public static function failure(
		Campaign_Workflow_Error $error,
		?Campaign $campaign = null,
		?Compile_Result $compile_result = null
	): self {
		return new self( false, $campaign, null, $compile_result, $error, false );
	}

	/**
	 * Whether the result is success.
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * The result's campaign.
	 */
	public function campaign(): ?Campaign {
		return $this->campaign;
	}

	/**
	 * The result's snapshot.
	 */
	public function snapshot(): ?Campaign_Snapshot {
		return $this->snapshot;
	}

	/**
	 * The result's compile result.
	 */
	public function compile_result(): ?Compile_Result {
		return $this->compile_result;
	}

	/**
	 * The result's error.
	 */
	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}

	/**
	 * Whether the result is idempotent replay.
	 */
	public function is_idempotent_replay(): bool {
		return $this->idempotent;
	}
}
