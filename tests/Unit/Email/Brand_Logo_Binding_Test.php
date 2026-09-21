<?php
/**
 * Brand Logo Core Image binding tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Brand_Logo_Binding_Test extends TestCase {
	public function test_bound_core_image_compiles_the_frozen_logo(): void {
		$result = $this->compile( $this->logo_block(), $this->brand_kit() );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString(
			'<a href="https://example.com/" style="text-decoration:none"><img src="https://cdn.example.com/logo.png" width="240" height="72" alt="Example &amp; Co."',
			$result->html()
		);
		self::assertSame( "https://example.com/\n", $result->text() );
		self::assertSame( 240, $result->assets()[0]['width'] ?? null );
		self::assertSame( 72, $result->assets()[0]['height'] ?? null );
	}

	public function test_logo_dimensions_never_upscale_the_frozen_asset(): void {
		$block                   = $this->logo_block();
		$block['attrs']['width'] = 900;
		$result                  = $this->compile( $block, $this->brand_kit() );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'width="600" height="180"', $result->html() );
	}

	public function test_bound_logo_requires_an_imported_brand_asset(): void {
		$result = $this->compile( $this->logo_block(), Brand_Kit::defaults() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'brand.logo.missing', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	/** @dataProvider invalid_bindings */
	public function test_unsupported_brand_binding_metadata_fails_closed( array $bindings, string $path ): void {
		$block                                  = $this->logo_block();
		$block['attrs']['metadata']['bindings'] = $bindings;
		$result                                 = $this->compile( $block, $this->brand_kit() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0].innerBlocks[0].attrs.' . $path, $result->diagnostics()[0]->path() );
	}

	/** @return array<string, array{array<string, mixed>, string}> */
	public static function invalid_bindings(): array {
		return array(
			'wrong field'           => array(
				array(
					'url' => array(
						'source' => 'campaignbridge/brand-data',
						'args'   => array( 'field' => 'logoAlt' ),
					),
				),
				'metadata.bindings.url.args.field',
			),
			'unsupported attribute' => array(
				array(
					'caption' => array(
						'source' => 'campaignbridge/brand-data',
						'args'   => array( 'field' => 'logoUrl' ),
					),
				),
				'metadata.bindings.caption',
			),
			'extra argument'        => array(
				array(
					'url' => array(
						'source' => 'campaignbridge/brand-data',
						'args'   => array(
							'field' => 'logoUrl',
							'size'  => 'full',
						),
					),
				),
				'metadata.bindings.url.args.field',
			),
		);
	}

	/** @return array<string, mixed> */
	private function logo_block(): array {
		return array(
			'blockName'   => 'core/image',
			'attrs'       => array(
				'width'           => 240,
				'align'           => 'center',
				'linkDestination' => 'custom',
				'metadata'        => array(
					'bindings' => array(
						'url'  => array(
							'source' => 'campaignbridge/brand-data',
							'args'   => array( 'field' => 'logoUrl' ),
						),
						'alt'  => array(
							'source' => 'campaignbridge/brand-data',
							'args'   => array( 'field' => 'logoAlt' ),
						),
						'href' => array(
							'source' => 'campaignbridge/brand-data',
							'args'   => array( 'field' => 'logoLink' ),
						),
					),
				),
			),
			'innerHTML'   => '',
			'innerBlocks' => array(),
		);
	}

	private function brand_kit(): Brand_Kit {
		return Brand_Kit::from_colors(
			array(),
			Brand_Kit::SOURCE_THEME,
			null,
			null,
			null,
			array(
				'url'      => 'https://cdn.example.com/logo.png',
				'alt'      => 'Example & Co.',
				'width'    => 800,
				'height'   => 240,
				'link_url' => 'https://example.com/',
			)
		);
	}

	private function compile( array $logo, Brand_Kit $kit ): Compile_Result {
		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array( $logo ),
					),
				),
			),
		);

		return Compiler_Factory::create()->compile( $document, ( new Render_Context() )->with_metadata( 'brandKit', $kit ) );
	}

	private function diagnostics( Compile_Result $result ): string {
		return implode( ', ', array_map( static fn ( $diagnostic ): string => $diagnostic->code() . '@' . $diagnostic->path(), $result->diagnostics() ) );
	}
}
