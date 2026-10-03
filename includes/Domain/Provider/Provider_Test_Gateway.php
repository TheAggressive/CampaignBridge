<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed port signatures are the contract.
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
	public function slug(): string;

	/** @param array<string, mixed> $settings Decrypted provider settings. */
	public function send_test( array $settings, string $remote_id, Test_Delivery $delivery ): Test_Outcome;
}
