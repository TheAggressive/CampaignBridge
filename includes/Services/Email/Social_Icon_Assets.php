<?php
/**
 * Fixed social icon asset catalogue.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Locates the bounded raster icon set shipped with CampaignBridge. */
final class Social_Icon_Assets {
	private const DIRECTORY = 'assets/email/social/';

	/**
	 * Return supported service names keyed by Core slug.
	 *
	 * @return array<string, string> Service labels keyed by slug.
	 */
	public static function services(): array {
		return Email_Block_Contract::social_services();
	}

	/**
	 * Get one accessible service name.
	 *
	 * @param string $service Core service slug.
	 */
	public static function label( string $service ): ?string {
		return self::services()[ $service ] ?? null;
	}

	/**
	 * Get the absolute HTTPS URL of one packaged PNG, or null when unsupported.
	 *
	 * @param string $service Core service slug.
	 */
	public static function url( string $service ): ?string {
		if ( ! isset( self::services()[ $service ] ) ) {
			return null;
		}

		return \set_url_scheme(
			\CampaignBridge_Plugin::url() . self::DIRECTORY . $service . '.png',
			'https'
		);
	}
}
