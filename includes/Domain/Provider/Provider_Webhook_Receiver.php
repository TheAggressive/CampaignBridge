<?php
/**
 * Inbound webhook verification port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Webhook\Signed_Delivery;
use CampaignBridge\Domain\Webhook\Webhook_Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticates one provider's webhook deliveries and reduces them to the
 * campaign they are about.
 *
 * The body is authenticated as the exact bytes received before anything is
 * read from it. Provider payload shapes stay inside the adapter.
 */
interface Provider_Webhook_Receiver {
	/**
	 * The provider this receiver understands.
	 */
	public function slug(): string;

	/**
	 * Prove a delivery was signed with one of the secrets and is still fresh.
	 *
	 * @param string                $raw_body The exact request body.
	 * @param array<string, string> $headers  Request headers, lowercase with underscores.
	 * @param array<int, string>    $secrets  Plaintext signing secrets for this provider.
	 * @param int                   $now      Current Unix time.
	 * @return Signed_Delivery|null Null when no secret signed it or it is not fresh.
	 */
	public function authenticate( string $raw_body, array $headers, array $secrets, int $now ): ?Signed_Delivery;

	/**
	 * What an authenticated delivery is about.
	 *
	 * @param string $raw_body The exact request body.
	 * @return Webhook_Event|null Null when the body is malformed.
	 */
	public function event( string $raw_body ): ?Webhook_Event;
}
