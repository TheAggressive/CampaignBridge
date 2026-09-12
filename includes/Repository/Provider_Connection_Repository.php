<?php
/**
 * WordPress provider connection storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Campaign\Provider_Connection_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists provider connections as one versioned option per provider.
 *
 * Migrates legacy scattered options (campaignbridge_mailchimp_api_key,
 * campaignbridge_mailchimp_audience) into the canonical connection model
 * on first read. Corrupt or unknown stored shapes return null so a bad
 * write cannot take the workflow down.
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
	 * @return Provider_Connection|null Connection or null if not found.
	 */
	public function get( string $provider_slug ): ?Provider_Connection {
		$stored = Storage::get_option( self::option_key( $provider_slug ), null );

		if ( is_array( $stored ) ) {
			// The stored api_key is encrypted; decrypt for the domain object.
			if ( isset( $stored['api_key'] ) && is_string( $stored['api_key'] ) && '' !== $stored['api_key'] ) {
				$decrypted = Encryption::decrypt( $stored['api_key'] );
				if ( '' !== $decrypted ) {
					$stored['api_key'] = $decrypted;
				}
			}

			try {
				return Provider_Connection::from_array( $stored );
			} catch ( \InvalidArgumentException $e ) {
				return null;
			}
		}

		// Migrate legacy scattered options into the canonical model.
		return $this->migrate_legacy( $provider_slug );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Provider_Connection $connection Connection to store.
	 */
	public function save( Provider_Connection $connection ): bool {
		$payload            = $connection->to_array();
		$payload['api_key'] = Encryption::encrypt( $connection->api_key() );

		// update_option() reports false when the stored value is identical,
		// which is a successful no-op rather than a failed write.
		if ( Storage::get_option( self::option_key( $connection->provider_slug() ), null ) === $payload ) {
			return true;
		}

		$success = Storage::update_option( self::option_key( $connection->provider_slug() ), $payload );

		// Clear legacy options after successful migration write.
		if ( $success ) {
			$this->clear_legacy( $connection->provider_slug() );
		}

		return $success;
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

	/**
	 * Migrate legacy scattered options into the canonical connection model.
	 *
	 * Reads campaignbridge_{slug}_api_key and campaignbridge_{slug}_audience,
	 * creates a Provider_Connection, persists it, and returns the domain object.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 */
	private function migrate_legacy( string $provider_slug ): ?Provider_Connection {
		$legacy_key_option   = 'campaignbridge_' . $provider_slug . '_api_key';
		$legacy_audience_opt = 'campaignbridge_' . $provider_slug . '_audience';

		$stored_key = Storage::get_option( $legacy_key_option, '' );

		if ( ! is_string( $stored_key ) || '' === $stored_key ) {
			return null;
		}

		$api_key = Encryption::decrypt( $stored_key );
		if ( '' === $api_key ) {
			return null;
		}

		$audience_id = (string) Storage::get_option( $legacy_audience_opt, '' );

		$connection = Provider_Connection::create( $provider_slug, $api_key, $audience_id );

		// Persist the migrated connection.
		$payload            = $connection->to_array();
		$payload['api_key'] = Encryption::encrypt( $api_key );
		Storage::update_option( self::option_key( $provider_slug ), $payload );

		return $connection;
	}

	/**
	 * Remove legacy scattered options after migration.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 */
	private function clear_legacy( string $provider_slug ): void {
		Storage::delete_option( 'campaignbridge_' . $provider_slug . '_api_key' );
		Storage::delete_option( 'campaignbridge_' . $provider_slug . '_audience' );
	}
}
