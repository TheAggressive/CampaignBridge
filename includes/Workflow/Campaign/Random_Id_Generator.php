<?php
/**
 * Cryptographically random workflow identifiers.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates bounded opaque identifiers without external state. */
final class Random_Id_Generator implements Campaign_Id_Generator {
	/**
	 * {@inheritDoc}
	 *
	 * @throws \InvalidArgumentException When the prefix is not a short lowercase identifier.
	 */
	public function generate( string $prefix ): string {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]{0,15}$/', $prefix ) ) {
			throw new \InvalidArgumentException( 'Identifier prefix is invalid.' );
		}

		return $prefix . '-' . bin2hex( random_bytes( 16 ) );
	}
}
