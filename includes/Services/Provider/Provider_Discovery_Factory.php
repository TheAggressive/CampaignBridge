<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Production provider discovery composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Provider;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;
use CampaignBridge\Services\Lock\Lock_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the discovery service and loads decrypted settings for one call.
 *
 * Delivery adapters (REST, admin, future Abilities) use this instead of
 * touching repositories, encryption, or provider classes directly.
 */
final class Provider_Discovery_Factory {
	/** Providers with a discovery adapter. */
	public const PROVIDERS = array( 'mailchimp' );

	/** The discovery service for one provider, or null when it has none. */
	public static function service( string $provider ): ?Provider_Discovery_Service {
		return match ( $provider ) {
			'mailchimp' => new Provider_Discovery_Service( new Mailchimp_Discovery(), new Provider_Discovery_Repository(), new System_Clock(), Lock_Factory::manager() ),
			default     => null,
		};
	}

	/**
	 * Decrypted settings for one discovery call.
	 *
	 * Returns an empty array when no usable credential is stored; the service
	 * then reports the provider as not configured. Callers must not persist,
	 * log, or return the result.
	 *
	 * @return array<string, string>
	 */
	public static function settings( string $provider ): array {
		$connection = ( new Provider_Connection_Repository() )->get( $provider );
		if ( null === $connection || '' === $connection->api_key() ) {
			return array();
		}
		try {
			$api_key = Encryption::decrypt( $connection->api_key() );
		} catch ( \Throwable ) {
			return array();
		}

		return '' === $api_key ? array() : array( 'api_key' => $api_key );
	}
}
