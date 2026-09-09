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
	 * The semantic type slots an email template is built from.
	 *
	 * @var array<int, string>
	 */
	public const FONT_SLOTS = array( 'heading', 'body', 'button' );

	/**
	 * Reserved slug identifying the brand kit's custom Google Font. A font slot
	 * (or a block's font choice) set to this slug resolves to the kit's
	 * resolved custom font, when one is configured.
	 */
	public const CUSTOM_FONT_SLUG = 'custom';

	/**
	 * The default font slug for every semantic type slot.
	 *
	 * @var array<string, string>
	 */
	public const FONT_DEFAULTS = array(
		'heading' => 'arial',
		'body'    => 'arial',
		'button'  => 'arial',
	);

	/**
	 * Create a kit from already-validated slot colours.
	 *
	 * @param array<string, string>     $colors            Slot slug to six-digit hex.
	 * @param string                    $source            How the kit was last written.
	 * @param string|null               $theme_fingerprint Hash of the imported theme slice.
	 * @param array<string, string>     $fonts             Slot slug to known font slug.
	 * @param array<string, mixed>|null $custom_font   Resolved custom Google Font snapshot.
	 */
	private function __construct(
		private readonly array $colors,
		private readonly string $source,
		private readonly ?string $theme_fingerprint,
		private readonly array $fonts,
		private readonly ?array $custom_font
	) {}

	/**
	 * The hardcoded CampaignBridge defaults.
	 */
	public static function defaults(): self {
		$colors = array();

		foreach ( Design_Presets::colors() as $preset ) {
			$colors[ $preset['slug'] ] = $preset['color'];
		}

		return new self( $colors, self::SOURCE_DEFAULTS, null, self::FONT_DEFAULTS, null );
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
		$fonts  = isset( $data['fonts'] ) && is_array( $data['fonts'] ) ? $data['fonts'] : null;

		$custom_font = isset( $data['custom_font'] ) ? $data['custom_font'] : null;

		return self::from_colors( $posted, $source, $fingerprint, $fonts, $custom_font );
	}

	/**
	 * Overlay slot colours onto the defaults.
	 *
	 * @param array<string, mixed>      $colors      Slot slug to colour.
	 * @param string                    $source      How the kit was last written.
	 * @param string|null               $fingerprint Imported theme hash.
	 * @param array<string, mixed>|null $fonts       Slot slug to font slug.
	 * @param array<string, mixed>|null $custom_font Resolved custom Google Font snapshot.
	 * @throws \InvalidArgumentException When an explicit colour is not portable.
	 */
	public static function from_colors( array $colors, string $source = self::SOURCE_CUSTOM, ?string $fingerprint = null, ?array $fonts = null, ?array $custom_font = null ): self {
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

		$custom = self::normalize_custom_font( $custom_font );

		return new self( $merged, $source, $fingerprint, self::resolve_fonts( $fonts, null !== $custom ), $custom );
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
	 * The font slug for a semantic type slot, or the safe slot default.
	 *
	 * Unknown stored slugs degrade to the slot default alone (tolerant of bad
	 * persisted data) rather than invalidating the whole kit.
	 *
	 * @param string $slot heading, body, or button.
	 * @return string A known catalogue slug.
	 */
	public function font( string $slot ): string {
		$value = $this->fonts[ $slot ] ?? self::FONT_DEFAULTS['body'];

		if ( self::CUSTOM_FONT_SLUG === $value ) {
			return null !== $this->custom_font ? $value : ( self::FONT_DEFAULTS[ $slot ] ?? self::FONT_DEFAULTS['body'] );
		}

		if ( null !== Design_Presets::font( $value ) ) {
			return $value;
		}

		return self::FONT_DEFAULTS[ $slot ] ?? self::FONT_DEFAULTS['body'];
	}

	/**
	 * The font slot map as stored.
	 *
	 * @return array<string, string>
	 */
	public function fonts(): array {
		return $this->fonts;
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
	 * The kit's resolved custom Google Font, or null when none is configured.
	 *
	 * @return array<string, mixed>|null
	 */
	public function custom_font(): ?array {
		return $this->custom_font;
	}

	/**
	 * Whether the kit carries a resolved custom Google Font.
	 */
	public function has_custom_font(): bool {
		return null !== $this->custom_font;
	}

	/**
	 * The custom font's CSS family name, or null when none is configured.
	 */
	public function custom_font_family(): ?string {
		return null !== $this->custom_font ? $this->custom_font['family'] : null;
	}

	/**
	 * Persistable array.
	 *
	 * @return array{version: int, source: string, theme_fingerprint: string|null, colors: array<string, string>, fonts: array<string, string>, custom_font?: array<string, mixed>}
	 */
	public function to_array(): array {
		$stored = array(
			'version'           => self::VERSION,
			'source'            => $this->source,
			'theme_fingerprint' => $this->theme_fingerprint,
			'colors'            => $this->colors,
			'fonts'             => $this->fonts,
		);

		if ( null !== $this->custom_font ) {
			$stored['custom_font'] = $this->custom_font;
		}

		return $stored;
	}

	/**
	 * Validate and normalise a font slot map.
	 *
	 * Unknown slugs are tolerated and replaced with the slot default so a bad
	 * write in one slot never invalidates the rest of the kit.
	 *
	 * @param array<string, mixed>|null $fonts Stored font slugs.
	 * @param bool                      $has_custom_font Whether the custom slug can resolve.
	 * @return array<string, string>
	 */
	private static function resolve_fonts( ?array $fonts, bool $has_custom_font = false ): array {
		$resolved = self::FONT_DEFAULTS;

		if ( null !== $fonts ) {
			foreach ( self::FONT_SLOTS as $slot ) {
				$value = $fonts[ $slot ] ?? null;
				if ( ! is_string( $value ) ) {
					continue;
				}

				if ( self::CUSTOM_FONT_SLUG === $value ) {
					if ( $has_custom_font ) {
						$resolved[ $slot ] = $value;
					}
					continue;
				}

				if ( null !== Design_Presets::font( $value ) ) {
					$resolved[ $slot ] = $value;
				}
			}
		}

		return $resolved;
	}

	/**
	 * Normalize stored custom-font data into its canonical shape, or return null
	 * when it is missing or unusable.
	 *
	 * A custom font is the kit's resolved Google Font: a family name, a set of
	 * weights, and the @font-face CSS captured at save time. Tolerant on read —
	 * malformed input is dropped rather than raised, so a corrupted kit degrades
	 * to the catalogue defaults instead of breaking render.
	 *
	 * @param mixed $raw Raw custom-font data.
	 *
	 * @return array{slug: string, name: string, family: string, weights: array<int, int>, url: string, css: string}|null
	 */
	private static function normalize_custom_font( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$family = $raw['family'] ?? null;
		if ( ! is_string( $family ) ) {
			return null;
		}

		$family = trim( $family );

		// A controlled family plus portable fallback stack; never arbitrary CSS.
		if ( '' === $family || strlen( $family ) > 160 || ! preg_match( "/^[a-zA-Z][a-zA-Z0-9 _,'\"-]*$/", $family ) ) {
			return null;
		}

		$name = $raw['name'] ?? null;
		$name = ( is_string( $name ) && '' !== trim( $name ) ) ? trim( $name ) : $family;

		$weights     = array();
		$raw_weights = $raw['weights'] ?? array();
		if ( is_array( $raw_weights ) ) {
			foreach ( $raw_weights as $weight ) {
				if ( is_int( $weight ) && $weight >= 100 && $weight <= 900 && 0 === $weight % 100 ) {
					$weights[] = $weight;
				}
			}
		}

		if ( array() === $weights ) {
			$weights = array( 400 );
		}

		sort( $weights );
		$weights = array_values( array_unique( $weights ) );

		$url = is_string( $raw['url'] ?? null ) ? $raw['url'] : '';
		$css = is_string( $raw['css'] ?? null ) ? trim( $raw['css'] ) : '';
		if ( ! self::is_safe_custom_font_url( $url ) || ! self::is_safe_custom_font_css( $css ) ) {
			return null;
		}

		return array(
			'slug'    => self::CUSTOM_FONT_SLUG,
			'name'    => $name,
			'family'  => $family,
			'weights' => $weights,
			'url'     => $url,
			'css'     => $css,
		);
	}

	/**
	 * Only allow the Google CSS2 endpoint generated by the resolver.
	 *
	 * @param string $url Stylesheet URL.
	 */
	private static function is_safe_custom_font_url( string $url ): bool {
		return 'https' === wp_parse_url( $url, PHP_URL_SCHEME )
			&& 'fonts.googleapis.com' === wp_parse_url( $url, PHP_URL_HOST )
			&& '/css2' === wp_parse_url( $url, PHP_URL_PATH );
	}

	/**
	 * Reject persisted CSS unless every asset is a Google-hosted WOFF2 face.
	 *
	 * @param string $css Font-face stylesheet.
	 */
	private static function is_safe_custom_font_css( string $css ): bool {
		if ( '' === $css || strlen( $css ) > 200000 || str_contains( strtolower( $css ), '</style' ) || ! str_contains( $css, '@font-face' ) ) {
			return false;
		}
		if ( ! preg_match_all( '/url\(([^)]+)\)/i', $css, $matches ) ) {
			return false;
		}
		foreach ( $matches[1] as $raw_url ) {
			$url = trim( (string) $raw_url, " \t\n\r\0\x0B\"'" );
			if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME )
				|| 'fonts.gstatic.com' !== wp_parse_url( $url, PHP_URL_HOST )
				|| ! str_ends_with( (string) wp_parse_url( $url, PHP_URL_PATH ), '.woff2' ) ) {
				return false;
			}
		}

		return true;
	}
}
