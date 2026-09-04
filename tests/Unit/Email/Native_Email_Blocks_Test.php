<?php
/**
 * Native email block compiler contract tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Native_Email_Blocks_Test extends TestCase {
	public function test_editor_metadata_and_compiler_registry_have_identical_names(): void {
		$metadata_files = glob( dirname( __DIR__, 3 ) . '/src/blocks/*/block.json' );
		self::assertIsArray( $metadata_files );
		$metadata_names = array_map(
			static function ( string $file ): string {
				$metadata = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
				self::assertIsArray( $metadata );

				return (string) $metadata['name'];
			},
			$metadata_files
		);
		$renderer_names = Compiler_Factory::registry()->block_names();
		sort( $metadata_names, SORT_STRING );
		sort( $renderer_names, SORT_STRING );

		self::assertSame( $metadata_names, $renderer_names );
	}

	public function test_compiles_native_blocks_to_golden_html_and_text(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertSame( $this->fixture( 'native-content.html' ), $result->html() );
		self::assertSame( $this->fixture( 'native-content.txt' ), $result->text() );
		self::assertSame(
			array(
				array(
					'type'   => 'image',
					'url'    => 'https://example.com/hero.jpg',
					'width'  => 600,
					'height' => 320,
					'alt'    => 'Campaign hero',
				),
			),
			$result->assets()
		);
	}

	public function test_rejects_unsafe_rich_text_without_partial_output(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['content'] = '<strong onclick="alert(1)">Unsafe</strong>';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'text.content.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
		self::assertSame( array(), $result->assets() );
	}

	public function test_rejects_non_https_rich_text_link(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['content'] = '<a href="http://example.com">Unsafe</a>';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'text.content.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_requires_alt_text_for_non_decorative_image(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][2]['attrs']['alt'] = '';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'image.alt.missing', $result->diagnostics()[0]->code() );
	}

	public function test_marks_decorative_images_explicitly(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][2]['attrs']['decorative'] = true;
		$document[0]['innerBlocks'][0]['innerBlocks'][2]['attrs']['alt']        = '';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'alt="" role="presentation"', $result->html() );
	}

	public function test_requires_https_button_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3]['attrs']['url'] = 'javascript:alert(1)';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'button.url.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_out_of_range_width_instead_of_clamping_it(): void {
		$document = $this->document();

		$document[0]['innerBlocks'][0]['innerBlocks'][2]['attrs']['width'] = 5000;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0].innerBlocks[2].attrs.width', $result->diagnostics()[0]->path() );
		self::assertSame( '', $result->html() );
		self::assertSame( array(), $result->assets() );
	}

	public function test_rejects_invalid_alignment_instead_of_substituting_a_default(): void {
		$document = $this->document();

		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['align'] = 'diagonal';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0].innerBlocks[1].attrs.align', $result->diagnostics()[0]->path() );
	}

	public function test_rejects_invalid_color_instead_of_substituting_a_default(): void {
		$document = $this->document();

		$document[0]['innerBlocks'][0]['innerBlocks'][5]['attrs']['color'] = 'red';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0].innerBlocks[5].attrs.color', $result->diagnostics()[0]->path() );
	}

	public function test_rejects_partial_spacing_instead_of_filling_missing_sides(): void {
		$document = $this->document();

		$document[0]['innerBlocks'][0]['attrs']['padding'] = array( 'top' => 24 );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0].attrs.padding', $result->diagnostics()[0]->path() );
	}

	public function test_rejects_native_leaf_outside_section(): void {
		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/text',
						'attrs'       => array( 'content' => 'Wrong parent' ),
						'innerBlocks' => array(),
					),
				),
			),
		);
		$result   = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
	}

	public function test_compiles_serialized_native_block_comments(): void {
		$serialized = '<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/heading {"content":"Serialized heading"} /-->'
			. '<!-- wp:campaignbridge/text {"content":"Serialized text"} /-->'
			. '<!-- /wp:campaignbridge/section -->'
			. '<!-- /wp:campaignbridge/container -->';
		$result     = Compiler_Factory::create()->compile( parse_blocks( $serialized ), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'Serialized heading', $result->html() );
		self::assertStringContainsString( 'Serialized text', $result->text() );
	}

	/** @return array<int, array<string, mixed>> */
	private function document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array( 'maxWidth' => 600 ),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(
							'padding'         => array(
								'top'    => 24,
								'right'  => 12,
								'bottom' => 24,
								'left'   => 12,
							),
							'backgroundColor' => '#ffffff',
						),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/heading',
								'attrs'       => array(
									'content'   => 'Build &amp; send confidently',
									'level'     => 1,
									'align'     => 'center',
									'textColor' => '#111111',
								),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/text',
								'attrs'       => array(
									'content'   => 'A <strong>deterministic</strong> message with an <a href="https://example.com/docs" target="_blank" rel="noreferrer noopener">auditable link</a>.',
									'align'     => 'left',
									'textColor' => '#333333',
									'fontSize'  => 16,
								),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/image',
								'attrs'       => array(
									'url'        => 'https://example.com/hero.jpg',
									'alt'        => 'Campaign hero',
									'decorative' => false,
									'width'      => 600,
									'height'     => 320,
									'linkUrl'    => 'https://example.com/story',
								),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/button',
								'attrs'       => array(
									'label'           => 'Read the story',
									'url'             => 'https://example.com/story',
									'align'           => 'center',
									'backgroundColor' => '#0057b8',
									'textColor'       => '#ffffff',
								),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/spacer',
								'attrs'       => array( 'height' => 24 ),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/divider',
								'attrs'       => array(
									'color'     => '#dddddd',
									'thickness' => 2,
									'width'     => 80,
									'style'     => 'solid',
								),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);
	}

	private function context(): Render_Context {
		return new Render_Context(
			array(
				'title'            => 'Native content fixture',
				'language'         => 'en',
				'background_color' => '#f4f4f4',
			)
		);
	}

	private function fixture( string $name ): string {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/' . $name );
		self::assertIsString( $contents );

		return $contents;
	}
}
