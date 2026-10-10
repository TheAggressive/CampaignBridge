<?php
/**
 * Remote test-delivery port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends one test of an existing remote draft. It never schedules or sends
 * to the campaign audience.
 *
 * Adapters receive decrypted settings and recipients for one call only,
 * must not retry the send, and return only normalized outcomes.
 */
interface Provider_Test_Gateway {
	/**
	 * The provider this gateway talks to.
	 */
	public function slug(): string;

	/**
	 * Send one test of the draft.
	 *
	 * @param array<string, mixed> $settings  Decrypted provider settings.
	 * @param string               $remote_id The provider's campaign ID.
	 * @param Test_Delivery        $delivery  The test delivery request.
	 */
	public function send_test( array $settings, string $remote_id, Test_Delivery $delivery ): Action_Outcome;
}
