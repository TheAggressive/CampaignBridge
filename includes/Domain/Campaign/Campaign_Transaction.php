<?php
/**
 * Atomic campaign persistence boundary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Runs a set of repository writes atomically. */
interface Campaign_Transaction {
	/**
	 * Run an operation atomically; false rolls everything back.
	 *
	 * @param callable(): bool $operation Writes that return true only when complete.
	 */
	public function run( callable $operation ): bool;
}
