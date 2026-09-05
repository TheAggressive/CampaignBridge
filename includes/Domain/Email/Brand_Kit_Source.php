<?php
/**
 * Brand kit persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and stores the active email brand kit.
 *
 * Workflow and delivery read through this port. The WordPress option
 * implementation lives in the repository layer.
 */
interface Brand_Kit_Source {
	/**
	 * Get the active kit, or the CampaignBridge defaults when none is stored.
	 */
	public function get(): Brand_Kit;

	/**
	 * Persist a validated kit.
	 *
	 * @param Brand_Kit $kit Kit to store.
	 */
	public function save( Brand_Kit $kit ): bool;

	/**
	 * Remove the stored kit so the defaults return.
	 */
	public function clear(): bool;
}
