<?php
/**
 * Native email navigation compiler tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Renderer\Navigation_Renderer;
use PHPUnit\Framework\TestCase;

/** Covers the bounded navigation source and deterministic transport output. */
final class Navigation_Block_Test extends TestCase {
	/** The renderer emits exact reviewed HTML and text for the same source. */
	public function test_golden_output_and_fingerprint_are_deterministic(): void {
		$items    = array(
			array(
				'label' => 'Shop & save',
				'url'   => 'https://example.com/shop?a=1&b=2',
			),
			array(
				'label' => 'About',
				'url'   => 'https://example.com/about',
			),
		);
		$renderer = new Navigation_Renderer();
		$context  = $this->context();
		$block    = $renderer->normalize( new Block_Node( 'campaignbridge/navigation', array( 'items' => $items ), array(), '0.1.0' ) );
		$fixture  = dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/';

		self::assertSame( rtrim( (string) file_get_contents( $fixture . 'navigation.html' ), "\n" ), $renderer->render_html( $block, '', $context ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local golden fixture.
		self::assertSame( (string) file_get_contents( $fixture . 'navigation.txt' ), $renderer->render_text( $block, '', $context ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local golden fixture.

		$first  = Compiler_Factory::create()->compile( $this->document( $items ), $context );
		$second = Compiler_Factory::create()->compile( $this->document( $items ), $context );
		self::assertTrue( $first->is_success() );
		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->text(), $second->text() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
		self::assertStringContainsString( $renderer->render_html( $block, '', $context ), $first->html() );
		self::assertStringContainsString( $renderer->render_text( $block, '', $context ), $first->text() );
		self::assertStringNotContainsString( '<script', $first->html() );
		self::assertStringNotContainsString( '<svg', $first->html() );
	}

	/**
	 * Invalid data fails with stable paths and diagnostics without leaking values.
	 *
	 * @dataProvider invalid_items
	 * @param array<int|string, mixed> $items Invalid author input.
	 * @param string                   $path  Expected diagnostic path suffix.
	 */
	public function test_rejects_invalid_items( array $items, string $path ): void {
		$result = Compiler_Factory::create()->compile( $this->document( $items ), $this->context() );
		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertStringEndsWith( $path, $result->diagnostics()[0]->path() );
		self::assertSame( '', $result->html() );
		self::assertStringNotContainsString( 'private-token', $result->diagnostics()[0]->to_array()['message'] );
	}

	/** The documented item and label maxima remain valid. */
	public function test_accepts_exact_bounds(): void {
		$items  = array_fill( 0, Navigation_Renderer::MAX_ITEMS, array( 'label' => str_repeat( 'a', Navigation_Renderer::MAX_LABEL_LENGTH ), 'url' => 'https://example.com' ) );
		$result = Compiler_Factory::create()->compile( $this->document( $items ), $this->context() );
		self::assertTrue( $result->is_success() );
		self::assertSame( Navigation_Renderer::MAX_ITEMS, substr_count( $result->html(), 'class="cb-nav-item"' ) );
	}

	/**
	 * Invalid navigation source cases.
	 *
	 * @return array<string, array{array<int|string, mixed>, string}>
	 */
	public static function invalid_items(): array {
		$valid = array(
			'label' => 'Home',
			'url'   => 'https://example.com',
		);
		return array(
			'empty'         => array( array(), '.attrs.items' ),
			'too many'      => array( array_fill( 0, 6, $valid ), '.attrs.items' ),
			'object'        => array( array( 'first' => $valid ), '.attrs.items' ),
			'unknown field' => array( array( $valid + array( 'icon' => '<svg>' ) ), '.attrs.items.0' ),
			'no label'      => array(
				array(
					array(
						'label' => '',
						'url'   => $valid['url'],
					),
				),
				'.attrs.items.0.label',
			),
			'long label'    => array(
				array(
					array(
						'label' => str_repeat( 'a', 25 ),
						'url'   => $valid['url'],
					),
				),
				'.attrs.items.0.label',
			),
			'markup label'  => array(
				array(
					array(
						'label' => '<script>private-token</script>',
						'url'   => $valid['url'],
					),
				),
				'.attrs.items.0.label',
			),
			'wrong label'   => array(
				array(
					array(
						'label' => 10,
						'url'   => $valid['url'],
					),
				),
				'.attrs.items.0.label',
			),
			'http'          => array(
				array(
					array(
						'label' => 'Home',
						'url'   => 'http://example.com',
					),
				),
				'.attrs.items.0.url',
			),
			'userinfo'      => array(
				array( array( 'label' => 'Home', 'url' => 'https://private-token@example.com' ) ),
				'.attrs.items.0.url',
			),
			'javascript'    => array(
				array(
					array(
						'label' => 'Home',
						'url'   => 'javascript:private-token',
					),
				),
				'.attrs.items.0.url',
			),
			'wrong url'     => array(
				array(
					array(
						'label' => 'Home',
						'url'   => 10,
					),
				),
				'.attrs.items.0.url',
			),
			'relative'      => array(
				array(
					array(
						'label' => 'Home',
						'url'   => '/private-token',
					),
				),
				'.attrs.items.0.url',
			),
		);
	}

	/** Unknown attributes and invalid nesting never silently disappear. */
	public function test_rejects_unknown_attributes_and_children(): void {
		$items                        = array(
			array(
				'label' => 'Home',
				'url'   => 'https://example.com',
			),
		);
		$document                     = $this->document( $items );
		$navigation                   = &$document[0]['innerBlocks'][0]['innerBlocks'][0];
		$navigation['attrs']['style'] = array( 'color' => array( 'text' => '#ff0000' ) );
		$result                       = Compiler_Factory::create()->compile( $document, $this->context() );
		self::assertSame( 'block.attributes.unsupported', $result->diagnostics()[0]->code() );

		unset( $navigation['attrs']['style'] );
		$navigation['innerBlocks'][] = array(
			'blockName'   => 'core/paragraph',
			'attrs'       => array( 'content' => 'Hidden' ),
			'innerBlocks' => array(),
		);
		$result                      = Compiler_Factory::create()->compile( $document, $this->context() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
	}

	/**
	 * Build a complete document with navigation.
	 *
	 * @param array<int|string, mixed> $items Navigation links.
	 * @return array<int, array<string, mixed>>
	 */
	private function document( array $items ): array {
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
								'blockName'   => 'campaignbridge/navigation',
								'attrs'       => array( 'items' => $items ),
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
				'title'           => 'Navigation fixture',
				'unsubscribe_url' => 'https://example.com/unsubscribe',
			),
			array(),
			array(),
			'universal@1'
		);
	}
}
