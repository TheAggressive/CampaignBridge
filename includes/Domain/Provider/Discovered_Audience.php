<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Normalized provider audience.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One provider audience a campaign may target.
 *
 * Carries the opaque ID, display name, aggregate member count, and the
 * audience's default sender. Member records are never imported.
 */
final class Discovered_Audience implements Discovered_Item {
	private function __construct(
		private readonly string $id,
		private readonly string $name,
		private readonly ?int $member_count,
		private readonly ?Sender_Identity $default_sender
	) {}

	public static function create( mixed $id, mixed $name, mixed $member_count = null, ?Sender_Identity $default_sender = null ): self {
		return new self(
			Discovery_Values::remote_id( $id, 'Audience ID' ),
			Discovery_Values::label( $name, 'Audience name' ),
			Discovery_Values::count( $member_count, 'Audience member count' ),
			$default_sender
		);
	}

	/** @param array<string, mixed> $data Stored audience. */
	public static function from_array( array $data ): self {
		$sender = $data['default_sender'] ?? null;
		if ( null !== $sender && ! is_array( $sender ) ) {
			throw new \InvalidArgumentException( 'Default sender must be an object or null.' );
		}

		return self::create(
			$data['id'] ?? null,
			$data['name'] ?? null,
			$data['member_count'] ?? null,
			null === $sender ? null : Sender_Identity::from_array( $sender )
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}

	public function member_count(): ?int {
		return $this->member_count;
	}

	public function default_sender(): ?Sender_Identity {
		return $this->default_sender;
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'id'             => $this->id,
			'name'           => $this->name,
			'member_count'   => $this->member_count,
			'default_sender' => $this->default_sender?->to_array(),
		);
	}
}
