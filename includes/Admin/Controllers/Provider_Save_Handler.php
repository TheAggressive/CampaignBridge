<?php
/**
 * Provider Settings Save Handler.
 *
 * Production save path for the providers settings form.
 * Extracted from the inline closure for testability.
 *
 * @package CampaignBridge\Admin\Controllers
 */

namespace CampaignBridge\Admin\Controllers;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Provider_Connection_Repository;

/**
 * Handles the save logic for the providers settings form.
 */
final class Provider_Save_Handler {

	/**
	 * Process the providers form save.
	 *
	 * @param array<string, mixed> $data Sanitized form data (encrypted fields already encrypted).
	 * @return bool True on success, false to abort (no partial state written).
	 */
	public static function handle( array $data ): bool {
		$provider = (string) ( $data['provider'] ?? 'html' );

		if ( 'mailchimp' === $provider ) {
			if ( ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) ) {
				return false;
			}

			$repository = new Provider_Connection_Repository();
			$existing   = $repository->get( 'mailchimp' );
			$new_key    = trim( (string) ( $data['mailchimp_api_key'] ?? '' ) );
			$audience   = (string) ( $data['mailchimp_audience'] ?? '' );

			if ( '' === $new_key && null === $existing ) {
				return false;
			}

			if ( null !== $existing && ( '' === $new_key || $new_key === $existing->api_key() ) ) {
				// Unchanged credential: preserve verification state, timestamp, account details.
				$current = self::under_current_key( $existing );
				if ( null === $current ) {
					return false;
				}
				$connection = $current->with_audience( $audience );
			} else {
				// New or changed credential: validate format before persisting.
				if ( ! self::validate_new_api_key( $new_key ) ) {
					return false;
				}
				$connection = Provider_Connection::create( 'mailchimp', $new_key, $audience );
			}

			// Save connection first; only persist provider selection on success.
			if ( ! $repository->save( $connection ) ) {
				return false;
			}

			Storage::update_option( 'campaignbridge_provider', 'mailchimp' );
			return true;
		}

		// Non-mailchimp provider: no connection to save.
		Storage::update_option( 'campaignbridge_provider', $provider );
		return true;
	}

	/**
	 * Move an unchanged credential under the current encryption key.
	 *
	 * This is the deliberate re-encryption step after a key change: an
	 * operator who can manage connections saves the providers screen. The new
	 * envelope is verified before it is returned, and the previous key is
	 * left in place, so a failure keeps the stored value readable.
	 *
	 * @param Provider_Connection $existing Stored connection.
	 * @return Provider_Connection|null The connection under the current key, or null when it cannot be re-encrypted.
	 */
	private static function under_current_key( Provider_Connection $existing ): ?Provider_Connection {
		try {
			$envelope = Encryption::reencrypt( $existing->api_key() );
		} catch ( \RuntimeException ) {
			return null;
		}

		return $envelope === $existing->api_key() ? $existing : $existing->with_reencrypted_api_key( $envelope );
	}

	/**
	 * Validate a new (encrypted) API key by decrypting for format check only.
	 *
	 * The plaintext is discarded immediately after validation.
	 * Never included in error messages or logs.
	 *
	 * @param string $encrypted_key The encrypted API key to validate.
	 * @return bool True if the decrypted key matches the Mailchimp format.
	 */
	private static function validate_new_api_key( string $encrypted_key ): bool {
		if ( ! Encryption::is_encrypted_value( $encrypted_key ) ) {
			return false;
		}

		try {
			$plaintext = Encryption::decrypt( $encrypted_key );
		} catch ( \RuntimeException $e ) {
			return false;
		}

		$provider = new Mailchimp_Provider();
		$valid    = $provider->is_valid_api_key( $plaintext );

		// Discard plaintext immediately.
		unset( $plaintext );

		return $valid;
	}
}
