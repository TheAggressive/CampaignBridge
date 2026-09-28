<?php
/**
 * Immutable normalized email design.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Runtime design truth shared by editor and compiler consumers. */
final class Resolved_Email_Design {
	/**
	 * Restore an exact normalized design and verify its identity.
	 *
	 * @param array<string, mixed> $design      Stored normalized design.
	 * @param string               $fingerprint Stored deterministic identity.
	 *
	 * @throws \InvalidArgumentException When the stored design is malformed or its identity differs.
	 */
	public static function from_array( array $design, string $fingerprint ): self {
		$required_arrays = array(
			$design['settings'] ?? null,
			$design['styles'] ?? null,
			$design['brand'] ?? null,
			$design['settings']['layout'] ?? null,
			$design['settings']['color'] ?? null,
			$design['settings']['typography'] ?? null,
			$design['settings']['spacing'] ?? null,
			$design['styles']['global'] ?? null,
			$design['styles']['blocks'] ?? null,
			$design['brand']['fonts'] ?? null,
		);
		if ( 1 !== ( $design['version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Stored email design version is unsupported.' );
		}
		foreach ( $required_arrays as $value ) {
			if ( ! is_array( $value ) ) {
				throw new \InvalidArgumentException( 'Stored email design is malformed.' );
			}
		}
		$normalized = self::canonicalize( $design );
		try {
			$json = json_encode( $normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain identity verification requires throwing, deterministic JSON.
		} catch ( \JsonException $exception ) {
			throw new \InvalidArgumentException( 'Stored email design cannot be serialized canonically.', 0, $exception );
		}
		$actual = 'sha256:' . hash( 'sha256', $json );
		if ( ! hash_equals( $actual, $fingerprint ) ) {
			throw new \InvalidArgumentException( 'Stored email design fingerprint does not match its data.' );
		}

		return new self( $design, $fingerprint );
	}
	/**
	 * Create an immutable resolved design.
	 *
	 * @param array<string, mixed> $design      Canonical normalized design.
	 * @param string               $fingerprint Deterministic design identity.
	 */
	public function __construct(
		private readonly array $design,
		private readonly string $fingerprint
	) {}

	/** Public manifest contract version. */
	public function version(): int {
		return $this->design['version'];
	}

	/** Resolved content width in whole pixels. */
	public function content_width(): int {
		return $this->design['settings']['layout']['contentWidth'];
	}

	/**
	 * Get the resolved palette.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function colors(): array {
		return $this->design['settings']['color']['palette'];
	}

	/**
	 * Get resolved font choices.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function font_families(): array {
		return $this->design['settings']['typography']['fontFamilies'];
	}

	/**
	 * Get resolved font-size presets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function font_sizes(): array {
		return $this->design['settings']['typography']['fontSizes'];
	}

	/**
	 * Get resolved spacing presets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function spacing_sizes(): array {
		return $this->design['settings']['spacing']['spacingSizes'];
	}

	/** Whether arbitrary colors are offered by the authoring surface. */
	public function allows_custom_colors(): bool {
		return $this->design['settings']['color']['custom'];
	}

	/** Whether arbitrary font sizes are offered by the authoring surface. */
	public function allows_custom_font_sizes(): bool {
		return $this->design['settings']['typography']['customFontSize'];
	}

	/** Whether arbitrary spacing is offered by the authoring surface. */
	public function allows_custom_spacing(): bool {
		return $this->design['settings']['spacing']['custom'];
	}

	/**
	 * Get global design defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function global_style(): array {
		return $this->design['styles']['global'];
	}

	/**
	 * Get defaults for one block.
	 *
	 * @param string $block_name Registered block name.
	 * @return array<string, mixed>
	 */
	public function block_style( string $block_name ): array {
		$style = $this->design['styles']['blocks'][ $block_name ] ?? array();
		return is_array( $style ) ? $style : array();
	}

	/**
	 * Get semantic Brand Kit font assignments.
	 *
	 * @return array<string, string>
	 */
	public function brand_fonts(): array {
		return $this->design['brand']['fonts'];
	}

	/**
	 * Resolve a canonical font preset from this design.
	 *
	 * @param string $slug Stable font preset slug.
	 * @return array<string, mixed>|null
	 */
	public function font( string $slug ): ?array {
		foreach ( $this->font_families() as $font ) {
			if ( $slug === $font['slug'] ) {
				return $font;
			}
		}

		return null;
	}

	/**
	 * Resolve one semantic Brand Kit font slot from this design.
	 *
	 * @param string $slot heading, body, or button.
	 * @return array<string, mixed>
	 */
	public function font_for_slot( string $slot ): array {
		$fonts = $this->brand_fonts();
		$slug  = is_string( $fonts[ $slot ] ?? null ) ? $fonts[ $slot ] : '';
		$font  = $this->font( $slug );

		return $font ?? Design_Presets::default_font();
	}

	/** Deterministic identity for every output-affecting design input. */
	public function fingerprint(): string {
		return $this->fingerprint;
	}

	/**
	 * Export the canonical runtime representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->design;
	}

	/**
	 * Recursively canonicalize associative maps for identity verification.
	 *
	 * @param mixed $value Value to canonicalize.
	 */
	private static function canonicalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}
}
