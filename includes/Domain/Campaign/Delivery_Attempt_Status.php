<?php
/**
 * Delivery attempt result states.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Separates pending, known failure, success, and ambiguous remote outcomes. */
final class Delivery_Attempt_Status {
	public const PENDING   = 'pending';
	public const SUCCEEDED = 'succeeded';
	public const FAILED    = 'failed';
	public const UNKNOWN   = 'unknown';

	/**
	 * Every attempt status.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::PENDING, self::SUCCEEDED, self::FAILED, self::UNKNOWN );
	}
}
