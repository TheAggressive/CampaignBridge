<?php
/**
 * WordPress brand kit storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Brand_Kit_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists the email brand kit as one versioned option.
 *
 * Corrupt or unknown stored shapes fall back to the CampaignBridge
 * defaults so a bad write cannot take the compiler or editor down.
 */
final class Brand_Kit_Repository implements Brand_Kit_Source {
	public const OPTION = 'brand_kit';

	/** {@inheritDoc} */
	public function get(): Brand_Kit {
		$stored = Storage::get_option( self::OPTION, null );

		if ( ! is_array( $stored ) ) {
			return Brand_Kit::defaults();
		}

		try {
			return Brand_Kit::from_array( $stored );
		} catch ( \InvalidArgumentException $e ) {
			return Brand_Kit::defaults();
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Brand_Kit $kit Kit to store.
	 */
	public function save( Brand_Kit $kit ): bool {
		$payload = $kit->to_array();

		// update_option() reports false when the stored value is identical,
		// which is a successful no-op rather than a failed write. Saving the
		// same colour twice must not read as an error.
		if ( Storage::get_option( self::OPTION, null ) === $payload ) {
			return true;
		}

		return Storage::update_option( self::OPTION, $payload );
	}

	/** {@inheritDoc} */
	public function clear(): bool {
		// Deleting an option that was never written reports false; the caller
		// only cares that the defaults are in force afterwards.
		if ( null === Storage::get_option( self::OPTION, null ) ) {
			return true;
		}

		return Storage::delete_option( self::OPTION );
	}
}
