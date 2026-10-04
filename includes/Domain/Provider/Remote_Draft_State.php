<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Normalized observation of one remote draft.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the provider reports about a remote campaign before delivery.
 *
 * Only the facts delivery and reconciliation depend on are kept: its
 * lifecycle status, when it is scheduled or was sent, the audience it
 * targets, and whether a segment narrows or changes who receives it.
 * Provider response shapes never leave the adapter.
 */
final class Remote_Draft_State {
	public const DRAFT     = 'draft';
	public const SCHEDULED = 'scheduled';
	public const SENDING   = 'sending';
	public const SENT      = 'sent';
	public const CANCELED  = 'canceled';
	public const OTHER     = 'other';

	private function __construct(
		private readonly string $status,
		private readonly string $audience_id,
		private readonly bool $segmented,
		private readonly ?string $send_time
	) {}

	/** @param string|null $send_time Scheduled or actual send time as UTC `Y-m-d\TH:i:s\Z`, when known. */
	public static function create( string $status, string $audience_id, bool $segmented, ?string $send_time = null ): self {
		if ( ! in_array( $status, array( self::DRAFT, self::SCHEDULED, self::SENDING, self::SENT, self::CANCELED, self::OTHER ), true ) ) {
			throw new \InvalidArgumentException( 'Remote draft status is invalid.' );
		}
		if ( null !== $send_time && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $send_time ) ) {
			throw new \InvalidArgumentException( 'Remote send time must be a UTC timestamp.' );
		}

		return new self( $status, $audience_id, $segmented, $send_time );
	}

	public function status(): string {
		return $this->status;
	}

	public function audience_id(): string {
		return $this->audience_id;
	}

	public function is_segmented(): bool {
		return $this->segmented;
	}

	/** When the provider will send, or sent, the campaign; null when unknown or unscheduled. */
	public function send_time(): ?string {
		return $this->send_time;
	}

	/** Whether the remote draft targets exactly the approved audience and is still unsent. */
	public function matches( string $audience_id ): bool {
		return self::DRAFT === $this->status && ! $this->segmented && hash_equals( $audience_id, $this->audience_id );
	}
}
