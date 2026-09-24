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
		self::assertSame( 1, $design->block_style( 'core/separator' )['border']['width'] );
		self::assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', $design->fingerprint() );
		self::assertStringNotContainsString( 'var:preset|', (string) wp_json_encode( $design->to_array() ) );
	}

	/** Brand Kit identity populates semantic slots used by manifest rules. */
	public function test_brand_kit_identity_flows_into_resolved_styles(): void {
		$kit    = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) );
		$design = $this->resolve( $kit );

		self::assertSame( '#ff5500', $this->color( $design->colors(), Brand_Kit::SLOT_BRAND ) );
		self::assertSame( '#ff5500', $design->block_style( 'core/button' )['color']['background'] );
	}

	/** Semantic Brand Kit font slots become inherited resolved block styles. */
	public function test_brand_kit_typography_flows_into_resolved_styles(): void {
		$kit    = Brand_Kit::from_colors(
			array(),
			Brand_Kit::SOURCE_CUSTOM,
			null,
			array(
				'heading' => 'playfair',
				'body'    => 'inter',
				'button'  => 'montserrat',
			)
		);
		$design = $this->resolve( $kit );

		self::assertSame( 'Playfair Display,Georgia,serif', $design->block_style( 'core/heading' )['typography']['fontFamily'] );
		self::assertSame( 'Inter,Arial,Helvetica,sans-serif', $design->block_style( 'core/paragraph' )['typography']['fontFamily'] );
		self::assertSame( 'Montserrat,Arial,Helvetica,sans-serif', $design->block_style( 'core/button' )['typography']['fontFamily'] );
		self::assertSame( 'inter', $design->font_for_slot( 'body' )['slug'] );
	}

	/** A validated custom Google Font is part of the resolved preset catalog. */
	public function test_custom_brand_font_is_resolved_for_its_semantic_slot(): void {
		$custom = array(
			'name'    => 'Example Sans',
			'family'  => 'Example Sans,Arial,Helvetica,sans-serif',
			'weights' => array( 400, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Example+Sans:wght@400;700&display=swap',
		);
		$kit    = Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_CUSTOM, null, array( 'heading' => 'custom' ), $custom );
		$design = $this->resolve( $kit );

		self::assertSame( 'Example Sans,Arial,Helvetica,sans-serif', $design->block_style( 'core/heading' )['typography']['fontFamily'] );
		self::assertSame( 'custom', $design->font_for_slot( 'heading' )['slug'] );
	}

	/** Parent and child theme manifests follow Core's low-to-high cascade. */
	public function test_layers_parent_and_child_theme_email_designs(): void {
		$fixture = dirname( __DIR__, 2 ) . '/Fixtures/Email/design/';
		$loader  = new Email_Design_Loader( array( $fixture . 'parent.json', $fixture . 'child.json' ) );
		$design  = ( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )
			->resolve_layers( $loader->layers() );

		self::assertSame( 680, $design->content_width() );
		self::assertSame( '#0055cc', $design->block_style( 'core/heading' )['color']['text'] );
		self::assertSame( 600, $design->block_style( 'core/heading' )['typography']['fontWeight'] );
		self::assertSame( '#111111', $design->global_style()['color']['text'] );
	}

	/** Brand Kit remains the user customization layer above theme files. */
	public function test_brand_kit_overrides_theme_identity_slots(): void {
		$fixture = dirname( __DIR__, 2 ) . '/Fixtures/Email/design/parent.json';
		$loader  = new Email_Design_Loader( array( $fixture ) );
		$kit     = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) );
		$design  = ( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )
			->resolve_layers( $loader->layers(), $kit );

		self::assertSame( '#ff5500', $this->color( $design->colors(), Brand_Kit::SLOT_BRAND ) );
	}

	/** Theme manifests must identify the contract they target. */
	public function test_rejects_a_theme_manifest_without_a_version(): void {
		$fixture = dirname( __DIR__, 2 ) . '/Fixtures/Email/design/versionless.json';
		$loader  = new Email_Design_Loader( array( $fixture ) );

		$this->expectException( Email_Design_Error::class );
		$this->expectExceptionMessage( 'must declare its contract version' );
		$loader->layers();
	}

	/** A valid child cannot hide an invalid parent source layer. */
	public function test_rejects_an_invalid_parent_even_when_the_child_replaces_its_value(): void {
		$fixture = dirname( __DIR__, 2 ) . '/Fixtures/Email/design/';
		$loader  = new Email_Design_Loader( array( $fixture . 'invalid-parent.json', $fixture . 'child.json' ) );

		$this->expectException( Email_Design_Error::class );
		$this->expectExceptionMessage( '$.settings.layout.contentWidth' );
		( new Email_Design_Resolver( new Email_Design_Validator( $loader->schema() ) ) )
			->resolve_layers( $loader->layers() );
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
				'styles.blocks.core/button.border',
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
