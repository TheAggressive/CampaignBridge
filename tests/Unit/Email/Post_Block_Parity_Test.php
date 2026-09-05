<?php
/**
 * Post binding block control-surface tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Post_Block_Parity_Test extends TestCase {
	public function test_defaults_reproduce_the_previous_output_byte_for_byte(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		// No alignment wrapper, no anchor, snapshot dimensions preserved.
		self::assertStringContainsString(
			'<img src="https://example.com/hero.jpg" width="600" height="320" alt="Campaign hero" border="0"',
			$result->html()
		);
		self::assertStringNotContainsString( '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse"><tr><td align="', $result->html() );
	}

	public function test_scales_image_height_with_an_author_width_to_keep_the_aspect_ratio(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['width'] = 300;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'width="300" height="160"', $result->html() );
		self::assertSame( 300, $result->assets()[0]['width'] );
		self::assertSame( 160, $result->assets()[0]['height'] );
	}

	public function test_centres_an_image_with_an_alignment_wrapper(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['align'] = 'center';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<td align="center"><img src="https://example.com/hero.jpg"', $result->html() );
	}

	public function test_links_the_image_and_title_to_the_snapshot_post(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['linkToPost'] = true;
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['linkToPost'] = true;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<a href="https://example.com/posts/7" style="text-decoration:none"><img', $result->html() );
		self::assertStringContainsString( '<a href="https://example.com/posts/7" style="color:#111111;text-decoration:none">Snapshot title</a>', $result->html() );
	}

	public function test_drops_alt_text_for_a_decorative_image(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['decorative'] = true;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'alt="" role="presentation"', $result->html() );
		self::assertSame( '', $result->assets()[0]['alt'] );
	}

	public function test_renders_the_call_to_action_as_a_text_link_without_button_markup(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3]['attrs']['style'] = 'link';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString(
			'<a href="https://example.com/posts/7" style="color:#111111;text-decoration:underline">Read more</a>',
			$result->html()
		);
		self::assertStringNotContainsString( 'v:roundrect', $result->html() );
	}

	public function test_link_style_uses_its_own_colour_rather_than_the_button_fill(): void {
		$document               = $this->document();
		$cta                    = &$document[0]['innerBlocks'][0]['innerBlocks'][3]['attrs'];
		$cta['style']           = 'link';
		$cta['linkColor']       = '#0000ee';
		$cta['backgroundColor'] = '#ff0000';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'color:#0000ee;text-decoration:underline', $result->html() );
		self::assertStringNotContainsString( '#ff0000', $result->html() );
	}

	public function test_keeps_the_bulletproof_button_as_the_default_style(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'v:roundrect', $result->html() );
	}

	public function test_rejects_an_unknown_call_to_action_style(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3]['attrs']['style'] = 'ghost';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_applies_post_card_padding_and_background(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['attrs']['padding']         = array(
			'top'    => 8,
			'right'  => 12,
			'bottom' => 8,
			'left'   => 12,
		);
		$document[0]['innerBlocks'][0]['attrs']['backgroundColor'] = '#eeeeee';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'border-collapse:collapse;background-color:#eeeeee', $result->html() );
		self::assertStringContainsString( 'padding:8px 12px 8px 12px', $result->html() );
	}

	public function test_compiles_a_post_card_laid_out_with_columns(): void {
		$result = Compiler_Factory::create()->compile( $this->media_left_document(), $this->context() );

		self::assertTrue( $result->is_success() );
		// The post binding reaches the post blocks through the columns wrapper.
		self::assertStringContainsString( 'width="35%"', $result->html() );
		self::assertStringContainsString( 'width="65%"', $result->html() );
		self::assertStringContainsString( 'Snapshot title', $result->html() );
		self::assertStringContainsString( 'https://example.com/hero.jpg', $result->html() );
	}

	public function test_a_post_block_outside_a_card_fails_with_a_binding_diagnostic(): void {
		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/columns',
								'attrs'       => array(),
								'innerBlocks' => array(
									array(
										'blockName'   => 'campaignbridge/column',
										'attrs'       => array(),
										'innerBlocks' => array(
											array(
												'blockName' => 'campaignbridge/post-title',
												'attrs' => array(),
												'innerBlocks' => array(),
											),
										),
									),
								),
							),
						),
					),
				),
			),
		);

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.title.missing', $result->diagnostics()[0]->code() );
	}

	/**
	 * Build a stacked post card document.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/post-card',
						'attrs'       => array(
							'postId'   => 7,
							'postType' => 'post',
						),
						'innerBlocks' => array(
							$this->block( 'post-image' ),
							$this->block( 'post-title' ),
							$this->block( 'post-excerpt' ),
							$this->block( 'post-cta' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Build a post card whose media sits beside the copy.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function media_left_document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/post-card',
						'attrs'       => array(
							'postId'   => 7,
							'postType' => 'post',
						),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/columns',
								'attrs'       => array( 'verticalAlign' => 'middle' ),
								'innerBlocks' => array(
									array(
										'blockName'   => 'campaignbridge/column',
										'attrs'       => array( 'width' => 35 ),
										'innerBlocks' => array( $this->block( 'post-image' ) ),
									),
									array(
										'blockName'   => 'campaignbridge/column',
										'attrs'       => array( 'width' => 65 ),
										'innerBlocks' => array(
											$this->block( 'post-title' ),
											$this->block( 'post-excerpt' ),
											$this->block( 'post-cta', array( 'style' => 'link' ) ),
										),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Build one post binding block.
	 *
	 * @param string               $name  Block slug.
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array<string, mixed>
	 */
	private function block( string $name, array $attrs = array() ): array {
		return array(
			'blockName'   => 'campaignbridge/' . $name,
			'attrs'       => $attrs,
			'innerBlocks' => array(),
		);
	}

	/** Build a context carrying one immutable post snapshot. */
	private function context(): Render_Context {
		return new Render_Context(
			array( 'title' => 'Parity fixture' ),
			array(
				'posts' => array(
					'7' => array(
						'title'   => 'Snapshot title',
						'excerpt' => 'Snapshot excerpt copy.',
						'url'     => 'https://example.com/posts/7',
						'image'   => array(
							'url'    => 'https://example.com/hero.jpg',
							'alt'    => 'Campaign hero',
							'width'  => 600,
							'height' => 320,
						),
					),
				),
			),
			array(),
			'universal@1'
		);
	}
}
