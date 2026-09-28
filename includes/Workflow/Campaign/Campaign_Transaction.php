<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Atomic campaign workflow boundary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Runs a set of repository writes atomically. */
interface Campaign_Transaction {
	/** @param callable(): bool $operation Writes that return true only when complete. */
	public function run( callable $operation ): bool;
}
