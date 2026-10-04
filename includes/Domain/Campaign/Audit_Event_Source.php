<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed persistence ports use explicit signatures and focused contract comments.
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
	public function get( string $id ): ?Audit_Event;

	/** Append only; existing event identities are never replaced. */
	public function add( Audit_Event $event ): bool;

	/** @return array<int, Audit_Event> */
	public function for_target( string $target_type, string $target_id, int $limit = 100, int $offset = 0 ): array;

	/** Number of events recorded for one target. */
	public function count_for_target( string $target_type, string $target_id ): int;
}
