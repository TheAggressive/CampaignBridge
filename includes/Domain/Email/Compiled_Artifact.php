<?php
/**
 * Durable successful compiler artifact.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable exact HTML/text artifact retained for provider handoff. */
final class Compiled_Artifact {
	public const SCHEMA_VERSION  = 1;
	private const MAX_HTML       = 2097152;
	private const MAX_TEXT       = 524288;
	private const MAX_ASSETS     = 100;
	private const MAX_ASSET_JSON = 65536;

	/**
	 * Build the compiled artifact.
	 *
	 * @param string                           $html             HTML content.
	 * @param string                           $text             Plain-text content.
	 * @param array<int, array<string, mixed>> $assets           Bounded canonical asset records.
	 * @param string                           $fingerprint      Artifact fingerprint.
	 * @param string                           $compiler_version Compiler version.
	 * @param string                           $profile_version  Compatibility profile version.
	 */
	private function __construct(
		private readonly string $html,
		private readonly string $text,
		private readonly array $assets,
		private readonly string $fingerprint,
		private readonly string $compiler_version,
		private readonly string $profile_version
	) {}

	/**
	 * Capture a successful canonical compiler result.
	 *
	 * @param Compile_Result $result Compiler output.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_result( Compile_Result $result ): self {
		if ( ! $result->is_success() || '' === $result->html() ) {
			throw new \InvalidArgumentException( 'Only successful compiler results can be persisted as artifacts.' );
		}

		return self::from_array(
			array(
				'schema_version'   => self::SCHEMA_VERSION,
				'html'             => $result->html(),
				'text'             => $result->text(),
				'assets'           => $result->assets(),
				'fingerprint'      => $result->fingerprint(),
				'compiler_version' => $result->compiler_version(),
				'profile_version'  => $result->profile_version(),
			)
		);
	}

	/**
	 * Rebuild an artifact from its stored values.
	 *
	 * @param array<string, mixed> $data Persisted artifact.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_array( array $data ): self {
		$allowed = array( 'schema_version', 'html', 'text', 'assets', 'fingerprint', 'compiler_version', 'profile_version' );
		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new \InvalidArgumentException( 'Unknown compiled artifact field.' );
			}
		}
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported compiled artifact schema version.' );
		}

		$html   = $data['html'] ?? null;
		$text   = $data['text'] ?? null;
		$assets = $data['assets'] ?? null;
		if ( ! is_string( $html ) || '' === $html || strlen( $html ) > self::MAX_HTML ) {
			throw new \InvalidArgumentException( 'Compiled HTML is missing or exceeds its storage bound.' );
		}
		if ( ! is_string( $text ) || strlen( $text ) > self::MAX_TEXT ) {
			throw new \InvalidArgumentException( 'Compiled text exceeds its storage bound.' );
		}
		if ( ! is_array( $assets ) || ! array_is_list( $assets ) || count( $assets ) > self::MAX_ASSETS ) {
			throw new \InvalidArgumentException( 'Compiled assets must be a bounded list.' );
		}
		$normalized_assets = array_map( array( self::class, 'validate_asset' ), $assets );
		try {
			$asset_json = json_encode( $normalized_assets, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain validation requires throwing, deterministic JSON.
		} catch ( \JsonException $exception ) {
			throw new \InvalidArgumentException( 'Compiled assets cannot be serialized.', 0, $exception );
		}
		if ( strlen( $asset_json ) > self::MAX_ASSET_JSON ) {
			throw new \InvalidArgumentException( 'Compiled assets exceed their storage bound.' );
		}

		$fingerprint = $data['fingerprint'] ?? null;
		if ( ! is_string( $fingerprint ) || 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $fingerprint ) ) {
			throw new \InvalidArgumentException( 'Compiled artifact fingerprint is invalid.' );
		}
		$compiler = $data['compiler_version'] ?? null;
		$profile  = $data['profile_version'] ?? null;
		if ( ! is_string( $compiler ) || '' === $compiler || strlen( $compiler ) > 32 || ! is_string( $profile ) || '' === $profile || strlen( $profile ) > 32 ) {
			throw new \InvalidArgumentException( 'Compiled artifact versions are invalid.' );
		}

		return new self( $html, $text, $normalized_assets, $fingerprint, $compiler, $profile );
	}

	/**
	 * Validate one closed canonical asset record.
	 *
	 * @param mixed $asset Untrusted decoded asset.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	private static function validate_asset( mixed $asset ): array {
		if ( ! is_array( $asset ) || array_is_list( $asset ) || ! is_string( $asset['type'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Compiled asset is malformed.' );
		}

		if ( 'image' === $asset['type'] ) {
			self::require_exact_keys( $asset, array( 'type', 'url', 'width', 'height', 'alt' ) );
			// A null height is an image without an authored height, which email clients scale from the width.
			if (
				! is_int( $asset['width'] ) || 1 > $asset['width'] || 4096 < $asset['width']
				|| ( null !== $asset['height'] && ( ! is_int( $asset['height'] ) || 1 > $asset['height'] || 4096 < $asset['height'] ) )
				|| ! is_string( $asset['alt'] ) || 2048 < strlen( $asset['alt'] )
			) {
				throw new \InvalidArgumentException( 'Compiled image asset is invalid.' );
			}
			self::validate_asset_url( $asset['url'] );

			return $asset;
		}

		if ( 'font' === $asset['type'] ) {
			self::require_exact_keys( $asset, array( 'type', 'slug', 'url' ) );
			if ( ! is_string( $asset['slug'] ) || 1 !== preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $asset['slug'] ) ) {
				throw new \InvalidArgumentException( 'Compiled font asset slug is invalid.' );
			}
			self::validate_asset_url( $asset['url'] );

			return $asset;
		}

		throw new \InvalidArgumentException( 'Compiled asset type is unsupported.' );
	}

	/**
	 * Reject an asset record whose keys are not exactly the expected ones.
	 *
	 * @param array<string, mixed> $asset    Asset record.
	 * @param array<int, string>   $expected Exact field names.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	private static function require_exact_keys( array $asset, array $expected ): void {
		$keys = array_keys( $asset );
		sort( $keys, SORT_STRING );
		sort( $expected, SORT_STRING );
		if ( $expected !== $keys ) {
			throw new \InvalidArgumentException( 'Compiled asset contains missing or unknown fields.' );
		}
	}

	/**
	 * Validate a bounded public HTTP(S) asset URL without embedded credentials.
	 *
	 * @param mixed $url Asset URL.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	private static function validate_asset_url( mixed $url ): void {
		if ( ! is_string( $url ) || '' === $url || 2048 < strlen( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( 'Compiled asset URL is invalid.' );
		}

		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure domain validation does not require WordPress URL normalization.
		if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new \InvalidArgumentException( 'Compiled asset URL is invalid.' );
		}
	}

	/**
	 * The artifact's HTML.
	 */
	public function html(): string {
		return $this->html;
	}

	/**
	 * The artifact's text.
	 */
	public function text(): string {
		return $this->text;
	}

	/**
	 * The artifact's assets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function assets(): array {
		return $this->assets;
	}

	/**
	 * The artifact's fingerprint.
	 */
	public function fingerprint(): string {
		return $this->fingerprint;
	}

	/**
	 * The artifact's compiler version.
	 */
	public function compiler_version(): string {
		return $this->compiler_version;
	}

	/**
	 * The artifact's profile version.
	 */
	public function profile_version(): string {
		return $this->profile_version;
	}

	/**
	 * The artifact's stored values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version'   => self::SCHEMA_VERSION,
			'html'             => $this->html,
			'text'             => $this->text,
			'assets'           => $this->assets,
			'fingerprint'      => $this->fingerprint,
			'compiler_version' => $this->compiler_version,
			'profile_version'  => $this->profile_version,
		);
	}
}
