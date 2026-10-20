<?php
/**
 * Production webhook composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Webhook;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Provider\Discovered_Audience;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Providers\Mailchimp_Webhook_Gateway;
use CampaignBridge\Providers\Mailchimp_Webhook_Receiver;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Webhook_Receipt_Repository;
use CampaignBridge\Repository\Webhook_Subscription_Repository;
use CampaignBridge\REST\Rest_Constants;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Job\Job_Queue;
use CampaignBridge\Workflow\Webhook\Webhook_Intake;
use CampaignBridge\Workflow\Webhook\Webhook_Registrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the webhook intake and registrar with production storage and adapters.
 */
final class Webhook_Factory {
	/** Providers that can deliver webhooks to this site. */
	public const PROVIDERS = array( 'mailchimp' );

	/** Route base of the webhook endpoint in the REST namespace. */
	public const ROUTE_BASE = '/webhooks/';

	/**
	 * The intake for one provider, or null when it has no webhooks.
	 *
	 * @param string $provider Provider slug.
	 */
	public static function intake( string $provider ): ?Webhook_Intake {
		if ( 'mailchimp' !== $provider ) {
			return null;
		}
		$clock = new System_Clock();

		return new Webhook_Intake(
			new Mailchimp_Webhook_Receiver(),
			new Webhook_Subscription_Repository(),
			static fn ( string $encrypted ): string => Encryption::decrypt( $encrypted ),
			new Webhook_Receipt_Repository(),
			new Remote_Campaign_Reference_Repository(),
			new Campaign_Repository(),
			new Job_Queue( new Job_Repository(), $clock ),
			$clock
		);
	}

	/**
	 * The registrar for one provider, or null when it has no webhooks.
	 *
	 * @param string $provider Provider slug.
	 */
	public static function registrar( string $provider ): ?Webhook_Registrar {
		if ( 'mailchimp' !== $provider ) {
			return null;
		}

		return new Webhook_Registrar(
			new Mailchimp_Webhook_Gateway(),
			new Webhook_Subscription_Repository(),
			static fn (): array => Provider_Discovery_Factory::settings( $provider ),
			static function ( array $settings ): array|Provider_Error {
				$batch = ( new Mailchimp_Discovery() )->discover_audiences( $settings );
				if ( ! $batch instanceof Discovery_Batch ) {
					return $batch;
				}
				$ids = array();
				foreach ( $batch->items() as $audience ) {
					if ( $audience instanceof Discovered_Audience ) {
						$ids[] = $audience->id();
					}
				}

				return $ids;
			},
			static fn ( string $secret ): string => Encryption::encrypt( $secret ),
			self::url( $provider ),
			new System_Clock()
		);
	}

	/**
	 * This site's webhook URL for one provider.
	 *
	 * @param string $provider Provider slug.
	 */
	public static function url( string $provider ): string {
		$url = \rest_url( Rest_Constants::API_NAMESPACE . self::ROUTE_BASE . $provider );

		/**
		 * Filters the public address a provider delivers webhooks to.
		 *
		 * A site behind a reverse proxy or tunnel whose public address differs
		 * from its REST URL may supply that address here. It must reach this
		 * site's webhook route.
		 *
		 * @param string $url      Webhook URL.
		 * @param string $provider Provider slug.
		 */
		$filtered = \apply_filters( 'campaignbridge_webhook_url', $url, $provider );

		return is_string( $filtered ) && false !== filter_var( $filtered, FILTER_VALIDATE_URL ) ? $filtered : $url;
	}
}
