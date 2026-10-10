<?php
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
	/**
	 * The cached list for one account, provider, kind, and scope.
	 *
	 * @param string $account  Stable key for the provider account.
	 * @param string $provider Provider slug.
	 * @param string $kind     Discovery kind.
	 * @param string $scope    Discovery scope: empty, or an audience ID.
	 */
	public function get( string $account, string $provider, string $kind, string $scope ): ?Discovery_Result;

	/**
	 * Replace the cached list.
	 *
	 * @param string           $account Stable key for the provider account.
	 * @param Discovery_Result $result  The discovered list.
	 */
	public function save( string $account, Discovery_Result $result ): bool;
}
