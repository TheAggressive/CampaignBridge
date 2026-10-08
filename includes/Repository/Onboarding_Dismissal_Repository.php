<?php
/**
 * Per-user record that the onboarding checklist was dismissed.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores one prefixed flag per user in user meta, removed on uninstall.
 */
final class Onboarding_Dismissal_Repository {
	/** User meta key, prefixed by Storage. */
	public const META_KEY = 'onboarding_dismissed';

	/**
	 * Whether the user dismissed the checklist.
	 *
	 * @param int $user_id User.
	 */
	public function is_dismissed( int $user_id ): bool {
		return 0 < $user_id && '1' === (string) Storage::get_user_meta( $user_id, self::META_KEY, true );
	}

	/**
	 * Record that the user dismissed the checklist.
	 *
	 * @param int $user_id User.
	 */
	public function dismiss( int $user_id ): void {
		if ( 0 < $user_id ) {
			Storage::update_user_meta( $user_id, self::META_KEY, '1' );
		}
	}
}
