<?php
/** Native block styling through the complete email compiler. @package CampaignBridge */
declare(strict_types=1);
namespace CampaignBridge\Tests\Unit\Email;

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
		foreach ( array( 'text', 'heading', 'post-title', 'post-excerpt', 'compliance-footer', 'button', 'post-button', 'container' ) as $name ) {
			$cases[ $name . ' custom text' ] = array( $name, array( 'style' => array( 'color' => array( 'text' => '#123456' ) ) ), 'color:#123456' );
			$cases[ $name . ' text preset' ] = array( $name, array( 'textColor' => 'brand' ), 'color:#1a6dcc' );
		}
		foreach ( array( 'text', 'container', 'section', 'column', 'post-card', 'button', 'post-button' ) as $name ) {
			$cases[ $name . ' background' ] = array( $name, array( 'style' => array( 'color' => array( 'background' => '#abcdef' ) ) ), 'background-color:#abcdef' );
		}
		foreach ( array( 'text', 'heading', 'post-title', 'post-excerpt' ) as $name ) {
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
		foreach ( array( 'post-link', 'post-button' ) as $name ) {
			$cases[ $name . ' link color' ]  = array(
				$name,
				array(
					'className' => 'post-button' === $name ? 'is-style-link' : null,
					'style'     => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#123456' ) ) ) ),
				),
				'color:#123456',
			);
			$cases[ $name . ' hover color' ] = array( $name, array( 'style' => array( 'elements' => array( 'link' => array( ':hover' => array( 'color' => array( 'text' => '#123456' ) ) ) ) ) ), ':hover{color:#123456!important}' );
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
		$cases['native spacer']                      = array( 'spacer', array( 'style' => array( 'dimensions' => array( 'minHeight' => '48px' ) ) ), 'height="48"' );
		$cases['native divider']                     = array(
			'divider',
			array(
				'style' => array(
					'border' => array(
						'width' => '3px',
						'style' => 'dashed',
						'color' => '#123456',
					),
				),
			),
			'border-top:3px dashed #123456',
		);
		$cases['filled button native link color']    = array( 'post-button', array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#123456' ) ) ) ) ), 'color:#123456' );
		$cases['zero native border']                 = array( 'divider', array( 'style' => array( 'border' => array( 'width' => '0px' ) ) ), 'border:0px solid' );
		$cases['native border side']                 = array(
			'divider',
			array(
				'style' => array(
					'border' => array(
						'left' => array(
							'width' => '2px',
							'color' => '#123456',
						),
					),
				),
			),
			'border-left:2px solid #123456',
		);
		return $cases;
	}

	public function test_unsupported_native_properties_fail_visibly(): void {
		$result = Compiler_Factory::create()->compile( $this->document( 'text', array( 'style' => array( 'typography' => array( 'letterSpacing' => '2px' ) ) ) ), $this->context() );
		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
	}

	private function node( string $name, array $attributes = array(), array $children = array() ): array {
		return array(
			'blockName'   => 'campaignbridge/' . $name,
			'attrs'       => array_filter( $attributes, static fn( $value ) => null !== $value ),
			'innerBlocks' => $children,
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
		$block    = $this->node( $name, array_merge( $defaults[ $name ] ?? array(), $attributes ) );
		$text     = $this->node( 'text', array( 'content' => 'Text' ) );
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
			$block['innerBlocks'] = array( $this->node( 'post-title' ) ); } elseif ( str_starts_with( $name, 'post-' ) ) {
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
					'42' => array(
						'title'   => 'Title',
						'excerpt' => 'Excerpt',
						'url'     => 'https://example.com/post',
					),
				),
				)
		);
	}
}
