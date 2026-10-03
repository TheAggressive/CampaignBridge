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
 * Only the facts delivery depends on are kept: its lifecycle status, the
 * audience it targets, and whether a segment narrows or changes who
 * receives it. Provider response shapes never leave the adapter.
 */
final class Remote_Draft_State {
	public const DRAFT     = 'draft';
	public const SCHEDULED = 'scheduled';
	public const SENDING   = 'sending';
	public const SENT      = 'sent';
	public const OTHER     = 'other';

	private function __construct(
		private readonly string $status,
		private readonly string $audience_id,
		private readonly bool $segmented
	) {}

	public static function create( string $status, string $audience_id, bool $segmented ): self {
		if ( ! in_array( $status, array( self::DRAFT, self::SCHEDULED, self::SENDING, self::SENT, self::OTHER ), true ) ) {
			throw new \InvalidArgumentException( 'Remote draft status is invalid.' );
		}

		return new self( $status, $audience_id, $segmented );
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

	/** Whether the remote draft targets exactly the approved audience and is still unsent. */
	public function matches( string $audience_id ): bool {
		return self::DRAFT === $this->status && ! $this->segmented && hash_equals( $audience_id, $this->audience_id );
	}
}
