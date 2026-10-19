<?php
/**
 * A webhook the provider just created.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The provider's ID for a new webhook and the secret it will sign
 * deliveries with.
 *
 * The provider shows the secret only once. It is plaintext here, for the
 * registrar to encrypt immediately; it is never logged or returned to a
 * browser.
 */
final class Webhook_Created {
	/**
	 * Build the result.
	 *
	 * @param string $remote_id      The provider's webhook ID.
	 * @param string $signing_secret Plaintext signing secret.
	 */
	public function __construct(
		private readonly string $remote_id,
		#[\SensitiveParameter] private readonly string $signing_secret
	) {}

	/**
	 * The provider's webhook ID.
	 */
	public function remote_id(): string {
		return $this->remote_id;
	}

	/**
	 * Plaintext signing secret.
	 */
	public function signing_secret(): string {
		return $this->signing_secret;
	}
}
