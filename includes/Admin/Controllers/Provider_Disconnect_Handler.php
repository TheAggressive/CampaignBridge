<?php
/**
 * Provider connection removal.
 *
 * @package CampaignBridge\Admin\Controllers
 */

namespace CampaignBridge\Admin\Controllers;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Core\Error_Handler;
use CampaignBridge\Core\Storage;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Services\Webhook\Webhook_Factory;

/**
 * Removes a stored provider connection from this site.
 *
 * The provider's webhooks for this site are removed first, while the key
 * still works, and their secrets are forgotten either way. Otherwise only
 * the encrypted API key and its connection details are deleted. The key
 * stays valid in the provider until it is revoked there, and campaigns the
 * provider already holds are untouched: a scheduled campaign still sends,
 * and CampaignBridge cannot unschedule or reconcile it until a key is saved
 * again.
 */
final class Provider_Disconnect_Handler {

	/** Providers whose connection this site can store. */
	private const PROVIDERS = array( 'mailchimp' );

	/**
	 * Remove one provider connection.
	 *
	 * @param string $provider Provider slug.
	 * @return bool True when the connection is gone, false when refused.
	 */
	public static function handle( string $provider ): bool {
		if ( ! in_array( $provider, self::PROVIDERS, true ) || ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) ) {
			return false;
		}

		$repository = new Provider_Connection_Repository();
		$existed    = null !== $repository->get( $provider );
		$webhooks   = Webhook_Factory::registrar( $provider );
		if ( null !== $webhooks && array() !== $webhooks->subscriptions() ) {
			// Take the webhooks down while the key still works; any left behind are refused from now on.
			$webhooks->disable();
			$webhooks->forget();
		}
		if ( ! $repository->delete( $provider ) ) { // phpcs:ignore CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.InvalidWpdbUsage -- Repository method, not $wpdb.
			return false;
		}
		if ( Storage::get_option( 'campaignbridge_provider', 'html' ) === $provider ) {
			Storage::update_option( 'campaignbridge_provider', 'html' );
		}
		if ( $existed ) {
			Error_Handler::info(
				'Provider connection removed',
				array(
					'provider' => $provider,
					'user_id'  => get_current_user_id(),
				)
			);
		}

		return true;
	}
}
