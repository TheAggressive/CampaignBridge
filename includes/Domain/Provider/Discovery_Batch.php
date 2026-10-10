<?php
/**
 * Items returned by one provider discovery call.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized, bounded output of one adapter call before it is timestamped.
 *
 * `complete` is false when the provider holds more items than the bounded
 * request returned, so callers never mistake a truncated list for the whole.
 */
final class Discovery_Batch {
	/** Maximum items kept from one discovery. */
	public const MAX_ITEMS = 1000;

	/**
	 * Build the discovery batch.
	 *
	 * @param string                      $kind     Discovery kind.
	 * @param array<int, Discovered_Item> $items    Normalized items.
	 * @param bool                        $complete Whether the provider returned every item.
	 */
	private function __construct(
		private readonly string $kind,
		private readonly array $items,
		private readonly bool $complete
	) {}

	/**
	 * A bounded batch of items of one kind.
	 *
	 * @param string                      $kind     Discovery kind.
	 * @param array<int, Discovered_Item> $items    Normalized items.
	 * @param bool                        $complete Whether the provider returned every item.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function create( string $kind, array $items, bool $complete ): self {
		Discovery_Kind::operation( $kind );
		if ( self::MAX_ITEMS < count( $items ) ) {
			throw new \InvalidArgumentException( sprintf( 'A discovery may keep at most %d items.', self::MAX_ITEMS ) );
		}
		foreach ( $items as $item ) {
			if ( ! $item instanceof Discovered_Item || ! Discovery_Kind::accepts( $kind, $item ) ) {
				throw new \InvalidArgumentException( 'Discovery item does not match its kind.' );
			}
		}

		return new self( $kind, array_values( $items ), $complete );
	}

	/**
	 * The batch's kind.
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * The batch's items.
	 *
	 * @return array<int, Discovered_Item>
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Whether the batch is complete.
	 */
	public function is_complete(): bool {
		return $this->complete;
	}
}
