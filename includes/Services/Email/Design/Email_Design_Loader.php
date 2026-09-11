<?php
/**
 * Packaged email design loader.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Design;

use CampaignBridge\Domain\Email\Email_Design_Error;
use JsonException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Loads and layers bounded repository and active-theme JSON manifests. */
final class Email_Design_Loader {
	private const MAX_BYTES  = 131072;
	private const THEME_PATH = 'campaignbridge/email.json';

	/**
	 * Create a loader.
	 *
	 * @param array<int, string>|null $theme_manifest_paths Explicit low-to-high theme paths, or null to discover the active theme hierarchy.
	 */
	public function __construct( private readonly ?array $theme_manifest_paths = null ) {}

	/**
	 * Load the packaged base manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function manifest(): array {
		return $this->decode( dirname( __DIR__, 3 ) . '/Email_Design/email.json' );
	}

	/**
	 * Load all design layers in low-to-high precedence.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function layers(): array {
		$layers = array( $this->manifest() );
		foreach ( $this->theme_paths() as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$overlay = $this->decode( $path );
			$this->assert_theme_version( $overlay );
			$layers[] = $overlay;
		}
		return $layers;
	}

	/**
	 * Load the packaged v1 schema.
	 *
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		return $this->decode( dirname( __DIR__, 3 ) . '/Email_Design/email-design-v1.schema.json' );
	}

	/**
	 * Decode one bounded local JSON file.
	 *
	 * @param string $path Absolute local path.
	 * @return array<string, mixed>
	 * @throws Email_Design_Error When the file is absent, unsafe, or invalid.
	 */
	private function decode( string $path ): array {
		$size = is_file( $path ) ? filesize( $path ) : false;
		if ( false === $size || 0 === $size || self::MAX_BYTES < $size ) {
			throw new Email_Design_Error( 'design.unsafe_value', '$', 'Email design file is missing or exceeds its size limit.' );
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Fixed repository-owned local file.
		if ( false === $content ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Email design file could not be read.' );
		}

		try {
			$value = json_decode( $content, true, 64, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Email design file contains invalid JSON.' );
		}
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Email design root must be an object.' );
		}
		return $value;
	}

	/**
	 * Resolve theme manifest paths in low-to-high precedence.
	 *
	 * @return array<int, string>
	 */
	private function theme_paths(): array {
		if ( null !== $this->theme_manifest_paths ) {
			return $this->theme_manifest_paths;
		}
		if ( ! function_exists( 'get_template_directory' ) || ! function_exists( 'get_stylesheet_directory' ) ) {
			return array();
		}

		$directories = array_unique( array( get_template_directory(), get_stylesheet_directory() ) );
		return array_map(
			static fn( string $directory ): string => rtrim( $directory, '/\\' ) . '/' . self::THEME_PATH,
			$directories
		);
	}

	/**
	 * Require every theme layer to declare the public contract version.
	 *
	 * @param array<string, mixed> $manifest Theme manifest layer.
	 * @throws Email_Design_Error When the layer omits or declares an unsupported version.
	 */
	private function assert_theme_version( array $manifest ): void {
		if ( ! array_key_exists( 'version', $manifest ) ) {
			throw new Email_Design_Error( 'design.invalid_property', '$.version', 'Theme email design must declare its contract version.' );
		}
		if ( 1 !== $manifest['version'] ) {
			throw new Email_Design_Error( 'design.unsupported_version', '$.version', 'Theme email design version is not supported.' );
		}
	}
}
