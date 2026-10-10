<?php
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
	/**
	 * Acquire a named lock, or take it over once it has expired.
	 *
	 * @param string $name    Lock name.
	 * @param string $owner   Lease or lock owner identity.
	 * @param string $purpose What the lock holder is doing.
	 * @param string $until   UTC time the lock expires.
	 * @param string $now     Current UTC timestamp.
	 */
	public function acquire( string $name, string $owner, string $purpose, string $until, string $now ): Lock_Acquisition;

	/**
	 * Release a lock the owner holds.
	 *
	 * @param string $name  Lock name.
	 * @param string $owner Lease or lock owner identity.
	 */
	public function release( string $name, string $owner ): bool;
}
