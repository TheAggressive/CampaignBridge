<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed port signatures are the contract.
/**
 * Remote draft mutation port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and fills one remote draft. It never schedules or sends.
 *
 * Adapters receive decrypted settings for one call only, must not retry a
 * non-idempotent create, and return only normalized outcomes.
 */
interface Provider_Draft_Gateway {
	public function slug(): string;

	/** @param array<string, mixed> $settings Decrypted provider settings. */
	public function create_draft( array $settings, Draft_Content $content ): Draft_Outcome;

	/**
	 * Re-upload content to an existing draft. Idempotent by contract.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function upload_content( array $settings, string $remote_id, Draft_Content $content ): Draft_Outcome;
}
