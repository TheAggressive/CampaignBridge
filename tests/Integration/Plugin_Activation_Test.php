<?php
/**
 * Plugin activation runtime guard.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the WordPress version the guard reads; tearDown restores it.

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge_Plugin;
use WPDieException;

/**
 * Proves activation accepts the declared minimum WordPress release exactly as
 * WordPress reports it, and refuses older or pre-release versions.
 */
final class Plugin_Activation_Test extends Test_Case {
	private string $original_wp_version = '';

	/**
	 * Remember the real WordPress version.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_wp_version = (string) $GLOBALS['wp_version'];
	}

	/**
	 * Restore the real WordPress version.
	 */
	public function tearDown(): void {
		$GLOBALS['wp_version'] = $this->original_wp_version;
		parent::tearDown();
	}

	public function test_activation_succeeds_on_the_minimum_release_version_string(): void {
		// WordPress reports "7.1" for the 7.1 release, never "7.1.0". Using the
		// literal release string catches a minimum written as "7.1.0".
		$GLOBALS['wp_version'] = '7.1';

		CampaignBridge_Plugin::activate();

		self::assertTrue( get_role( 'administrator' )->has_cap( 'campaignbridge_edit_templates' ) );
	}

	public function test_activation_succeeds_on_a_later_maintenance_release(): void {
		$GLOBALS['wp_version'] = '7.1.1';

		CampaignBridge_Plugin::activate();

		self::assertTrue( get_role( 'administrator' )->has_cap( 'campaignbridge_edit_templates' ) );
	}

	/**
	 * @dataProvider unsupported_versions
	 *
	 * @param string $version Reported WordPress version.
	 */
	public function test_activation_refuses_versions_below_the_minimum( string $version ): void {
		$GLOBALS['wp_version'] = $version;

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'CampaignBridge requires WordPress ' . CampaignBridge_Plugin::MIN_WP_VERSION );

		CampaignBridge_Plugin::activate();
	}

	/**
	 * Versions older than the declared minimum.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unsupported_versions(): array {
		return array(
			'previous release'    => array( '7.0.4' ),
			'minimum pre-release' => array( '7.1-RC1' ),
		);
	}
}
