<?php
/**
 * Provider interface.
 *
 * Defines the minimal contract that every email delivery provider must
 * implement. This is the port that new providers (e.g. SendGrid) implement
 * against without touching campaign, workflow, delivery, or block code.
 *
 * @package CampaignBridge\Providers
 */

namespace CampaignBridge\Providers;

use CampaignBridge\Domain\Campaign\Connection_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider interface.
 *
 * Every email delivery provider must implement this contract. The port is
 * intentionally minimal so that adding a new provider does not require
 * changes to campaign, workflow, delivery, or block code.
 */
interface Provider_Interface {

	/**
	 * Stable machine identifier for this provider.
	 *
	 * Used for option keys, status entries, and log correlation.
	 * Must be lowercase alphanumeric with underscores.
	 */
	public function slug(): string;

	/**
	 * Human-readable display name for this provider.
	 */
	public function label(): string;

	/**
	 * Whether the supplied settings are sufficient to use this provider.
	 *
	 * This is a local, offline validation — no network calls.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 */
	public function is_configured( array $settings ): bool;

	/**
	 * Perform a live, read-only connection check against the provider.
	 *
	 * @param array<string, mixed> $settings Provider settings.
	 * @return Connection_Result Domain-typed result wrapping success or a Provider_Error.
	 */
	public function verify_connection( array $settings ): Connection_Result;
}
