<?php
/**
 * Theme style reader tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;
use CampaignBridge\Domain\Email\Theme_Brand_Mapper;
use CampaignBridge\Repository\Theme_Style_Reader;
use PHPUnit\Framework\TestCase;

final class Theme_Style_Reader_Test extends TestCase {
	public function test_resolves_oklch_custom_tokens_and_ignores_core_defaults(): void {
		$extracted = ( new Theme_Style_Reader() )->extract_from(
			array(
				'custom' => array(
					'color' => array(
						'black'       => 'oklch(6.72% 0 0)',
						'white'       => 'oklch(100% 0 0)',
						'red'         => 'oklch(57.71% 0.2152 27.33)',
						'dark-gray'   => 'oklch(34.458% 0 0)',
						'gray'        => 'oklch(55.208% 0 0)',
						'light-gray'  => 'oklch(91.887% 0 0)',
						'transparent' => 'oklch(0% 0 0 / 0)',
					),
				),
				'color'  => array(
					'palette' => array(
						'theme'   => array(
							array(
								'slug'  => 'laao-black',
								'name'  => 'Black',
								'color' => 'var(--wp--custom--color--black)',
							),
							array(
								'slug'  => 'laao-white',
								'name'  => 'White',
								'color' => 'var(--wp--custom--color--white)',
							),
							array(
								'slug'  => 'laao-red',
								'name'  => 'Red',
								'color' => 'var(--wp--custom--color--red)',
							),
							array(
								'slug'  => 'laao-dark-gray',
								'name'  => 'Dark gray',
								'color' => 'var(--wp--custom--color--dark-gray)',
							),
							array(
								'slug'  => 'laao-gray',
								'name'  => 'Gray',
								'color' => 'var(--wp--custom--color--gray)',
							),
							array(
								'slug'  => 'laao-light-gray',
								'name'  => 'Light gray',
								'color' => 'var(--wp--custom--color--light-gray)',
							),
							array(
								'slug'  => 'laao-transparent',
								'name'  => 'Transparent',
								'color' => 'var(--wp--custom--color--transparent)',
							),
						),
						'default' => array(
							array(
								'slug'  => 'vivid-cyan-blue',
								'name'  => 'Vivid cyan blue',
								'color' => '#0693e3',
							),
						),
					),
				),
			),
			array(
				'color'    => array(
					'text'       => 'var:preset|color|laao-black',
					'background' => 'var:preset|color|laao-light-gray',
				),
				'elements' => array(
					'link' => array(
						'color' => array(
							'text' => 'var:preset|color|laao-red',
						),
					),
				),
			)
		);

		$slugs = array_map( static fn( array $item ): string => $item['slug'], $extracted['palette'] );

		self::assertNotContains( 'vivid-cyan-blue', $slugs );
		self::assertNotContains( 'laao-transparent', $slugs );
		self::assertContains( 'laao-red', $slugs );
		self::assertNotNull( $extracted['text'] );
		self::assertNotNull( $extracted['background'] );
		self::assertNotNull( $extracted['link'] );
		self::assertNotSame( '#0693e3', $extracted['link'] );

		$kit = Theme_Brand_Mapper::from_theme( $extracted );

		self::assertSame( $extracted['text'], $kit->color( Brand_Kit::SLOT_TEXT ) );
		self::assertSame( $extracted['background'], $kit->color( Brand_Kit::SLOT_BACKGROUND ) );
		self::assertSame( $extracted['link'], $kit->color( Brand_Kit::SLOT_BRAND ) );
		self::assertNotSame( '#0693e3', $kit->color( Brand_Kit::SLOT_BRAND ) );
		self::assertNotSame( Design_Presets::color( Brand_Kit::SLOT_BRAND ), $kit->color( Brand_Kit::SLOT_BRAND ) );
	}
}
