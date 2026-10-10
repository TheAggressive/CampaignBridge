<?php
/**
 * Stable provider-neutral campaign list result.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Typed bounded collection result shared by delivery adapters. */
final class Campaign_Workflow_List_Result {
	/**
	 * Build the campaign workflow list result.
	 *
	 * @param array<int, Campaign>         $campaigns Authorized campaigns.
	 * @param int                          $total     Number of matching records.
	 * @param Campaign_Workflow_Error|null $error     Why the operation was refused or failed, when it was.
	 */
	private function __construct(
		private readonly array $campaigns,
		private readonly int $total,
		private readonly ?Campaign_Workflow_Error $error
	) {}

	/**
	 * A page of campaigns and the total that match.
	 *
	 * @param array<int, Campaign> $campaigns Authorized campaigns.
	 * @param int                  $total     Number of matching records.
	 */
	public static function success( array $campaigns, int $total ): self {
		return new self( $campaigns, $total, null );
	}

	/**
	 * A refused or failed listing.
	 *
	 * @param Campaign_Workflow_Error $error Why the operation was refused or failed.
	 */
	public static function failure( Campaign_Workflow_Error $error ): self {
		return new self( array(), 0, $error );
	}

	/**
	 * Whether the listing succeeded.
	 */
	public function is_success(): bool {
		return null === $this->error;
	}

	/**
	 * The result's campaigns.
	 *
	 * @return array<int, Campaign>
	 */
	public function campaigns(): array {
		return $this->campaigns;
	}

	/**
	 * The result's total.
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * The result's error.
	 */
	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}
}
