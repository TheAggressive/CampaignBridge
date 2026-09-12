<?php
/**
 * Production settings forms, including their submission guards.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Tests\Helpers\Test_Case;

class Admin_Form_Screens_Test extends Test_Case {
	private array $original_post;
	private array $original_server;

	public function setUp(): void {
		parent::setUp();
		$this->original_post = $_POST;
		$this->original_server = $_SERVER;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'admin' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=campaignbridge-settings';
	}

	public function tearDown(): void {
		$_POST = $this->original_post;
		$_SERVER = $this->original_server;
		set_current_screen( 'front' );
		parent::tearDown();
	}

	public function test_general_screen_saves_and_renders_sender_settings(): void {
		$this->submit_general();
		$html = $this->render_screen( 'general' );
		$this->assertSame( 'Sender name', get_option( 'campaignbridge_from_name' ) );
		$this->assertSame( 'sender@example.com', get_option( 'campaignbridge_from_email' ) );
		$this->assertStringContainsString( 'sender@example.com', $html );
		$this->assertStringContainsString( 'general_settings_wpnonce', $html );
	}

	public function test_general_screen_rejects_invalid_email(): void {
		update_option( 'campaignbridge_from_email', 'original@example.com' );
		$this->submit_general();
		$_POST['general_settings']['from_email'] = 'invalid-email';
		$this->render_screen( 'general' );
		$this->assertSame( 'original@example.com', get_option( 'campaignbridge_from_email' ) );
	}

	public function test_general_screen_requires_nonce_and_capability(): void {
		update_option( 'campaignbridge_from_name', 'Original' );
		$this->submit_general();
		$_POST['general_settings_wpnonce'] = 'invalid';
		$this->render_screen( 'general' );
		$this->assertSame( 'Original', get_option( 'campaignbridge_from_name' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->submit_general();
		$this->render_screen( 'general' );
		$this->assertSame( 'Original', get_option( 'campaignbridge_from_name' ) );
	}

	public function test_provider_screen_encrypts_credentials(): void {
		$key = 'mailchimp-test-fixture-us20';
		$_POST = array(
			'providers' => array( 'form_id' => 'providers', 'provider' => 'mailchimp', 'mailchimp_api_key' => $key, 'mailchimp_audience' => 'audience' ),
			'providers_wpnonce' => wp_create_nonce( 'campaignbridge_form_providers' ),
		);
		$html = $this->render_screen( 'providers' );
		$repo     = new \CampaignBridge\Repository\Provider_Connection_Repository();
		$conn     = $repo->get( 'mailchimp' );
		$this->assertNotNull( $conn );
		$stored = $conn->api_key();
		$this->assertTrue( Encryption::is_encrypted_value( $stored ) );
		$this->assertSame( $key, Encryption::decrypt_for_context( $stored, 'api_key' ) );
		$this->assertStringNotContainsString( $key, $html );
		$this->assertSame( 'mailchimp', get_option( 'campaignbridge_provider' ) );
	}

	private function submit_general(): void {
		$_POST = array(
			'general_settings' => array( 'form_id' => 'general_settings', 'from_name' => 'Sender name', 'from_email' => 'sender@example.com', 'reply_to' => 'reply@example.com' ),
			'general_settings_wpnonce' => wp_create_nonce( 'campaignbridge_form_general_settings' ),
		);
	}

	private function render_screen( string $name ): string {
		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/includes/Admin/Screens/settings/' . $name . '.php';
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}
}
