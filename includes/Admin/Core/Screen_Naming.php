<?php
/**
 * Naming conventions for discovered admin screens.
 *
 * @package CampaignBridge\Admin\Core
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Converts discovered file names into presentation-safe identifiers. */
final class Screen_Naming {
	/**
	 * Convert a screen name to a URL slug.
	 *
	 * @param string $name Screen name.
	 */
	public static function slug( string $name ): string {
		return strtolower( str_replace( array( '_', ' ' ), '-', $name ) );
	}

	/**
	 * Convert a screen name to a human-readable title.
	 *
	 * @param string $name Screen name.
	 */
	public static function title( string $name ): string {
		return ucwords( str_replace( array( '_', '-' ), ' ', $name ) );
	}
}
