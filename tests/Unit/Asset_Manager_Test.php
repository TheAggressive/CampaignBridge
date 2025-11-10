<?php
/**
 * Unit tests for Asset_Manager class.
 *
 * @package CampaignBridge\Tests\Unit
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Admin\Asset_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test Asset_Manager functionality.
 */
class _Asset_Manager_Test extends Test_Case {

	/**
	 * Test load_asset_data_static with non-existent file.
	 */
	public function test_load_asset_data_static_nonexistent(): void {
		$asset_data = Asset_Manager::load_asset_data_static( 'nonexistent.asset.php' );
		$this->assertNull( $asset_data );
	}

	/**
	 * Test load_asset_data_static with non-.asset.php file.
	 */
	public function test_load_asset_data_static_wrong_extension(): void {
		$asset_data = Asset_Manager::load_asset_data_static( 'wrong.txt' );
		$this->assertNull( $asset_data );
	}

	/**
	 * Test enqueue_assets with non-existent directory.
	 */
	public function test_enqueue_assets_with_nonexistent_directory(): void {
		// Should not throw an error, just return silently.
		Asset_Manager::enqueue_assets( 'nonexistent/directory' );
		$this->assertTrue( true ); // If we get here, the test passes.
	}

	/**
	 * Test enqueue_from_config with empty configuration.
	 */
	public function test_enqueue_from_config_empty(): void {
		Asset_Manager::enqueue_from_config( array() );
		$this->assertTrue( true ); // Should not throw any errors.
	}
}
