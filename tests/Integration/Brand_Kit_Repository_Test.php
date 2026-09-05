<?php
/**
 * Brand kit persistence tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Repository\Theme_Style_Reader;
use CampaignBridge\Tests\Helpers\Test_Case;

final class Brand_Kit_Repository_Test extends Test_Case {
	public function tearDown(): void {
		( new Brand_Kit_Repository() )->clear();
		parent::tearDown();
	}

	public function test_returns_defaults_when_nothing_is_stored(): void {
		$kit = ( new Brand_Kit_Repository() )->get();

		$this->assertSame( Brand_Kit::defaults()->to_array(), $kit->to_array() );
	}

	public function test_round_trips_a_custom_kit(): void {
		$repository = new Brand_Kit_Repository();
		$saved      = Brand_Kit::from_colors(
			array( Brand_Kit::SLOT_BRAND => '#ff5500' ),
			Brand_Kit::SOURCE_CUSTOM
		);

		$this->assertTrue( $repository->save( $saved ) );
		$this->assertSame( $saved->to_array(), $repository->get()->to_array() );
	}

	public function test_falls_back_to_defaults_when_stored_data_is_corrupt(): void {
		update_option( 'campaignbridge_brand_kit', array( 'colors' => array( 'brand' => 'not-a-colour' ) ) );

		$this->assertSame(
			Brand_Kit::defaults()->to_array(),
			( new Brand_Kit_Repository() )->get()->to_array()
		);
	}

	public function test_delete_restores_the_defaults(): void {
		$repository = new Brand_Kit_Repository();
		$repository->save( Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) ) );

		$this->assertTrue( $repository->clear() );
		$this->assertSame( Brand_Kit::SOURCE_DEFAULTS, $repository->get()->source() );
	}

	public function test_brand_settings_tab_renders_import_and_slots(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		ob_start();
		include dirname( __DIR__, 2 ) . '/includes/Admin/Screens/settings/brand.php';
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'import_brand_kit', $html );
		$this->assertStringContainsString( 'restore_brand_kit', $html );
		$this->assertStringContainsString( 'campaignbridge-brand-kit-root', $html );
		$this->assertStringContainsString( 'campaignbridge-brand-kit__dataviews', $html );
	}

	public function test_theme_reader_returns_a_portable_slice(): void {
		$extracted = ( new Theme_Style_Reader() )->extract();

		$this->assertArrayHasKey( 'palette', $extracted );
		$this->assertArrayHasKey( 'text', $extracted );
		$this->assertArrayHasKey( 'background', $extracted );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $extracted['fingerprint'] );

		foreach ( $extracted['palette'] as $item ) {
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $item['color'] );
		}
	}

	public function test_saving_an_unchanged_kit_reports_success(): void {
		$repository = new Brand_Kit_Repository();
		$kit        = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#123456' ) );

		self::assertTrue( $repository->save( $kit ), 'first write' );

		// update_option() returns false when the stored value is identical.
		// That is a successful no-op, not a failed write, and callers surface
		// a false return to the operator as a server error.
		self::assertTrue( $repository->save( $kit ), 'identical rewrite' );
		self::assertSame( '#123456', $repository->get()->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_clearing_a_kit_that_was_never_stored_reports_success(): void {
		$repository = new Brand_Kit_Repository();

		self::assertTrue( $repository->clear(), 'clearing an absent kit' );
		self::assertSame( Brand_Kit::SOURCE_DEFAULTS, $repository->get()->source() );
	}

	public function test_clearing_twice_reports_success_both_times(): void {
		$repository = new Brand_Kit_Repository();
		$repository->save( Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#abcdef' ) ) );

		self::assertTrue( $repository->clear() );
		self::assertTrue( $repository->clear() );
	}
}
