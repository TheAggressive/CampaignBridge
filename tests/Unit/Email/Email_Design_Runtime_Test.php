<?php
/**
 * Email design runtime tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Email_Design_Error;
use CampaignBridge\Domain\Email\Email_Design_Resolver;
use CampaignBridge\Domain\Email\Email_Design_Validator;
use CampaignBridge\Services\Email\Design\Email_Design_Loader;
use PHPUnit\Framework\TestCase;

/** Proves packaged design input becomes one safe and deterministic runtime value. */
final class Email_Design_Runtime_Test extends TestCase {
	/** The packaged contract loads and resolves every preset to a literal. */
	public function test_resolves_the_packaged_design(): void {
		$design = $this->resolve();

		self::assertSame( 1, $design->version() );
		self::assertSame( 600, $design->content_width() );
		self::assertFalse( $design->allows_custom_colors() );
		self::assertFalse( $design->allows_custom_font_sizes() );
		self::assertFalse( $design->allows_custom_spacing() );
		self::assertSame( '#111111', $design->global_style()['color']['text'] );
		self::assertSame( 16, $design->global_style()['typography']['fontSize'] );
		self::assertSame( 24, $design->block_style( 'campaignbridge/columns' )['spacing']['blockGap'] );
		self::assertSame( 1, $design->block_style( 'campaignbridge/divider' )['border']['width'] );
		self::assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', $design->fingerprint() );
		self::assertStringNotContainsString( 'var:preset|', (string) wp_json_encode( $design->to_array() ) );
	}

	/** Brand Kit identity populates semantic slots used by manifest rules. */
	public function test_brand_kit_identity_flows_into_resolved_styles(): void {
		$kit    = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) );
		$design = $this->resolve( $kit );

		self::assertSame( '#ff5500', $this->color( $design->colors(), Brand_Kit::SLOT_BRAND ) );
		self::assertSame( '#ff5500', $design->block_style( 'campaignbridge/button' )['color']['background'] );
	}

	/** Equivalent input is stable while an output-affecting Brand Kit change is not. */
	public function test_fingerprint_is_deterministic_and_includes_brand_identity(): void {
		$first  = $this->resolve();
		$second = $this->resolve();
		$custom = $this->resolve( Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) ) );

		self::assertSame( $first->fingerprint(), $second->fingerprint() );
		self::assertNotSame( $first->fingerprint(), $custom->fingerprint() );
	}

	/** Unknown fields are rejected instead of being silently forwarded. */
	public function test_rejects_unknown_properties(): void {
		$loader          = new Email_Design_Loader();
		$manifest        = $loader->manifest();
		$manifest['css'] = 'body { display:none }';
		$validator       = new Email_Design_Validator( $loader->schema() );

		try {
			$validator->validate( $manifest );
			self::fail( 'Unknown manifest property was accepted.' );
		} catch ( Email_Design_Error $error ) {
			self::assertSame( 'design.invalid_property', $error->diagnostic_code() );
			self::assertSame( '$.css', $error->design_path() );
		}
	}

	/** Unsupported public contract versions fail with their stable code. */
	public function test_rejects_unsupported_versions(): void {
		$loader              = new Email_Design_Loader();
		$manifest            = $loader->manifest();
		$manifest['version'] = 2;

		try {
			( new Email_Design_Validator( $loader->schema() ) )->validate( $manifest );
			self::fail( 'Unsupported version was accepted.' );
		} catch ( Email_Design_Error $error ) {
			self::assertSame( 'design.unsupported_version', $error->diagnostic_code() );
			self::assertSame( '$.version', $error->design_path() );
		}
	}

	/** Invalid and unsafe values produce stable typed diagnostics. */
	public function test_rejects_invalid_typed_values(): void {
		$loader = new Email_Design_Loader();
		$cases  = array(
			array( 'settings.color.palette.0.color', 'red', 'design.invalid_color' ),
			array( 'settings.typography.fontSizes.0.size', '1vw', 'design.invalid_font' ),
			array( 'settings.spacing.spacingSizes.0.size', '25%', 'design.invalid_spacing' ),
			array( 'styles.blocks.campaignbridge/unknown', array( 'color' => array( 'text' => '#111111' ) ), 'design.invalid_block' ),
			array(
				'styles.blocks.campaignbridge/button.border',
				array(
					'color' => '#111111',
					'style' => 'solid',
					'width' => '1px',
				),
				'design.unsupported_block_style',
			),
		);

		foreach ( $cases as $case ) {
			list( $path, $value, $code ) = $case;
			$manifest                    = $loader->manifest();
			$this->set_path( $manifest, $path, $value );
			try {
				( new Email_Design_Validator( $loader->schema() ) )->validate( $manifest );
				self::fail( 'Invalid value was accepted at ' . $path );
			} catch ( Email_Design_Error $error ) {
				self::assertSame( $code, $error->diagnostic_code(), $path );
			}
		}
	}

	/** Duplicate slugs cannot create order-dependent lookup behavior. */
	public function test_rejects_duplicate_preset_slugs(): void {
		$loader                                     = new Email_Design_Loader();
		$manifest                                   = $loader->manifest();
		$manifest['settings']['color']['palette'][] = $manifest['settings']['color']['palette'][0];

		$this->expectException( Email_Design_Error::class );
		$this->expectExceptionMessage( 'must be unique' );
		( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )->resolve( $manifest );
	}

	/** A reference cannot cross catalogs or name an absent preset. */
	public function test_rejects_unresolved_preset_references(): void {
		$loader                              = new Email_Design_Loader();
		$manifest                            = $loader->manifest();
		$manifest['styles']['color']['text'] = 'var:preset|color|missing';

		try {
			( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )->resolve( $manifest );
			self::fail( 'Unresolved reference was accepted.' );
		} catch ( Email_Design_Error $error ) {
			self::assertSame( 'design.unresolved_preset', $error->diagnostic_code() );
			self::assertSame( '$.styles.color.text', $error->design_path() );
		}
	}

	/**
	 * Resolve the packaged design with optional brand identity.
	 *
	 * @param Brand_Kit|null $brand_kit Active identity.
	 */
	private function resolve( ?Brand_Kit $brand_kit = null ): \CampaignBridge\Domain\Email\Resolved_Email_Design {
		$loader = new Email_Design_Loader();
		return ( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )->resolve( $loader->manifest(), $brand_kit );
	}

	/**
	 * Find a resolved color by slug.
	 *
	 * @param array<int, array<string, mixed>> $colors Resolved palette.
	 * @param string                           $slug   Semantic slug.
	 */
	private function color( array $colors, string $slug ): string {
		foreach ( $colors as $color ) {
			if ( $slug === $color['slug'] ) {
				return $color['color'];
			}
		}
		self::fail( 'Missing color ' . $slug );
	}

	/**
	 * Set a dot-delimited test fixture path.
	 *
	 * @param array<string, mixed> $manifest Fixture mutated by reference.
	 * @param string               $path     Dot-delimited path.
	 * @param mixed                $value    Replacement value.
	 */
	private function set_path( array &$manifest, string $path, mixed $value ): void {
		$cursor =& $manifest;
		foreach ( explode( '.', $path ) as $segment ) {
			$key    = ctype_digit( $segment ) ? (int) $segment : $segment;
			$cursor =& $cursor[ $key ];
		}
		$cursor = $value;
	}
}
