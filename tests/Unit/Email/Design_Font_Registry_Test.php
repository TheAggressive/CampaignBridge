<?php
/**
 * Per-template design font registry tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Font_Registry;
use PHPUnit\Framework\TestCase;

/** Proves template font assets remain bounded, portable, and deterministic. */
final class Design_Font_Registry_Test extends TestCase {
	/** Valid fonts and semantic overrides survive a canonical JSON round trip. */
	public function test_round_trips_valid_fonts_and_slots(): void {
		$font     = $this->font( 'Example Sans' );
		$registry = Design_Font_Registry::from_array(
			array(
				'version' => 1,
				'fonts'   => array( $font ),
				'slots'   => array(
					'heading' => $font['slug'],
					'body'    => 'arial',
					'unknown' => 'inter',
				),
			)
		);

		self::assertSame( array( $font ), $registry->fonts() );
		self::assertSame(
			array(
				'heading' => $font['slug'],
				'body'    => 'arial',
			),
			$registry->slots()
		);
		self::assertSame( $registry->to_array(), Design_Font_Registry::from_json( $registry->to_json() )->to_array() );
	}

	/** Malformed provider records fail closed without discarding valid siblings. */
	public function test_drops_malformed_and_duplicate_records(): void {
		$valid            = $this->font( 'Example Sans' );
		$malicious        = $valid;
		$malicious['url'] = 'https://attacker.example/font.css';

		$registry = Design_Font_Registry::from_array(
			array(
				'fonts' => array( $malicious, $valid, $valid ),
				'slots' => array( 'heading' => 'bad slug' ),
			)
		);

		self::assertSame( array( $valid ), $registry->fonts() );
		self::assertSame( array(), $registry->slots() );
		self::assertSame( Design_Font_Registry::empty()->to_array(), Design_Font_Registry::from_json( '{bad json' )->to_array() );
	}

	/** The registry is bounded independently from the smaller Brand Kit catalog. */
	public function test_limits_template_fonts_to_the_design_bound(): void {
		$fonts = array();
		for ( $index = 0; $index < Design_Font_Registry::MAX_FONTS + 3; ++$index ) {
			$fonts[] = $this->font( 'Example Font ' . $index );
		}

		$registry = Design_Font_Registry::from_array( array( 'fonts' => $fonts ) );

		self::assertCount( Design_Font_Registry::MAX_FONTS, $registry->fonts() );
		self::assertGreaterThan( Brand_Kit::MAX_CUSTOM_FONTS, count( $registry->fonts() ) );
	}

	/**
	 * Build one valid Google Font snapshot.
	 *
	 * @param string $name Font family name.
	 * @return array{slug: string, name: string, family: string, weights: array<int, int>, url: string}
	 */
	private function font( string $name ): array {
		$encoded = str_replace( '%20', '+', rawurlencode( $name ) );

		return array(
			'slug'    => Brand_Kit::custom_font_slug( $name ),
			'name'    => $name,
			'family'  => $name . ',Arial,Helvetica,sans-serif',
			'weights' => array( 400, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=' . $encoded . ':wght@400;700&display=swap',
		);
	}
}
