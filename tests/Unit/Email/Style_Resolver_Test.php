<?php
/**
 * Core style resolution tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Style_Resolver;
use PHPUnit\Framework\TestCase;

final class Style_Resolver_Test extends TestCase {
	public function test_resolves_a_preset_slug_written_to_the_top_level_attribute(): void {
		self::assertSame(
			'#1a6dcc',
			Style_Resolver::color( array( 'backgroundColor' => 'brand' ), 'background' )
		);
	}

	public function test_resolves_a_literal_written_into_the_style_tree(): void {
		self::assertSame(
			'#abcdef',
			Style_Resolver::color(
				array( 'style' => array( 'color' => array( 'text' => '#abcdef' ) ) ),
				'text'
			)
		);
	}

	public function test_resolves_a_preset_reference_written_into_the_style_tree(): void {
		self::assertSame(
			'#f4f4f4',
			Style_Resolver::color(
				array( 'style' => array( 'color' => array( 'background' => 'var:preset|color|card' ) ) ),
				'background'
			)
		);
	}

	public function test_the_preset_attribute_wins_over_the_style_tree(): void {
		// Core writes the slug attribute when a preset is chosen and clears the
		// literal, so the slug is the author's most recent decision.
		self::assertSame(
			'#111111',
			Style_Resolver::color(
				array(
					'backgroundColor' => 'text',
					'style'           => array( 'color' => array( 'background' => '#ff0000' ) ),
				),
				'background'
			)
		);
	}

	public function test_returns_the_fallback_when_nothing_was_chosen(): void {
		self::assertSame( '#ffffff', Style_Resolver::color( array(), 'background', '#ffffff' ) );
		self::assertNull( Style_Resolver::color( array(), 'background' ) );
	}

	public function test_resolves_a_colour_from_the_active_brand_kit(): void {
		$kit = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) );

		self::assertSame(
			'#ff5500',
			Style_Resolver::color( array( 'backgroundColor' => 'brand' ), 'background', null, $kit )
		);
		self::assertSame(
			'#ff5500',
			Style_Resolver::color(
				array( 'style' => array( 'color' => array( 'background' => 'var:preset|color|brand' ) ) ),
				'background',
				null,
				$kit
			)
		);
	}

	public function test_rejects_an_unknown_colour_preset(): void {
		$this->expectException( Invalid_Block_Attribute::class );
		Style_Resolver::color( array( 'backgroundColor' => 'chartreuse' ), 'background' );
	}

	public function test_rejects_a_colour_that_is_not_portable(): void {
		$this->expectException( Invalid_Block_Attribute::class );
		Style_Resolver::color(
			array( 'style' => array( 'color' => array( 'text' => 'rgb(1,2,3)' ) ) ),
			'text'
		);
	}

	public function test_resolves_a_font_size_preset_and_a_literal(): void {
		self::assertSame( 28, Style_Resolver::font_size( array( 'fontSize' => 'x-large' ) ) );
		self::assertSame(
			18,
			Style_Resolver::font_size( array( 'style' => array( 'typography' => array( 'fontSize' => '18px' ) ) ) )
		);
	}

	public function test_normalizes_rem_font_sizes_to_pixels(): void {
		self::assertSame(
			24,
			Style_Resolver::font_size( array( 'style' => array( 'typography' => array( 'fontSize' => '1.5rem' ) ) ) )
		);
	}

	public function test_rejects_a_font_size_outside_the_portable_range(): void {
		$this->expectException( Invalid_Block_Attribute::class );
		Style_Resolver::font_size( array( 'style' => array( 'typography' => array( 'fontSize' => '400px' ) ) ) );
	}

	public function test_resolves_spacing_presets_and_literals_per_side(): void {
		$spacing = Style_Resolver::spacing(
			array(
				'style' => array(
					'spacing' => array(
						'padding' => array(
							'top'    => 'var:preset|spacing|50',
							'right'  => '10px',
							'bottom' => '2rem',
						),
					),
				),
			),
			'padding',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
			)
		);

		self::assertSame(
			array(
				'top'    => 24,
				'right'  => 10,
				'bottom' => 32,
				'left'   => 0,
			),
			$spacing
		);
	}

	public function test_keeps_the_documented_default_for_sides_the_author_left_alone(): void {
		$spacing = Style_Resolver::spacing(
			array( 'style' => array( 'spacing' => array( 'padding' => array( 'top' => '4px' ) ) ) ),
			'padding',
			array(
				'top'    => 24,
				'right'  => 8,
				'bottom' => 24,
				'left'   => 8,
			)
		);

		self::assertSame( 4, $spacing['top'] );
		self::assertSame( 8, $spacing['right'] );
		self::assertSame( 24, $spacing['bottom'] );
	}

	public function test_rejects_an_unknown_spacing_preset(): void {
		$this->expectException( Invalid_Block_Attribute::class );
		Style_Resolver::spacing(
			array( 'style' => array( 'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|999' ) ) ) ),
			'padding',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
			)
		);
	}

	public function test_rejects_a_percentage_length_email_cannot_carry(): void {
		$this->expectException( Invalid_Block_Attribute::class );
		Style_Resolver::spacing(
			array( 'style' => array( 'spacing' => array( 'margin' => array( 'top' => '10%' ) ) ) ),
			'margin',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
			)
		);
	}

	public function test_resolves_a_unitless_line_height(): void {
		self::assertSame(
			1.6,
			Style_Resolver::line_height( array( 'style' => array( 'typography' => array( 'lineHeight' => '1.6' ) ) ) )
		);
		self::assertSame( 1.5, Style_Resolver::line_height( array(), 1.5 ) );
	}

	public function test_rejects_a_line_height_with_a_unit(): void {
		$this->expectException( Invalid_Block_Attribute::class );
		Style_Resolver::line_height( array( 'style' => array( 'typography' => array( 'lineHeight' => '24px' ) ) ) );
	}

	public function test_every_colour_preset_is_a_portable_six_digit_value(): void {
		foreach ( Design_Presets::colors() as $preset ) {
			self::assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $preset['color'], $preset['slug'] );
		}
	}

	public function test_every_size_preset_resolves_through_the_resolver(): void {
		foreach ( Design_Presets::font_sizes() as $preset ) {
			self::assertIsInt( Style_Resolver::font_size( array( 'fontSize' => $preset['slug'] ) ) );
		}

		foreach ( Design_Presets::spacing_sizes() as $preset ) {
			$spacing = Style_Resolver::spacing(
				array( 'style' => array( 'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|' . $preset['slug'] ) ) ) ),
				'padding',
				array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
				)
			);

			self::assertGreaterThan( 0, $spacing['top'], $preset['slug'] );
		}
	}
}
