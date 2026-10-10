<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port methods are documented by their contract below.
/**
 * Expiring named lock storage port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Lock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores expiring locks and decides who holds each one.
 *
 * At most one owner holds a name until its expiry. A lock whose expiry has
 * passed may be taken over, and only its current owner can release it.
 */
interface Lock_Source {
	public function acquire( string $name, string $owner, string $purpose, string $until, string $now ): Lock_Acquisition;

	public function release( string $name, string $owner ): bool;
}
