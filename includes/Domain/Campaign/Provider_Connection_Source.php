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
	 * @return array<string, mixed>|null Connection record or null when missing.
	 */
	public function get( string $provider_slug ): ?array;

	/**
	 * Persist a provider connection record.
	 *
	 * @param string               $provider_slug Provider slug identifier.
	 * @param array<string, mixed> $record        Connection data.
	 * @return bool True on success.
	 */
	public function save( string $provider_slug, array $record ): bool;

	/**
	 * Remove a provider connection record.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 * @return bool True on success.
	 */
	public function delete( string $provider_slug ): bool;
}
