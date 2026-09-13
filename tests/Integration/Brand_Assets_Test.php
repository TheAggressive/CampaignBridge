<?php
/**
 * Official brand asset wiring.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Admin_Menu_Manager;
use CampaignBridge\Admin\Brand_Assets;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Proves the admin uses the shipped brand files.
 */
final class Brand_Assets_Test extends Test_Case {
	public function test_the_brand_files_used_by_the_admin_are_present(): void {
		foreach ( array( Brand_Assets::MENU_ICON, Brand_Assets::HEADER_ICON ) as $file ) {
			self::assertFileIsReadable( Brand_Assets::path( $file ) );
		}
		self::assertStringEndsWith( '/assets/brand/' . Brand_Assets::HEADER_ICON, Brand_Assets::url( Brand_Assets::HEADER_ICON ) );
	}

	public function test_the_menu_icon_is_the_monochrome_brand_svg_as_a_data_uri(): void {
		$prefix = 'data:image/svg+xml;base64,';
		$icon   = Brand_Assets::menu_icon();

		self::assertStringStartsWith( $prefix, $icon );

		$svg = base64_decode( substr( $icon, strlen( $prefix ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the menu icon under test.
		self::assertSame( file_get_contents( Brand_Assets::path( Brand_Assets::MENU_ICON ) ), $svg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the bundled fixture.
		// WordPress can only recolor an icon that sets no colors of its own.
		self::assertStringNotContainsString( 'fill', (string) $svg );
		self::assertStringNotContainsString( 'stroke', (string) $svg );
	}

	public function test_the_admin_menu_registers_the_brand_icon(): void {
		global $menu;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolates the admin menu under test.

		( new Admin_Menu_Manager() )->add_parent_menu();

		$entries = array_values(
			array_filter(
				$menu,
				static fn ( array $item ): bool => 'campaignbridge' === ( $item[2] ?? '' )
			)
		);
		self::assertCount( 1, $entries );
		self::assertSame( Brand_Assets::menu_icon(), $entries[0][6] );
	}
}
