<?php
/**
 * Brand kit settings authorization tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Admin\Controllers\Settings_Controller;
use CampaignBridge\Tests\Helpers\Test_Case;
use WPDieException;

final class Brand_Kit_Settings_Test extends Test_Case {
	public function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	public function test_import_rejects_a_missing_nonce(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$_POST['import_brand_kit'] = '1';

		$this->expectException( WPDieException::class );
		( new Settings_Controller() )->handle_request();
	}

	public function test_import_rejects_a_user_without_manage_options(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'subscriber' ) ) );
		$_POST['import_brand_kit'] = '1';
		$_POST['_wpnonce']         = wp_create_nonce( 'campaignbridge_import_brand' );

		$this->expectException( WPDieException::class );
		( new Settings_Controller() )->handle_request();
	}

	public function test_restore_rejects_a_missing_nonce(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		$_POST['restore_brand_kit'] = '1';

		$this->expectException( WPDieException::class );
		( new Settings_Controller() )->handle_request();
	}
}
