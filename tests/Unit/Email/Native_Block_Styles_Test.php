<?php
/** Native block styling through the complete email compiler. @package CampaignBridge */
declare(strict_types=1);
namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Native_Block_Styles_Test extends TestCase {
	/** @dataProvider native_styles */
	public function test_native_controls_reach_email_html( string $name, array $attributes, string $expected ): void {
		$result = Compiler_Factory::create()->compile( $this->document( $name, $attributes ), $this->context() );
		self::assertTrue( $result->is_success(), implode( ', ', array_map( static fn( $diagnostic ) => $diagnostic->to_array()['message'], $result->diagnostics() ) ) );
		self::assertStringContainsString( $expected, $result->html() );
		self::assertStringNotContainsString( 'var:preset|', $result->html() );
	}

	public static function native_styles(): array {
		$cases = array();
		foreach ( array( 'text', 'heading', 'compliance-footer', 'button', 'container' ) as $name ) {
			$cases[ $name . ' custom text' ] = array( $name, array( 'style' => array( 'color' => array( 'text' => '#123456' ) ) ), 'color:#123456' );
			$cases[ $name . ' text preset' ] = array( $name, array( 'textColor' => 'brand' ), 'color:#1a6dcc' );
		}
		foreach ( array( 'text', 'container', 'section', 'column', 'post-card', 'button' ) as $name ) {
			$cases[ $name . ' background' ] = array( $name, array( 'style' => array( 'color' => array( 'background' => '#abcdef' ) ) ), 'background-color:#abcdef' );
		}
		foreach ( array( 'text', 'heading' ) as $name ) {
			$cases[ $name . ' font preset' ] = array( $name, array( 'fontSize' => 'large' ), 'font-size:20px' );
			$cases[ $name . ' typography' ]  = array(
				$name,
				array(
					'style' => array(
						'typography' => array(
							'fontSize'   => '1.5rem',
							'lineHeight' => '1.8',
						),
					),
				),
				'line-height:1.8',
			);
		}
		foreach ( array( 'text', 'container', 'section', 'post-card', 'compliance-footer' ) as $name ) {
			$cases[ $name . ' padding' ] = array( $name, array( 'style' => array( 'spacing' => array( 'padding' => 'var:preset|spacing|20' ) ) ), 'padding:8px 8px 8px 8px' );
		}
		$cases['native variant and colors together'] = array(
			'button',
			array(
				'className' => 'is-style-outline',
				'style'     => array( 'color' => array( 'background' => '#123456' ) ),
			),
			'#123456',
		);
		$cases['section margin']                     = array( 'section', array( 'style' => array( 'spacing' => array( 'margin' => '8px' ) ) ), 'padding:8px 8px 8px 8px' );
		$cases['text margin']                        = array( 'text', array( 'style' => array( 'spacing' => array( 'margin' => '8px' ) ) ), 'margin:8px 8px 8px 8px' );
		$cases['image margin']                       = array( 'image', array( 'style' => array( 'spacing' => array( 'margin' => '8px' ) ) ), 'margin:8px 8px 8px 8px' );
		$cases['native content width']               = array(
			'container',
			array(
				'layout' => array(
					'type'        => 'constrained',
					'contentSize' => '720px',
				),
			),
			'width="720"',
		);
		$cases['native gap']                         = array( 'columns', array( 'style' => array( 'spacing' => array( 'blockGap' => '16px' ) ) ), 'padding-right:8px' );
		$cases['core spacer height']                 = array( 'spacer', array( 'height' => '48px' ), 'height="48"' );
		$cases['core separator custom color']        = array( 'divider', array( 'style' => array( 'color' => array( 'background' => '#123456' ) ) ), 'border-top:1px solid #123456' );
		$cases['core separator preset color']        = array( 'divider', array( 'backgroundColor' => 'brand' ), 'border-top:1px solid #1a6dcc' );
		$cases['ghost button paints from its background'] = array(
			'button',
			array(
				'className' => 'is-style-ghost',
				'style'     => array( 'color' => array( 'background' => '#123456' ) ),
			),
			'color:#123456;text-decoration:underline',
		);
		foreach ( array( 'text', 'heading' ) as $name ) {
			$cases[ $name . ' core text alignment' ] = array( $name, array( 'style' => array( 'typography' => array( 'textAlign' => 'center' ) ) ), 'text-align:center' );
		}
		return $cases;
	}

	public function test_a_theme_palette_slug_names_the_attribute_that_set_it(): void {
		// A colour slug from the site theme has no email equivalent. The
		// diagnostic has to name the attribute the author set, not a generic
		// "color", or the template cannot be repaired.
		$result = Compiler_Factory::create()->compile(
			$this->document( 'section', array( 'backgroundColor' => 'laao-white' ) ),
			$this->context()
		);

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertStringEndsWith( '.attrs.backgroundColor', $result->diagnostics()[0]->path() );
		self::assertStringContainsString( 'backgroundColor', $result->diagnostics()[0]->to_array()['message'] );
	}

	public function test_unsupported_native_properties_fail_visibly(): void {
		$result = Compiler_Factory::create()->compile( $this->document( 'text', array( 'style' => array( 'typography' => array( 'letterSpacing' => '2px' ) ) ) ), $this->context() );
		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	private function node( string $name, array $attributes = array(), array $children = array() ): array {
		$block_names = array(
			'text'    => 'core/paragraph',
			'heading' => 'core/heading',
			'image'   => 'core/image',
			'button'  => 'core/button',
			'buttons' => 'core/buttons',
			'divider' => 'core/separator',
			'spacer'  => 'core/spacer',
		);
		$block_name = $block_names[ $name ] ?? 'campaignbridge/' . $name;

		return array(
			'blockName'   => $block_name,
			'attrs'       => array_filter( $attributes, static fn( $value ) => null !== $value ),
			'innerBlocks' => $children,
			'innerHTML'   => 'button' === $name ? '<div class="wp-block-button"><a class="wp-block-button__link">Button</a></div>' : '',
		);
	}

	private function document( string $name, array $attributes ): array {
		$defaults = array(
			'text'              => array( 'content' => 'Text' ),
			'heading'           => array( 'content' => 'Heading' ),
			'image'             => array(
				'url'    => 'https://example.com/image.jpg',
				'alt'    => 'Image',
				'width'  => 600,
				'height' => 400,
			),
			'button'            => array( 'url' => 'https://example.com' ),
			'post-card'         => array( 'postId' => 42 ),
			'compliance-footer' => array( 'address' => '123 Example St' ),
		);
		if ( 'button' === $name ) {
			$button = $this->node( 'button', array_merge( array( 'url' => 'https://example.com', 'text' => 'Button' ), $attributes ) );
			$block  = $this->node( 'buttons', array(), array( $button ) );
		} else {
			$block = $this->node( $name, array_merge( $defaults[ $name ] ?? array(), $attributes ) );
		}
		$text = $this->node( 'text', array( 'content' => 'Text' ) );
		if ( 'container' === $name ) {
			$block['innerBlocks'] = array( $this->node( 'section', array(), array( $text ) ) );
			return array( $block ); }
		if ( 'compliance-footer' === $name ) {
			return array( $this->node( 'container', array(), array( $block ) ) ); }
		if ( 'section' === $name ) {
			$block['innerBlocks'] = array( $text ); }
		if ( 'columns' === $name ) {
			$block['innerBlocks'] = array( $this->node( 'column', array(), array( $text ) ), $this->node( 'column', array(), array( $text ) ) ); }
		if ( 'column' === $name ) {
			$block['innerBlocks'] = array( $text );
			$block                = $this->node( 'columns', array(), array( $block, $this->node( 'column', array(), array( $text ) ) ) ); }
		if ( 'post-card' === $name ) {
			$block['innerBlocks'] = array( $this->node( 'heading', array( 'content' => 'Title' ) ) ); } elseif ( str_starts_with( $name, 'post-' ) ) {
			$block = $this->node( 'post-card', array( 'postId' => 42 ), array( $block ) ); }
			if ( 'section' !== $name ) {
				$block = $this->node( 'section', array(), array( $block ) ); }
			return array( $this->node( 'container', array(), array( $block ) ) );
	}

	private function context(): Render_Context {
		return new Render_Context(
			array( 'unsubscribe_url' => 'https://example.com/unsubscribe' ),
			array(
				'posts' => array(
					'42' => Post_Snapshot::create( 42, 'post', array(
						'title'   => 'Title',
						'excerpt' => 'Excerpt',
						'url'     => 'https://example.com/post',
					) ),
				),
				)
		);
	}
}
