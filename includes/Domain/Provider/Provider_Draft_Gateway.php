<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed port signatures are the contract.
/**
 * Remote draft mutation port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, re-asserts, and inspects one remote draft. It never schedules or
 * sends.
 *
 * Adapters receive decrypted settings for one call only, must not retry a
 * non-idempotent create, and return only normalized outcomes.
 */
interface Provider_Draft_Gateway {
	public function slug(): string;

	/** @param array<string, mixed> $settings Decrypted provider settings. */
	public function create_draft( array $settings, Draft_Content $content ): Draft_Outcome;

	/**
	 * Overwrite an existing draft's audience, envelope, and content with the
	 * approved values. Idempotent by contract, so a failure is safe to repeat.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function sync_draft( array $settings, string $remote_id, Draft_Content $content ): Action_Outcome;

	/**
	 * Read what the provider holds for a draft, without changing it.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function inspect_draft( array $settings, string $remote_id ): Remote_Draft_State|Provider_Error;
}
