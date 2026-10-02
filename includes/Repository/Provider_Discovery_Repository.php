<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port implementation signatures are documented by Provider_Discovery_Source.
/**
 * Transient-backed discovered reference cache.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Provider_Discovery_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores normalized discovery results as transients.
 *
 * Keys hash the account, provider, kind, and scope, so no credential or
 * remote identifier appears in an option name. Entries expire after a fixed
 * retention window; freshness is decided by the workflow from `fetched_at`.
 * Malformed or mismatched entries read as a miss.
 */
final class Provider_Discovery_Repository implements Provider_Discovery_Source {
	/** Maximum time a discovery result is retained, in seconds. */
	public const RETENTION_SECONDS = DAY_IN_SECONDS;

	public function get( string $account, string $provider, string $kind, string $scope ): ?Discovery_Result {
		$key = self::key( $account, $provider, $kind, $scope );
		if ( null === $key ) {
			return null;
		}

		$stored = Storage::get_transient( $key );
		if ( ! is_array( $stored ) ) {
			return null;
		}
		try {
			$result = Discovery_Result::from_array( $stored );
		} catch ( \InvalidArgumentException ) {
			return null;
		}

		return $result->provider() === $provider && $result->kind() === $kind && $result->scope() === $scope ? $result : null;
	}

	public function save( string $account, Discovery_Result $result ): bool {
		$key = self::key( $account, $result->provider(), $result->kind(), $result->scope() );

		return null !== $key && Storage::set_transient( $key, $result->to_array(), self::RETENTION_SECONDS );
	}

	private static function key( string $account, string $provider, string $kind, string $scope ): ?string {
		if ( '' === $account || 128 < strlen( $account ) ) {
			return null;
		}

		return 'discovery_' . substr( hash( 'sha256', implode( "\0", array( $account, $provider, $kind, $scope ) ) ), 0, 40 );
	}
}
