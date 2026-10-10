<?php
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
	/**
	 * Whether the remote operation completed.
	 */
	public function is_success(): bool;

	/**
	 * The campaign after the operation, when it was read.
	 */
	public function campaign(): ?Campaign;

	/**
	 * The campaign's remote reference, when one exists.
	 */
	public function reference(): ?Remote_Campaign_Reference;

	/**
	 * The delivery attempt the operation recorded, when there is one.
	 */
	public function attempt(): ?Delivery_Attempt;

	/**
	 * Why the operation was refused or failed.
	 */
	public function error(): ?Campaign_Workflow_Error;

	/**
	 * The normalized provider error behind a failure, when there is one.
	 */
	public function provider_error(): ?Provider_Error;
}
