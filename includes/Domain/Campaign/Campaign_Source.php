<?php
/**
 * Campaign persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and stores campaign records.
 *
 * Workflow and delivery read through this port. The WordPress post-type
 * implementation lives in the repository layer.
 */
interface Campaign_Source {
	/**
	 * Load a campaign by its identifier.
	 *
	 * @param string $id Campaign identifier.
	 * @return array<string, mixed>|null Campaign record or null when missing.
	 */
	public function get( string $id ): ?array;

	/**
	 * Persist a campaign record.
	 *
	 * @param string               $id     Campaign identifier.
	 * @param array<string, mixed> $record Campaign data.
	 * @return bool True on success.
	 */
	public function save( string $id, array $record ): bool;

	/**
	 * Transition a campaign to a new state.
	 *
	 * The transition is validated against the state machine before writing.
	 *
	 * @param string $id       Campaign identifier.
	 * @param string $new_state Target state.
	 * @return bool True on success.
	 */
	public function transition_state( string $id, string $new_state ): bool;

	/**
	 * Remove a campaign record.
	 *
	 * @param string $id Campaign identifier.
	 * @return bool True on success.
	 */
	public function delete( string $id ): bool;
}
