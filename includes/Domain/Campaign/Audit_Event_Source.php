<?php
/**
 * Audit event persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Appends bounded, redacted operator/security history. */
interface Audit_Event_Source {
	/**
	 * One audit event by ID.
	 *
	 * @param string $id Record ID.
	 */
	public function get( string $id ): ?Audit_Event;

	/**
	 * Append only; existing event identities are never replaced.
	 *
	 * @param Audit_Event $event Audit event.
	 */
	public function add( Audit_Event $event ): bool;

	/**
	 * A page of one target's events, newest write first.
	 *
	 * @param string $target_type Kind of record the event is about.
	 * @param string $target_id   ID of the record the event is about.
	 * @param int    $limit       Maximum number of records.
	 * @param int    $offset      Number of records to skip.
	 * @return array<int, Audit_Event>
	 */
	public function for_target( string $target_type, string $target_id, int $limit = 100, int $offset = 0 ): array;

	/**
	 * Number of events recorded for one target.
	 *
	 * @param string $target_type Kind of record the event is about.
	 * @param string $target_id   ID of the record the event is about.
	 */
	public function count_for_target( string $target_type, string $target_id ): int;
}
