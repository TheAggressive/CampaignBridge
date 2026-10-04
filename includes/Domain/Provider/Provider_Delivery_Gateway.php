<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed port signatures are the contract.
/**
 * Remote delivery mutation port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules, unschedules, or immediately sends an existing remote draft.
 *
 * Every method is a separate operation that may reach the campaign
 * audience. Adapters receive decrypted settings for one call only, must not
 * retry, and return only normalized outcomes.
 */
interface Provider_Delivery_Gateway {
	public function slug(): string;

	/** Minutes between the delivery times the provider accepts; 1 means any minute. */
	public function schedule_interval_minutes(): int;

	/**
	 * Schedule the remote draft to send to its audience at one UTC time.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function schedule( array $settings, string $remote_id, string $scheduled_for ): Action_Outcome;

	/**
	 * Return a scheduled remote campaign to an unscheduled draft before it sends.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function unschedule( array $settings, string $remote_id ): Action_Outcome;

	/**
	 * Send the remote draft to its audience now. Irreversible once accepted.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function send( array $settings, string $remote_id ): Action_Outcome;
}
