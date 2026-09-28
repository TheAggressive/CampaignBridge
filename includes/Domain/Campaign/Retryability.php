<?php // phpcs:disable Generic.Commenting.DocComment.MissingShort -- Stable vocabulary is documented by the enclosing type and constant names.
/**
 * Delivery attempt retryability classification.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Storage classification only; it does not trigger retry behavior. */
final class Retryability {
	public const UNKNOWN       = 'unknown';
	public const RETRYABLE     = 'retryable';
	public const NOT_RETRYABLE = 'not_retryable';

	/** @return array<int, string> */
	public static function all(): array {
		return array( self::UNKNOWN, self::RETRYABLE, self::NOT_RETRYABLE );
	}
}
