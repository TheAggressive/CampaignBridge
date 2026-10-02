<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed ports and values use explicit signatures and class-level documentation.
/**
 * Discovered reference cache port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded cache of discovery results, partitioned by connected account.
 */
interface Provider_Discovery_Source {
	public function get( string $account, string $provider, string $kind, string $scope ): ?Discovery_Result;

	public function save( string $account, Discovery_Result $result ): bool;
}
