<?php
/**
 * Post binding block control-surface tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Post_Snapshot;
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
		$document[0]['innerBlocks'][0]['innerBlocks'][1]                        = Email_Compiler_Test::bound_title( true );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<a href="https://example.com/posts/7" style="text-decoration:none"><img', $result->html() );
		self::assertStringContainsString( '<a href="https://example.com/posts/7" style="color:#111111;text-decoration:none">Snapshot title</a>', $result->html() );
	}

	public function test_an_unlinked_bound_title_emits_no_anchor(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '>Snapshot title</h2>', $result->html() );
		self::assertStringNotContainsString( 'Snapshot title</a>', $result->html() );
	}

	public function test_drops_alt_text_for_a_decorative_image(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['decorative'] = true;

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'alt="" role="presentation"', $result->html() );
		self::assertSame( '', $result->assets()[0]['alt'] );
	}

	public function test_renders_a_custom_font_size_for_the_post_title(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['style'] = array( 'typography' => array( 'fontSize' => '32px' ) );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'font-size:32px', $result->html() );
	}

	public function test_renders_the_call_to_action_as_a_native_ghost_text_link(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = Email_Compiler_Test::bound_button(
			array( 'field' => 'url' ),
			array( 'className' => 'is-style-ghost' )
		);

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'background-color:transparent;border:none', $result->html() );
		self::assertStringContainsString( 'text-decoration:underline', $result->html() );
	}

	public function test_ghost_style_paints_from_its_own_colour_rather_than_the_button_fill(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = Email_Compiler_Test::bound_button(
			array( 'field' => 'url' ),
			array(
				'className'       => 'is-style-ghost',
				'backgroundColor' => '#0000ee',
			)
		);

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'color:#0000ee;text-decoration:underline', $result->html() );
	}

	public function test_keeps_the_bulletproof_button_as_the_default_style(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'v:roundrect', $result->html() );
	}

	public function test_rejects_an_unknown_call_to_action_style(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = Email_Compiler_Test::bound_button(
			array( 'field' => 'url' ),
			array( 'className' => 'is-style-pill' )
		);

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	public function test_a_bound_core_block_outside_a_post_card_fails_closed(): void {
		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array( Email_Compiler_Test::bound_excerpt() ),
					),
				),
			),
		);

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.binding.unbound', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
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

	public function test_compiles_a_post_card_laid_out_with_columns_media_right(): void {
		$document = $this->media_left_document();
		$columns  = &$document[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'];
		$columns  = array_reverse( $columns );

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		// Reordering the columns keeps the post binding intact and swaps the visual order.
		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'width="35%"', $result->html() );
		self::assertStringContainsString( 'width="65%"', $result->html() );
		self::assertStringContainsString( 'Snapshot title', $result->html() );

		$text   = strpos( $result->html(), 'Snapshot title' );
		$image  = strpos( $result->html(), 'https://example.com/hero.jpg' );
		self::assertNotFalse( $text );
		self::assertNotFalse( $image );
		self::assertLessThan( $image, $text );
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
											Email_Compiler_Test::bound_title(),
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
		self::assertSame( 'post.binding.unbound', $result->diagnostics()[0]->code() );
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
							Email_Compiler_Test::bound_title(),
							Email_Compiler_Test::bound_excerpt(),
							Email_Compiler_Test::bound_button( array( 'field' => 'url' ) ),
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
											Email_Compiler_Test::bound_title(),
											Email_Compiler_Test::bound_excerpt(),
											Email_Compiler_Test::bound_button( array( 'field' => 'url' ), array( 'className' => 'is-style-ghost' ) ),
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
					'7' => Post_Snapshot::create( 7, 'post', array(
						'title'   => 'Snapshot title',
						'excerpt' => 'Snapshot excerpt copy.',
						'url'     => 'https://example.com/posts/7',
						'image'   => array(
							'url'    => 'https://example.com/hero.jpg',
							'alt'    => 'Campaign hero',
							'width'  => 600,
							'height' => 320,
						),
					) ),
				),
			),
			array(),
			'universal@1'
		);
	}
}
