<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures are the contract.
/**
 * Shared shape of remote campaign operation results.
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
 * What adapters may report after a remote operation, including a failed one:
 * the remote reference and attempt that already exist, and the normalized
 * provider error.
 */
interface Campaign_Remote_Result {
	public function is_success(): bool;

	public function campaign(): ?Campaign;

	public function reference(): ?Remote_Campaign_Reference;

	public function attempt(): ?Delivery_Attempt;

	public function error(): ?Campaign_Workflow_Error;

	public function provider_error(): ?Provider_Error;
}
