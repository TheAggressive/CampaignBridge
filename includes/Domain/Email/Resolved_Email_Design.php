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
}
