<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Normalized audience merge field.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One audience-scoped personalization field a provider can substitute.
 *
 * Merge fields are provider- and audience-scoped. They never become portable
 * CampaignBridge tokens; token mapping decides which canonical tokens they
 * can satisfy.
 */
final class Discovered_Merge_Field implements Discovered_Item {
	private function __construct(
		private readonly string $tag,
		private readonly string $name,
		private readonly string $type,
		private readonly bool $required
	) {}

	public static function create( mixed $tag, mixed $name, mixed $type, mixed $required ): self {
		if ( ! is_string( $tag ) || 1 !== preg_match( '/^[A-Z][A-Z0-9_]{0,49}$/', $tag ) ) {
			throw new \InvalidArgumentException( 'Merge field tag must be an uppercase identifier of at most 50 characters.' );
		}
		if ( ! is_string( $type ) || 1 !== preg_match( '/^[a-z][a-z_]{0,31}$/', $type ) ) {
			throw new \InvalidArgumentException( 'Merge field type must be a lowercase identifier.' );
		}
		if ( ! is_bool( $required ) ) {
			throw new \InvalidArgumentException( 'Merge field required flag must be boolean.' );
		}

		return new self( $tag, Discovery_Values::label( $name, 'Merge field name' ), $type, $required );
	}

	/** @param array<string, mixed> $data Stored merge field. */
	public static function from_array( array $data ): self {
		return self::create( $data['tag'] ?? null, $data['name'] ?? null, $data['type'] ?? null, $data['required'] ?? null );
	}

	public function tag(): string {
		return $this->tag;
	}

	public function name(): string {
		return $this->name;
	}

	public function type(): string {
		return $this->type;
	}

	public function required(): bool {
		return $this->required;
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'tag'      => $this->tag,
			'name'     => $this->name,
			'type'     => $this->type,
			'required' => $this->required,
		);
	}
}
