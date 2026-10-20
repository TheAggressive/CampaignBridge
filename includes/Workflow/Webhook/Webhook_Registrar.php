<?php
/**
 * Enable, rotate, and disable provider webhooks.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Webhook;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Provider\Provider_Webhook_Gateway;
use CampaignBridge\Domain\Webhook\Webhook_Subscription;
use CampaignBridge\Domain\Webhook\Webhook_Subscription_Source;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps one signed campaign-events webhook on every provider audience.
 *
 * This is an operator action of its own, never a side effect of creating,
 * scheduling, or sending a campaign. Each audience is handled on its own
 * and saved as soon as it changes, so repeating an action finishes what an
 * interrupted one left. Before a webhook is created, any webhook already
 * pointing at this site is removed: an earlier creation whose response was
 * lost left one whose secret was never stored, and it could not be
 * authenticated. Rotating replaces every webhook, and so every secret.
 */
final class Webhook_Registrar {
	/**
	 * Build the registrar.
	 *
	 * @param Provider_Webhook_Gateway    $gateway       Provider webhook adapter.
	 * @param Webhook_Subscription_Source $subscriptions Stored webhooks.
	 * @param \Closure                    $settings      Decrypted provider settings for one action; empty when not connected.
	 * @param \Closure                    $audiences     The provider's audience IDs for settings, or a normalized error.
	 * @param \Closure                    $encrypt       Encrypts one signing secret for storage.
	 * @param string                      $url           This site's webhook URL for the provider.
	 * @param Campaign_Clock              $clock         Source of the current time.
	 * @phpstan-param \Closure(): array<string, mixed> $settings
	 * @phpstan-param \Closure(array<string, mixed>): (array<int, string>|Provider_Error) $audiences
	 * @phpstan-param \Closure(string): string $encrypt
	 */
	public function __construct(
		private readonly Provider_Webhook_Gateway $gateway,
		private readonly Webhook_Subscription_Source $subscriptions,
		private readonly \Closure $settings,
		private readonly \Closure $audiences,
		private readonly \Closure $encrypt,
		private readonly string $url,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * The webhooks stored now, keyed by audience.
	 *
	 * @return array<string, Webhook_Subscription>
	 */
	public function subscriptions(): array {
		return $this->subscriptions->all( $this->gateway->slug() );
	}

	/**
	 * This site's webhook URL.
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Create a webhook on every audience that does not have one.
	 */
	public function enable(): Webhook_Registration_Result {
		$settings = ( $this->settings )();
		if ( array() === $settings ) {
			return Webhook_Registration_Result::not_connected();
		}
		$audiences = ( $this->audiences )( $settings );
		if ( $audiences instanceof Provider_Error ) {
			return Webhook_Registration_Result::failed( $audiences );
		}

		$stored  = $this->subscriptions();
		$changed = 0;
		$failed  = 0;
		$first   = null;
		foreach ( $audiences as $audience_id ) {
			if ( isset( $stored[ $audience_id ] ) ) {
				continue;
			}
			$created = $this->create( $settings, $audience_id );
			if ( true === $created ) {
				++$changed;
				continue;
			}
			++$failed;
			$first ??= $created instanceof Provider_Error ? $created : null;
		}
		unset( $settings );

		return Webhook_Registration_Result::completed( $changed, $failed, $first );
	}

	/**
	 * Remove every stored webhook from the provider, then forget it.
	 *
	 * A webhook the provider no longer has counts as removed. One that could
	 * not be removed stays stored, so its deliveries are still authenticated
	 * and the action can be repeated.
	 */
	public function disable(): Webhook_Registration_Result {
		$settings = ( $this->settings )();
		if ( array() === $settings ) {
			return Webhook_Registration_Result::not_connected();
		}

		$changed = 0;
		$failed  = 0;
		$first   = null;
		foreach ( $this->subscriptions() as $subscription ) {
			$error = $this->gateway->remove( $settings, $subscription->audience_id(), $subscription->remote_id() );
			if ( null === $error && $this->subscriptions->remove( $subscription->provider(), $subscription->audience_id() ) ) {
				++$changed;
				continue;
			}
			++$failed;
			$first ??= $error;
		}
		unset( $settings );

		return Webhook_Registration_Result::completed( $changed, $failed, $first );
	}

	/**
	 * Replace every webhook, and with it every signing secret.
	 */
	public function rotate(): Webhook_Registration_Result {
		$disabled = $this->disable();
		if ( ! $disabled->is_success() ) {
			return $disabled;
		}

		return $this->enable();
	}

	/**
	 * Forget every stored webhook without asking the provider.
	 *
	 * Used when the connection is being removed and its key may no longer
	 * work. Deliveries to webhooks left at the provider are refused from
	 * then on.
	 */
	public function forget(): void {
		foreach ( $this->subscriptions() as $subscription ) {
			$this->subscriptions->remove( $subscription->provider(), $subscription->audience_id() );
		}
	}

	/**
	 * Create and store the webhook for one audience.
	 *
	 * @param array<string, mixed> $settings    Decrypted provider settings.
	 * @param string               $audience_id The provider's audience ID.
	 * @return Provider_Error|bool True when the webhook is stored, false when it could not be stored locally.
	 */
	private function create( array $settings, string $audience_id ): Provider_Error|bool {
		$existing = $this->gateway->find( $settings, $audience_id, $this->url );
		if ( $existing instanceof Provider_Error ) {
			return $existing;
		}
		foreach ( $existing as $remote_id ) {
			$error = $this->gateway->remove( $settings, $audience_id, $remote_id );
			if ( null !== $error ) {
				return $error;
			}
		}

		$created = $this->gateway->create( $settings, $audience_id, $this->url );
		if ( $created instanceof Provider_Error ) {
			return $created;
		}
		try {
			$subscription = Webhook_Subscription::create(
				$this->gateway->slug(),
				$audience_id,
				$created->remote_id(),
				( $this->encrypt )( $created->signing_secret() ),
				$this->clock->now()
			);
		} catch ( \Throwable ) {
			$subscription = null;
		}
		if ( null === $subscription || ! $this->subscriptions->save( $subscription ) ) {
			// A webhook whose secret is not stored cannot be authenticated; take it back down.
			$this->gateway->remove( $settings, $audience_id, $created->remote_id() );

			return false;
		}

		return true;
	}
}
