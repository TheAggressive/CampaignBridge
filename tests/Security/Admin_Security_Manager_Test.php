<?php
/**
 * Admin security header tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Admin\Admin_Security_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;

final class Admin_Security_Manager_Test extends Test_Case {
	public function test_headers_are_sent_from_a_hook_that_runs_before_output(): void {
		$manager = new Admin_Security_Manager();
		$manager->init();

		// current_screen fires inside set_current_screen(), before
		// wp-admin/admin-header.php is required. admin_head and
		// admin_enqueue_scripts both run after _wp_admin_html_begin() has
		// started output, where header() no longer has any effect.
		$this->assertNotFalse( has_action( 'current_screen', array( $manager, 'add_security_headers' ) ) );
		$this->assertFalse( has_action( 'admin_head', array( $manager, 'add_security_headers' ) ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', array( $manager, 'add_security_headers' ) ) );
	}

	public function test_a_covered_screen_receives_every_security_header(): void {
		$headers = Admin_Security_Manager::headers_for( 'toplevel_page_campaignbridge' );

		$names = array_map(
			static fn( string $header ): string => strtok( $header, ':' ),
			$headers
		);

		$this->assertSame(
			array(
				'Content-Security-Policy',
				'X-Frame-Options',
				'X-XSS-Protection',
				'X-Content-Type-Options',
				'Referrer-Policy',
			),
			$names
		);
	}

	public function test_the_policy_constrains_scripts_and_form_targets(): void {
		$headers = Admin_Security_Manager::headers_for( 'campaignbridge_page_campaignbridge-settings' );
		$policy  = $headers[0];

		$this->assertStringContainsString( "default-src 'self'", $policy );
		$this->assertStringContainsString( "form-action 'self'", $policy );
		$this->assertStringContainsString( "connect-src 'self'", $policy );
		$this->assertStringNotContainsString( 'unsafe-eval', $policy );
	}

	public function test_an_unrelated_screen_receives_nothing(): void {
		$this->assertSame( array(), Admin_Security_Manager::headers_for( 'dashboard' ) );
		$this->assertSame( array(), Admin_Security_Manager::headers_for( '' ) );
		$this->assertSame( array(), Admin_Security_Manager::headers_for( 'edit-post' ) );
	}

	public function test_both_campaignbridge_admin_screens_are_covered(): void {
		foreach ( array( 'toplevel_page_campaignbridge', 'campaignbridge_page_campaignbridge-settings' ) as $screen ) {
			$this->assertNotSame( array(), Admin_Security_Manager::headers_for( $screen ), $screen );
		}
	}
}
