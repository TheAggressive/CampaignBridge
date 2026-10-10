<?php
/**
 * The result of asking for a lock.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Lock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the lock was acquired and, when it was taken over from a holder
 * whose lease ran out, what that holder had been doing. When the lock is
 * held elsewhere, `held_until` says when it expires.
 */
final class Lock_Acquisition {
	/**
	 * Describe one acquisition attempt.
	 *
	 * @param bool        $acquired            Whether the caller now holds the lock.
	 * @param string|null $interrupted_purpose The expired holder's purpose, on a takeover.
	 * @param string|null $held_until          When the current holder's lock expires, when refused.
	 */
	public function __construct(
		public readonly bool $acquired,
		public readonly ?string $interrupted_purpose = null,
		public readonly ?string $held_until = null
	) {}
}
