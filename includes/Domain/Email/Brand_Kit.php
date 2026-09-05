<?php
/**
 * Stored email brand kit.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The semantic colour slots an email template is built from.
 *
 * Slugs stay stable so saved blocks keep saying `brand` after a theme
 * import. Only portable six-digit hex values are allowed. Missing slots
 * fall back to Design_Presets so a partial kit never leaves the compiler
 * without a colour.
 */
final class Brand_Kit {
	public const VERSION = 1;

	public const SOURCE_DEFAULTS = 'defaults';
	public const SOURCE_CUSTOM   = 'custom';
	public const SOURCE_THEME    = 'theme';

	public const SLOT_TEXT       = 'text';
	public const SLOT_SECONDARY  = 'secondary';
	public const SLOT_BACKGROUND = 'background';
	public const SLOT_CARD       = 'card';
	public const SLOT_BORDER     = 'border';
	public const SLOT_BRAND      = 'brand';
	public const SLOT_ON_BRAND   = 'on-brand';

	/**
	 * Slot order matches Design_Presets so the editor and settings stay aligned.
	 *
	 * @var array<int, string>
	 */
	public const SLOTS = array(
		self::SLOT_TEXT,
		self::SLOT_SECONDARY,
		self::SLOT_BACKGROUND,
		self::SLOT_CARD,
		self::SLOT_BORDER,
		self::SLOT_BRAND,
		self::SLOT_ON_BRAND,
	);

	/**
	 * Create a kit from already-validated slot colours.
	 *
	 * @param array<string, string> $colors            Slot slug to six-digit hex.
	 * @param string                $source            How the kit was last written.
	 * @param string|null           $theme_fingerprint Hash of the imported theme slice.
	 */
	private function __construct(
		private readonly array $colors,
		private readonly string $source,
		private readonly ?string $theme_fingerprint
	) {}

	/**
	 * The hardcoded CampaignBridge defaults.
	 */
	public static function defaults(): self {
		$colors = array();

		foreach ( Design_Presets::colors() as $preset ) {
			$colors[ $preset['slug'] ] = $preset['color'];
		}

		return new self( $colors, self::SOURCE_DEFAULTS, null );
	}

	/**
	 * Rebuild a kit from stored or posted data.
	 *
	 * @param array<string, mixed> $data Stored kit array.
	 * @throws \InvalidArgumentException When an explicit colour is not portable.
	 */
	public static function from_array( array $data ): self {
		$source = isset( $data['source'] ) && is_string( $data['source'] )
			? $data['source']
			: self::SOURCE_CUSTOM;

		if ( ! in_array( $source, array( self::SOURCE_DEFAULTS, self::SOURCE_CUSTOM, self::SOURCE_THEME ), true ) ) {
			throw new \InvalidArgumentException( 'Brand kit source is not recognised.' );
		}

		$fingerprint = isset( $data['theme_fingerprint'] ) && is_string( $data['theme_fingerprint'] ) && '' !== $data['theme_fingerprint']
			? $data['theme_fingerprint']
			: null;

		$posted = isset( $data['colors'] ) && is_array( $data['colors'] ) ? $data['colors'] : $data;

		return self::from_colors( $posted, $source, $fingerprint );
	}

	/**
	 * Overlay slot colours onto the defaults.
	 *
	 * @param array<string, mixed> $colors      Slot slug to colour.
	 * @param string               $source      How the kit was last written.
	 * @param string|null          $fingerprint Imported theme hash.
	 * @throws \InvalidArgumentException When an explicit colour is not portable.
	 */
	public static function from_colors( array $colors, string $source = self::SOURCE_CUSTOM, ?string $fingerprint = null ): self {
		$merged = self::defaults()->colors;

		foreach ( $colors as $slug => $value ) {
			if ( ! is_string( $slug ) || ! in_array( $slug, self::SLOTS, true ) ) {
				continue;
			}

			$hex = self::normalize_hex( $value );
			if ( null === $hex ) {
				throw new \InvalidArgumentException( sprintf( 'Brand kit colour %s must be a six-digit hexadecimal value.', $slug ) );
			}

			$merged[ $slug ] = $hex;
		}

		return new self( $merged, $source, $fingerprint );
	}

	/**
	 * Expand a 3- or 6-digit hex colour, or reject it.
	 *
	 * @param mixed $value Raw colour.
	 */
	public static function normalize_hex( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );

		if ( 1 === preg_match( '/^#([0-9a-f]{3})$/i', $value, $matches ) ) {
			$digit = strtolower( $matches[1] );

			return sprintf(
				'#%1$s%1$s%2$s%2$s%3$s%3$s',
				$digit[0],
				$digit[1],
				$digit[2]
			);
		}

		if ( 1 === preg_match( '/^#([0-9a-f]{6})$/i', $value ) ) {
			return strtolower( $value );
		}

		return null;
	}

	/**
	 * Resolve one slot to its portable hex.
	 *
	 * @param string $slug Slot slug.
	 */
	public function color( string $slug ): ?string {
		return $this->colors[ $slug ] ?? null;
	}

	/**
	 * Colours in the editor preset shape.
	 *
	 * @return array<int, array{slug: string, name: string, color: string}>
	 */
	public function colors(): array {
		$names = array();
		foreach ( Design_Presets::colors() as $preset ) {
			$names[ $preset['slug'] ] = $preset['name'];
		}

		$palette = array();
		foreach ( self::SLOTS as $slug ) {
			$palette[] = array(
				'slug'  => $slug,
				'name'  => $names[ $slug ] ?? $slug,
				'color' => $this->colors[ $slug ],
			);
		}

		return $palette;
	}

	/**
	 * How the kit was last written.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Hash of the theme slice that last filled this kit.
	 */
	public function theme_fingerprint(): ?string {
		return $this->theme_fingerprint;
	}

	/**
	 * Persistable array.
	 *
	 * @return array{version: int, source: string, theme_fingerprint: string|null, colors: array<string, string>}
	 */
	public function to_array(): array {
		return array(
			'version'           => self::VERSION,
			'source'            => $this->source,
			'theme_fingerprint' => $this->theme_fingerprint,
			'colors'            => $this->colors,
		);
	}
}
