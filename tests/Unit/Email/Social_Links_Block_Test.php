<?php
/**
 * Core Social Icons email normalization and rendering tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Social_Links_Block_Test extends TestCase {
	public function test_core_social_icons_compile_with_packaged_assets_and_plain_text_urls(): void {
		$result = $this->compile(
			array(
				'align'        => 'center',
				'openInNewTab' => true,
				'showLabels'   => true,
				'size'         => 'has-large-icon-size',
				'style'        => array( 'spacing' => array( 'blockGap' => '12px' ) ),
			),
			array(
				array(
					'service' => 'facebook',
					'url'     => 'https://example.com/facebook',
					'label'   => 'Follow on Facebook',
				),
				array(
					'service' => 'youtube',
					'url'     => 'https://example.com/youtube',
				),
			)
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'role="navigation" aria-label="Social links"', $result->html() );
		self::assertStringContainsString( 'align="center"', $result->html() );
		self::assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $result->html() );
		self::assertStringContainsString( 'aria-label="Follow on Facebook"', $result->html() );
		self::assertStringContainsString( '/assets/email/social/facebook.png', $result->html() );
		self::assertStringContainsString( 'width="36" height="36"', $result->html() );
		self::assertStringNotContainsString( '<svg', $result->html() );
		self::assertStringContainsString( 'Follow on Facebook: https://example.com/facebook', $result->text() );
		self::assertStringContainsString( 'YouTube: https://example.com/youtube', $result->text() );
		$portable_html = str_replace(
			\set_url_scheme( \CampaignBridge_Plugin::url(), 'https' ),
			'https://example.test/wp-content/plugins/campaignbridge/',
			$result->html()
		);
		self::assertStringContainsString( rtrim( $this->fixture( 'social-links.html' ), "\n" ), $portable_html );
		self::assertStringContainsString( $this->fixture( 'social-links.txt' ), $result->text() );
		self::assertCount( 2, $result->assets() );
		self::assertSame( 'image', $result->assets()[0]['type'] );
		self::assertSame( 36, $result->assets()[0]['width'] );
		self::assertStringStartsWith( 'https://', $result->assets()[0]['url'] );
	}

	/**
	 * @dataProvider invalid_children
	 *
	 * @param array<string, mixed> $child Child attributes.
	 * @param string               $path  Expected diagnostic path suffix.
	 */
	public function test_invalid_social_link_values_fail_closed( array $child, string $path ): void {
		$result = $this->compile( array(), array( $child ) );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertStringEndsWith( $path, $result->diagnostics()[0]->path() );
		self::assertSame( '', $result->html() );
	}

	/** @return array<string, array{0: array<string, mixed>, 1: string}> */
	public static function invalid_children(): array {
		return array(
			'unsupported service' => array(
				array(
					'service' => 'wordpress',
					'url'     => 'https://example.com',
				),
				'.attrs.service',
			),
			'insecure URL'        => array(
				array(
					'service' => 'facebook',
					'url'     => 'http://example.com',
				),
				'.attrs.url',
			),
			'URL credentials'     => array(
				array(
					'service' => 'facebook',
					'url'     => 'https://user:pass@example.com',
				),
				'.attrs.url',
			),
			'markup label'        => array(
				array(
					'service' => 'facebook',
					'url'     => 'https://example.com',
					'label'   => '<b>Follow</b>',
				),
				'.attrs.label',
			),
			'long label'          => array(
				array(
					'service' => 'facebook',
					'url'     => 'https://example.com',
					'label'   => str_repeat( 'x', 41 ),
				),
				'.attrs.label',
			),
		);
	}

	public function test_social_icons_are_limited_to_six_items(): void {
		$children = array_fill(
			0,
			7,
			array(
				'service' => 'x',
				'url'     => 'https://example.com/x',
			)
		);
		$result   = $this->compile( array(), $children );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'social.items.invalid', $result->diagnostics()[0]->code() );
	}

	/**
	 * @dataProvider invalid_parents
	 *
	 * @param array<string, mixed> $attributes Parent attributes.
	 * @param string               $path       Expected path suffix.
	 */
	public function test_unsupported_core_parent_controls_fail_closed( array $attributes, string $path ): void {
		$result = $this->compile(
			$attributes,
			array(
				array(
					'service' => 'x',
					'url'     => 'https://example.com/x',
				),
			)
		);

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertStringEndsWith( $path, $result->diagnostics()[0]->path() );
	}

	/** @return array<string, array{0: array<string, mixed>, 1: string}> */
	public static function invalid_parents(): array {
		return array(
			'authored icon colour'  => array( array( 'iconColor' => 'brand' ), '.attrs.iconColor' ),
			'vertical layout'       => array(
				array(
					'layout' => array(
						'type'        => 'flex',
						'orientation' => 'vertical',
					),
				),
				'.attrs.layout',
			),
			'wrapping layout'       => array(
				array(
					'layout' => array(
						'type'     => 'flex',
						'flexWrap' => 'wrap',
					),
				),
				'.attrs.layout.flexWrap',
			),
			'conflicting alignment' => array(
				array(
					'align'  => 'left',
					'layout' => array(
						'type'           => 'flex',
						'justifyContent' => 'right',
					),
				),
				'.attrs.layout.justifyContent',
			),
			'pill style'            => array( array( 'className' => 'is-style-pill-shape' ), '.attrs.className' ),
			'oversized gap'         => array( array( 'style' => array( 'spacing' => array( 'blockGap' => '40px' ) ) ), '.attrs.style.spacing.blockGap' ),
		);
	}

	/**
	 * Compile one Social Icons tree inside a valid document.
	 *
	 * @param array<string, mixed>             $parent   Parent attributes.
	 * @param array<int, array<string, mixed>> $children Child attributes.
	 */
	private function compile( array $parent, array $children ): Compile_Result {
		$inner = array_map(
			static fn ( array $attributes ): array => array(
				'blockName'    => 'core/social-link',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$children
		);

		return Compiler_Factory::create()->compile(
			array(
				array(
					'blockName'   => 'campaignbridge/container',
					'attrs'       => array(),
					'innerBlocks' => array(
						array(
							'blockName'   => 'campaignbridge/section',
							'attrs'       => array(),
							'innerBlocks' => array(
								array(
									'blockName'    => 'core/social-links',
									'attrs'        => $parent,
									'innerBlocks'  => $inner,
									'innerHTML'    => '<ul class="wp-block-social-links"></ul>',
									'innerContent' => array(),
								),
							),
							'innerHTML'   => '',
						),
					),
					'innerHTML'   => '',
				),
			),
			new Render_Context()
		);
	}

	/** Read one reviewed golden fixture. */
	private function fixture( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local golden fixture.
	}

	/** Format compiler diagnostics for assertion messages. */
	private function diagnostics( Compile_Result $result ): string {
		return implode(
			', ',
			array_map(
				static fn ( $diagnostic ): string => $diagnostic->code() . '@' . $diagnostic->path(),
				$result->diagnostics()
			)
		);
	}
}
