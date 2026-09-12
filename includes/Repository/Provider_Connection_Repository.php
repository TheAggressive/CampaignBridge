<?php
/**
 * WordPress provider connection storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Campaign\Provider_Connection_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists provider connections as one versioned option per provider.
 *
 * The credential is stored in its encrypted (opaque) form. This repository
 * does not decrypt or encrypt; it reads and writes the stored representation
 * as-is. Callers are responsible for decrypting immediately before use.
 */
final class Provider_Connection_Repository implements Provider_Connection_Source {
	/**
	 * Build the option key for a provider slug.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 */
	private static function option_key( string $provider_slug ): string {
		return 'provider_connection_' . $provider_slug;
	}

	/**
	 * Get a provider connection by slug.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 * @return Provider_Connection|null Connection or null if not found or malformed.
	 */
	public function get( string $provider_slug ): ?Provider_Connection {
		$stored = Storage::get_option( self::option_key( $provider_slug ), null );

		if ( ! is_array( $stored ) ) {
			return null;
		}

		try {
			$connection = Provider_Connection::from_array( $stored );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		if ( $connection->provider_slug() !== $provider_slug ) {
			return null;
		}

		return $connection;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Provider_Connection $connection Connection to store.
	 */
	public function save( Provider_Connection $connection ): bool {
		$payload = $connection->to_array();

		// update_option() reports false when the stored value is identical,
		// which is a successful no-op rather than a failed write.
		if ( Storage::get_option( self::option_key( $connection->provider_slug() ), null ) === $payload ) {
			return true;
		}

		return Storage::update_option( self::option_key( $connection->provider_slug() ), $payload );
	}

	/**
	 * Delete a provider connection by slug.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 * @return bool True if deleted or already absent.
	 */
	public function delete( string $provider_slug ): bool {
		if ( null === Storage::get_option( self::option_key( $provider_slug ), null ) ) {
			return true;
		}

		return Storage::delete_option( self::option_key( $provider_slug ) );
	}
}
