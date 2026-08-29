<?php
/**
 * Provider credential migrations.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates provider secrets to the current versioned encryption envelope.
 */
final class Credential_Migrator {
	private const SCHEMA_VERSION        = 1;
	private const SCHEMA_VERSION_OPTION = 'credential_schema_version';

	/**
	 * Stored provider credential option keys.
	 *
	 * @var array<int, string>
	 */
	private const CREDENTIAL_OPTIONS = array(
		'campaignbridge_mailchimp_api_key',
	);

	/**
	 * Run idempotent credential migrations.
	 *
	 * @throws \RuntimeException When a credential cannot be migrated safely.
	 */
	public static function migrate(): void {
		$current_version = (int) Storage::get_option( self::SCHEMA_VERSION_OPTION, 0 );
		if ( $current_version >= self::SCHEMA_VERSION ) {
			return;
		}

		foreach ( self::CREDENTIAL_OPTIONS as $option_name ) {
			$value = Storage::get_option( $option_name, '' );
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$migrated = Encryption::migrate_legacy_value( $value );
			if ( $migrated !== $value && ! Storage::update_option( $option_name, $migrated ) ) {
				throw new \RuntimeException( 'Unable to migrate a stored provider credential.' );
			}
		}

		Storage::update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}
}
