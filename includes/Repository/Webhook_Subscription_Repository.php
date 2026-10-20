<?php
/**
 * WordPress webhook subscription storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Webhook\Webhook_Subscription;
use CampaignBridge\Domain\Webhook\Webhook_Subscription_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists a provider's webhooks as one option per provider, keyed by audience.
 *
 * Signing secrets are stored in their encrypted (opaque) form; this
 * repository never encrypts or decrypts them. A malformed entry is dropped
 * on read, so it can only stop that audience's deliveries from being
 * accepted, never admit one.
 */
final class Webhook_Subscription_Repository implements Webhook_Subscription_Source {
	/**
	 * Option key for a provider slug.
	 *
	 * @param string $provider Provider slug.
	 */
	private static function option_key( string $provider ): string {
		return 'provider_webhooks_' . $provider;
	}

	/**
	 * {@inheritDoc}
	 */
	public function all( string $provider ): array {
		$stored = Storage::get_option( self::option_key( $provider ), array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$subscriptions = array();
		foreach ( $stored as $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}
			try {
				$subscription = Webhook_Subscription::from_array( $data );
			} catch ( \InvalidArgumentException ) {
				continue;
			}
			if ( $subscription->provider() === $provider ) {
				$subscriptions[ $subscription->audience_id() ] = $subscription;
			}
		}

		return $subscriptions;
	}

	/**
	 * {@inheritDoc}
	 */
	public function save( Webhook_Subscription $subscription ): bool {
		$all                                 = $this->all( $subscription->provider() );
		$all[ $subscription->audience_id() ] = $subscription;

		return $this->write( $subscription->provider(), $all );
	}

	/**
	 * {@inheritDoc}
	 */
	public function remove( string $provider, string $audience_id ): bool {
		$all = $this->all( $provider );
		if ( ! isset( $all[ $audience_id ] ) ) {
			return true;
		}
		unset( $all[ $audience_id ] );

		return $this->write( $provider, $all );
	}

	/**
	 * Store one provider's webhooks, deleting the option when none remain.
	 *
	 * @param string                              $provider      Provider slug.
	 * @param array<string, Webhook_Subscription> $subscriptions Webhooks keyed by audience.
	 */
	private function write( string $provider, array $subscriptions ): bool {
		$key = self::option_key( $provider );
		if ( array() === $subscriptions ) {
			return null === Storage::get_option( $key, null ) || Storage::delete_option( $key );
		}
		$payload = array_values( array_map( static fn ( Webhook_Subscription $subscription ): array => $subscription->to_array(), $subscriptions ) );

		// update_option() reports false for an identical value, which is a successful no-op.
		return Storage::get_option( $key, null ) === $payload || Storage::update_option( $key, $payload );
	}
}
