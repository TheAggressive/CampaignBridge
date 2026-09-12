<?php
/**
 * Provider connection persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and stores provider connection records.
 *
 * Workflow reads connection state through this port. The WordPress option
 * or metadata implementation lives in the repository layer.
 */
interface Provider_Connection_Source {
	/**
	 * Load a provider connection by provider slug.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 * @return Provider_Connection|null Connection record or null when missing.
	 */
	public function get( string $provider_slug ): ?Provider_Connection;

	/**
	 * Persist a provider connection record.
	 *
	 * @param Provider_Connection $connection Connection to store.
	 * @return bool True on success.
	 */
	public function save( Provider_Connection $connection ): bool;

	/**
	 * Remove a provider connection record.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 * @return bool True on success.
	 */
	public function delete( string $provider_slug ): bool;
}
