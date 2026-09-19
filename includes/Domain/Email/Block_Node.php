<?php
/**
 * Immutable normalized email block node.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents one validated position in the email block tree.
 */
final class Block_Node {
	/**
	 * Create an immutable block node.
	 *
	 * @param string                 $name       Stable block name.
	 * @param array<string, mixed>   $attributes Semantic block attributes.
	 * @param array<int, Block_Node> $children   Normalized child nodes.
	 * @param string                 $path       Stable diagnostic path.
	 * @param string                 $inner_html Known serialized HTML fragment, when supplied by WordPress.
	 * @throws \InvalidArgumentException When the block name is empty.
	 */
	public function __construct(
		private readonly string $name,
		private readonly array $attributes,
		private readonly array $children,
		private readonly string $path,
		private readonly string $inner_html = ''
	) {
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Block name cannot be empty.' );
		}
	}

	/** Get the stable block name. */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Get semantic attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function attributes(): array {
		return $this->attributes;
	}

	/**
	 * Get normalized child nodes.
	 *
	 * @return array<int, Block_Node>
	 */
	public function children(): array {
		return $this->children;
	}

	/** Get the stable diagnostic path. */
	public function path(): string {
		return $this->path;
	}

	/** Get the known serialized HTML fragment for block-specific normalization. */
	public function inner_html(): string {
		return $this->inner_html;
	}

	/**
	 * Return a copy with normalized attributes.
	 *
	 * The copy does not retain serialized markup: once a block is normalized,
	 * only its semantic attributes are canonical.
	 *
	 * @param array<string, mixed> $attributes Normalized attributes.
	 */
	public function with_attributes( array $attributes ): self {
		return new self( $this->name, $attributes, $this->children, $this->path );
	}

	/**
	 * Get deterministic fingerprint data.
	 *
	 * @return array<string, mixed>
	 */
	public function fingerprint_payload(): array {
		return array(
			'name'       => $this->name,
			'attributes' => $this->attributes,
			'children'   => array_map(
				static fn ( self $child ): array => $child->fingerprint_payload(),
				$this->children
			),
		);
	}
}
