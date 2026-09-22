<?php
/**
 * Linked email video-poster compiler tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Renderer\Video_Renderer;
use PHPUnit\Framework\TestCase;

/** Covers the bounded video source and deterministic transport output. */
final class Video_Block_Test extends TestCase {
	/** The renderer emits exact reviewed HTML and text without playable media. */
	public function test_golden_output_assets_and_fingerprint_are_deterministic(): void {
		$attributes = $this->valid_attributes();
		$renderer   = new Video_Renderer();
		$context    = $this->context();
		$block      = $renderer->normalize( new Block_Node( 'campaignbridge/video', $attributes, array(), '0.1.0' ) );
		$fixture    = dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/';

		self::assertSame( rtrim( (string) file_get_contents( $fixture . 'video.html' ), "\n" ), $renderer->render_html( $block, '', $context ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local golden fixture.
		self::assertSame( (string) file_get_contents( $fixture . 'video.txt' ), $renderer->render_text( $block, '', $context ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local golden fixture.

		$first  = Compiler_Factory::create()->compile( $this->document( $attributes ), $context );
		$second = Compiler_Factory::create()->compile( $this->document( $attributes ), $context );
		self::assertTrue( $first->is_success() );
		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->text(), $second->text() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
		self::assertSame(
			array(
				array(
					'type'   => 'image',
					'url'    => $attributes['posterUrl'],
					'width'  => 600,
					'height' => 338,
					'alt'    => 'Product demo & tour',
				),
			),
			$first->assets()
		);
		self::assertStringNotContainsString( '<video', $first->html() );
		self::assertStringNotContainsString( '<iframe', $first->html() );
		self::assertStringNotContainsString( '<script', $first->html() );
		self::assertStringNotContainsString( '<svg', $first->html() );
	}

	/** Invalid source values fail with stable paths and no partial output. */
	public function test_rejects_invalid_attributes(): void {
		$cases = array(
			'posterUrl' => array( 'posterUrl' => 'http://example.com/private-token.jpg' ),
			'videoUrl'  => array( 'videoUrl' => 'https://private-token@example.com/watch' ),
			'posterAlt' => array( 'posterAlt' => '<strong>private-token</strong>' ),
			'label'     => array( 'label' => str_repeat( 'a', Video_Renderer::MAX_LABEL_LENGTH + 1 ) ),
			'width'     => array( 'width' => Video_Renderer::MIN_WIDTH - 1 ),
			'height'    => array( 'height' => Video_Renderer::MAX_HEIGHT + 1 ),
		);

		foreach ( $cases as $attribute => $changes ) {
			$result = Compiler_Factory::create()->compile( $this->document( array_replace( $this->valid_attributes(), $changes ) ), $this->context() );
			self::assertFalse( $result->is_success(), $attribute );
			self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code(), $attribute );
			self::assertStringEndsWith( '.attrs.' . $attribute, $result->diagnostics()[0]->path(), $attribute );
			self::assertSame( '', $result->html(), $attribute );
			self::assertSame( array(), $result->assets(), $attribute );
			self::assertStringNotContainsString( 'private-token', $result->diagnostics()[0]->to_array()['message'], $attribute );
		}
	}

	/** The documented dimensions and text lengths remain valid at their bounds. */
	public function test_accepts_exact_bounds(): void {
		$attributes              = $this->valid_attributes();
		$attributes['posterAlt'] = str_repeat( 'a', Video_Renderer::MAX_ALT_LENGTH );
		$attributes['label']     = str_repeat( 'b', Video_Renderer::MAX_LABEL_LENGTH );
		$attributes['width']     = Video_Renderer::MAX_WIDTH;
		$attributes['height']    = Video_Renderer::MIN_HEIGHT;
		$result                  = Compiler_Factory::create()->compile( $this->document( $attributes ), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'width="900" height="90"', $result->html() );
	}

	/** Unknown attributes and nested content never disappear silently. */
	public function test_rejects_unknown_attributes_and_children(): void {
		$document = $this->document( $this->valid_attributes() );
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['autoplay'] = true;
		$result = Compiler_Factory::create()->compile( $document, $this->context() );
		self::assertSame( 'block.attributes.unsupported', $result->diagnostics()[0]->code() );

		$document = $this->document( $this->valid_attributes() );
		$document[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][] = array(
			'blockName'   => 'core/paragraph',
			'attrs'       => array( 'content' => 'Hidden' ),
			'innerBlocks' => array(),
		);
		$result = Compiler_Factory::create()->compile( $document, $this->context() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
	}

	/**
	 * Build valid video attributes.
	 *
	 * @return array<string, int|string>
	 */
	private function valid_attributes(): array {
		return array(
			'posterUrl' => 'https://example.com/poster.jpg',
			'posterAlt' => 'Product demo & tour',
			'videoUrl'  => 'https://example.com/watch?a=1&b=2',
			'label'     => 'Watch the demo',
			'width'     => 600,
			'height'    => 338,
		);
	}

	/**
	 * Build a complete document with one video block.
	 *
	 * @param array<string, mixed> $attributes Video attributes.
	 */
	private function document( array $attributes ): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/video',
								'attrs'       => $attributes,
								'innerBlocks' => array(),
							),
						),
					),
					array(
						'blockName'   => 'campaignbridge/compliance-footer',
						'attrs'       => array( 'address' => '123 Example St' ),
						'innerBlocks' => array(),
					),
				),
			),
		);
	}

	/** Build the fixture's immutable render context. */
	private function context(): Render_Context {
		return new Render_Context(
			array(
				'title'           => 'Video fixture',
				'unsubscribe_url' => 'https://example.com/unsubscribe',
			),
			array(),
			array(),
			'universal@1'
		);
	}
}
