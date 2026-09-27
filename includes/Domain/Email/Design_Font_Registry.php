<?php
/**
 * Per-template email font registry.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores validated web fonts and semantic type overrides with one email design.
 *
 * Brand Kit fonts remain reusable site defaults. This registry is revisioned
 * with a template so a one-off campaign can introduce fonts without changing
 * every other email.
 */
final class Design_Font_Registry {
	public const VERSION   = 1;
	public const MAX_FONTS = 24;
	public const META_KEY  = 'campaignbridge_template_design_fonts';

	/**
	 * Create an immutable template font registry.
	 *
	 * @param array<int, array<string, mixed>> $fonts Validated web-font records.
	 * @param array<string, string>            $slots Per-design semantic overrides.
	 */
	private function __construct(
		private readonly array $fonts,
		private readonly array $slots
	) {}

	/** Empty registry inheriting every Brand Kit type slot. */
	public static function empty(): self {
		return new self( array(), array() );
	}

	/**
	 * Rebuild a registry from decoded persisted or request data.
	 *
	 * Invalid font records are dropped. Slot choices are retained only when
	 * they are syntactically valid; final catalog membership is resolved with
	 * the active Brand Kit by Email_Design_Normalizer.
	 *
	 * @param array<string, mixed>|array<int, mixed> $data Raw registry data.
	 */
	public static function from_array( array $data ): self {
		if ( array_is_list( $data ) ) {
			$data = array( 'fonts' => $data );
		}

		$version = $data['version'] ?? self::VERSION;
		if ( ! is_int( $version ) || self::VERSION !== $version ) {
			return self::empty();
		}

		$raw_fonts = is_array( $data['fonts'] ?? null ) ? $data['fonts'] : array();
		$fonts     = array();
		$seen      = array();
		foreach ( $raw_fonts as $raw_font ) {
			$font = Brand_Kit::normalize_custom_font_record( $raw_font );
			if ( null === $font ) {
				continue;
			}

			$family_key = strtolower( (string) $font['name'] );
			if ( isset( $seen[ $font['slug'] ] ) || isset( $seen[ $family_key ] ) ) {
				continue;
			}

			$seen[ $font['slug'] ] = true;
			$seen[ $family_key ]   = true;
			$fonts[]               = $font;
			if ( self::MAX_FONTS === count( $fonts ) ) {
				break;
			}
		}

		$slots     = array();
		$raw_slots = is_array( $data['slots'] ?? null ) ? $data['slots'] : array();
		foreach ( Brand_Kit::FONT_SLOTS as $slot ) {
			$value = $raw_slots[ $slot ] ?? null;
			if ( is_string( $value ) && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value ) ) {
				$slots[ $slot ] = $value;
			}
		}

		return new self( $fonts, $slots );
	}

	/**
	 * Decode a persisted JSON registry, failing closed to inherited defaults.
	 *
	 * @param mixed $value Persisted JSON value.
	 */
	public static function from_json( mixed $value ): self {
		if ( ! is_string( $value ) || '' === trim( $value ) || strlen( $value ) > 32768 ) {
			return self::empty();
		}

		try {
			$decoded = json_decode( $value, true, 16, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			return self::empty();
		}

		return is_array( $decoded ) ? self::from_array( $decoded ) : self::empty();
	}

	/**
	 * WordPress post-meta sanitization callback.
	 *
	 * @param mixed $value Submitted JSON.
	 */
	public static function sanitize_json( mixed $value ): string {
		return self::from_json( $value )->to_json();
	}

	/**
	 * Get validated template font records.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function fonts(): array {
		return $this->fonts;
	}

	/**
	 * Get semantic type-slot overrides.
	 *
	 * @return array<string, string>
	 */
	public function slots(): array {
		return $this->slots;
	}

	/**
	 * Export the canonical registry.
	 *
	 * @return array{version: int, fonts: array<int, array<string, mixed>>, slots: array<string, string>}
	 */
	public function to_array(): array {
		return array(
			'version' => self::VERSION,
			'fonts'   => $this->fonts,
			'slots'   => $this->slots,
		);
	}

	/** Canonical JSON suitable for registered post meta. */
	public function to_json(): string {
		$json = wp_json_encode( $this->to_array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? $json : '{"version":1,"fonts":[],"slots":{}}';
	}
}
