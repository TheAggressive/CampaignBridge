<?php
/**
 * Packaged email-design contract tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Design_Presets;
use JsonException;
use PHPUnit\Framework\TestCase;

/** Proves the repository-owned manifest remains aligned with its v1 contract. */
final class Email_Design_Contract_Test extends TestCase {
	private const SCHEMA_URI = 'https://campaignbridge.dev/schemas/email-design-v1.json';

	/** The packaged manifest and schema remain parseable and version-aligned. */
	public function test_packaged_manifest_and_schema_are_valid_json(): void {
		$manifest = $this->json_file( 'email.json' );
		$schema   = $this->json_file( 'email-design-v1.schema.json' );

		self::assertSame( self::SCHEMA_URI, $manifest['$schema'] );
		self::assertSame( 1, $manifest['version'] );
		self::assertSame( self::SCHEMA_URI, $schema['$id'] );
		self::assertSame( 1, $schema['properties']['version']['const'] );
		self::assertFalse( $schema['additionalProperties'] );
	}

	/** The contract starts from the design values the compiler already knows. */
	public function test_packaged_catalog_matches_the_existing_compiler_catalog(): void {
		$manifest = $this->json_file( 'email.json' );
		$settings = $manifest['settings'];

		self::assertSame( Design_Presets::colors(), $settings['color']['palette'] );
		self::assertSame( Design_Presets::font_sizes(), $settings['typography']['fontSizes'] );
		self::assertSame( Design_Presets::spacing_sizes(), $settings['spacing']['spacingSizes'] );
		self::assertSame(
			array_map(
				static fn( array $font ): array => array(
					'slug' => $font['slug'],
					'name' => $font['name'],
				),
				Design_Presets::fonts()
			),
			$settings['typography']['fontFamilies']
		);
		self::assertFalse( $settings['color']['custom'] );
		self::assertFalse( $settings['typography']['customFontSize'] );
		self::assertFalse( $settings['spacing']['custom'] );
	}

	/** Every semantic reference names a preset in the matching catalog. */
	public function test_every_packaged_preset_reference_resolves_to_its_typed_catalog(): void {
		$manifest = $this->json_file( 'email.json' );
		$catalogs = array(
			'color'       => array_column( $manifest['settings']['color']['palette'], 'slug' ),
			'font-size'   => array_column( $manifest['settings']['typography']['fontSizes'], 'slug' ),
			'font-family' => array_column( $manifest['settings']['typography']['fontFamilies'], 'slug' ),
			'spacing'     => array_column( $manifest['settings']['spacing']['spacingSizes'], 'slug' ),
		);

		foreach ( $this->strings_in( $manifest['styles'] ) as $value ) {
			if ( ! str_starts_with( $value, 'var:preset|' ) ) {
				continue;
			}

			$parts = explode( '|', $value );
			self::assertCount( 3, $parts, 'Malformed preset reference: ' . $value );
			self::assertSame( 'var:preset', $parts[0], 'Malformed preset prefix: ' . $value );
			self::assertArrayHasKey( $parts[1], $catalogs, 'Unknown preset kind: ' . $value );
			self::assertContains( $parts[2], $catalogs[ $parts[1] ], 'Unknown preset slug: ' . $value );
		}
	}

	/** The schema is closed and exposes no arbitrary CSS escape hatch. */
	public function test_schema_closes_objects_and_prohibits_css_escape_hatches(): void {
		$schema  = $this->json_file( 'email-design-v1.schema.json' );
		$encoded = (string) wp_json_encode( $schema, JSON_THROW_ON_ERROR );

		self::assertStringNotContainsString( '"css"', $encoded );
		self::assertStringNotContainsString( 'selector', strtolower( $encoded ) );
		self::assertStringNotContainsString( 'fontFamily":"', $encoded );

		foreach ( $this->objects_with_properties( $schema ) as $object ) {
			self::assertArrayHasKey( 'additionalProperties', $object );
			self::assertFalse( $object['additionalProperties'] );
		}
	}

	/**
	 * Decode one packaged contract file.
	 *
	 * @param string $name File basename.
	 * @return array<string, mixed>
	 */
	private function json_file( string $name ): array {
		$path = dirname( __DIR__, 3 ) . '/includes/Email_Design/' . $name;
		self::assertFileExists( $path );

		try {
			$decoded = json_decode( (string) file_get_contents( $path ), true, 64, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Local packaged fixture.
		} catch ( JsonException $exception ) {
			self::fail( $name . ' is invalid JSON: ' . $exception->getMessage() );
		}

		self::assertIsArray( $decoded );
		return $decoded;
	}

	/**
	 * Recursively collect strings.
	 *
	 * @param mixed $value Value to walk.
	 * @return array<int, string>
	 */
	private function strings_in( mixed $value ): array {
		if ( is_string( $value ) ) {
			return array( $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$strings = array();
		foreach ( $value as $item ) {
			$strings = array_merge( $strings, $this->strings_in( $item ) );
		}

		return $strings;
	}

	/**
	 * Collect object schemas that declare properties.
	 *
	 * @param mixed $value Schema branch.
	 * @return array<int, array<string, mixed>>
	 */
	private function objects_with_properties( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$objects = array_key_exists( 'properties', $value ) && 'object' === ( $value['type'] ?? null )
			? array( $value )
			: array();

		foreach ( $value as $item ) {
			$objects = array_merge( $objects, $this->objects_with_properties( $item ) );
		}

		return $objects;
	}
}
