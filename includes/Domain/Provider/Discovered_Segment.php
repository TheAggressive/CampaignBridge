<?php
/**
 * Normalized audience segment or tag.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One audience subset a campaign may target: a saved segment or a tag.
 */
final class Discovered_Segment implements Discovered_Item {
	public const KIND_SEGMENT = 'segment';
	public const KIND_TAG     = 'tag';

	/**
	 * Build the discovered segment.
	 *
	 * @param string   $id           Provider ID.
	 * @param string   $name         Display name.
	 * @param string   $kind         Segment or tag.
	 * @param int|null $member_count Number of contacts, when known.
	 */
	private function __construct(
		private readonly string $id,
		private readonly string $name,
		private readonly string $kind,
		private readonly ?int $member_count
	) {}

	/**
	 * Validate one segment or tag from provider values.
	 *
	 * @param mixed $id           Provider ID.
	 * @param mixed $name         Display name.
	 * @param mixed $kind         Segment or tag.
	 * @param mixed $member_count Number of contacts, when known.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function create( mixed $id, mixed $name, mixed $kind, mixed $member_count = null ): self {
		if ( ! in_array( $kind, array( self::KIND_SEGMENT, self::KIND_TAG ), true ) ) {
			throw new \InvalidArgumentException( 'Segment kind must be segment or tag.' );
		}

		return new self(
			Discovery_Values::remote_id( $id, 'Segment ID' ),
			Discovery_Values::label( $name, 'Segment name' ),
			$kind,
			Discovery_Values::count( $member_count, 'Segment member count' )
		);
	}

	/**
	 * Rebuild a segment from its cached values.
	 *
	 * @param array<string, mixed> $data Stored segment.
	 */
	public static function from_array( array $data ): self {
		return self::create( $data['id'] ?? null, $data['name'] ?? null, $data['kind'] ?? null, $data['member_count'] ?? null );
	}

	/**
	 * The segment's ID.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The segment's name.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * The segment's kind.
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * The segment's member count.
	 */
	public function member_count(): ?int {
		return $this->member_count;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'name'         => $this->name,
			'kind'         => $this->kind,
			'member_count' => $this->member_count,
		);
	}
}
