<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
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
	 * @param array<int, Campaign>         $campaigns Authorized campaigns.
	 * @param Campaign_Workflow_Error|null $error     Stable failure, if any.
	 */
	private function __construct(
		private readonly array $campaigns,
		private readonly int $total,
		private readonly ?Campaign_Workflow_Error $error
	) {}

	/** @param array<int, Campaign> $campaigns Authorized campaigns. */
	public static function success( array $campaigns, int $total ): self {
		return new self( $campaigns, $total, null );
	}

	public static function failure( Campaign_Workflow_Error $error ): self {
		return new self( array(), 0, $error );
	}

	public function is_success(): bool {
		return null === $this->error;
	}

	/** @return array<int, Campaign> */
	public function campaigns(): array {
		return $this->campaigns;
	}

	public function total(): int {
		return $this->total;
	}

	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}
}
