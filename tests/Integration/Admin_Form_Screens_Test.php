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
		$this->original_post   = $_POST;
		$this->original_server = $_SERVER;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'admin' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-admin/admin.php?page=campaignbridge-settings';
	}

	public function tearDown(): void {
		$_POST   = $this->original_post;
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
		$key   = str_repeat( 'a', 32 ) . '-us1';
		$_POST = array(
			'providers'         => array(
				'form_id'            => 'providers',
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => $key,
				'mailchimp_audience' => 'audience',
			),
			'providers_wpnonce' => wp_create_nonce( 'campaignbridge_form_providers' ),
		);
		$html  = $this->render_screen( 'providers' );
		$repo  = new \CampaignBridge\Repository\Provider_Connection_Repository();
		$conn  = $repo->get( 'mailchimp' );
		$this->assertNotNull( $conn );
		$stored = $conn->api_key();
		$this->assertTrue( Encryption::is_encrypted_value( $stored ) );
		$this->assertSame( $key, Encryption::decrypt_for_context( $stored, 'api_key' ) );
		$this->assertStringNotContainsString( $key, $html );
		$this->assertSame( 'mailchimp', get_option( 'campaignbridge_provider' ) );
	}

	public function test_provider_screen_shows_a_saved_key_masked_after_reload(): void {
		$key = str_repeat( 'b', 28 ) . 'c0de-us1';
		( new \CampaignBridge\Repository\Provider_Connection_Repository() )->save( \CampaignBridge\Domain\Campaign\Provider_Connection::create( 'mailchimp', Encryption::encrypt( $key ), '' ) );
		update_option( 'campaignbridge_provider', 'mailchimp' );
		$_POST = array();

		$html = $this->render_screen( 'providers' );

		$this->assertStringContainsString( 'campaignbridge-encrypted-field', $html );
		$this->assertMatchesRegularExpression( '/value="•+-us1"/u', $html, 'The saved key is shown masked, not as an empty field.' );
		$this->assertStringNotContainsString( $key, $html );
	}

	public function test_a_connection_manager_can_disconnect_mailchimp(): void {
		$this->store_mailchimp_key();

		$html = $this->render_screen( 'providers' );
		$this->assertStringContainsString( 'name="disconnect_provider" value="mailchimp"', $html );
		$this->assertStringContainsString( 'data-confirm=', $html );

		$this->assertTrue( \CampaignBridge\Admin\Controllers\Provider_Disconnect_Handler::handle( 'mailchimp' ) );
		$this->assertNull( ( new \CampaignBridge\Repository\Provider_Connection_Repository() )->get( 'mailchimp' ) );
		$this->assertSame( 'html', get_option( 'campaignbridge_provider' ) );
		$this->assertStringNotContainsString( 'name="disconnect_provider"', $this->render_screen( 'providers' ), 'Nothing is left to disconnect.' );
	}

	public function test_disconnecting_requires_the_connection_capability(): void {
		$this->store_mailchimp_key();
		$manager = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_userdata( $manager )->add_cap( \CampaignBridge\Core\Capabilities::MANAGE );
		wp_set_current_user( $manager );

		$this->assertStringNotContainsString( 'name="disconnect_provider"', $this->render_screen( 'providers' ) );
		$this->assertFalse( \CampaignBridge\Admin\Controllers\Provider_Disconnect_Handler::handle( 'mailchimp' ) );
		$this->assertFalse( \CampaignBridge\Admin\Controllers\Provider_Disconnect_Handler::handle( 'unknown' ) );
		$this->assertNotNull( ( new \CampaignBridge\Repository\Provider_Connection_Repository() )->get( 'mailchimp' ) );
	}

	public function test_a_disconnect_request_without_a_valid_nonce_is_refused(): void {
		$this->store_mailchimp_key();
		$_POST = array(
			'disconnect_provider' => 'mailchimp',
			'_wpnonce'            => wp_create_nonce( 'campaignbridge_reset_all' ),
		);
		// The constructor verifies the stored key with Mailchimp; only request handling is under test.
		$controller = ( new \ReflectionClass( \CampaignBridge\Admin\Controllers\Settings_Controller::class ) )->newInstanceWithoutConstructor();

		try {
			$controller->handle_request();
			$this->fail( 'A request without a valid nonce must be refused.' );
		} catch ( \WPDieException ) {
			$this->assertNotNull( ( new \CampaignBridge\Repository\Provider_Connection_Repository() )->get( 'mailchimp' ) );
		}
	}

	private function store_mailchimp_key(): void {
		( new \CampaignBridge\Repository\Provider_Connection_Repository() )->save( \CampaignBridge\Domain\Campaign\Provider_Connection::create( 'mailchimp', Encryption::encrypt( str_repeat( 'd', 32 ) . '-us1' ), '' ) );
		update_option( 'campaignbridge_provider', 'mailchimp' );
		$_POST = array();
	}

	public function test_policies_screen_saves_and_shows_the_effective_policy(): void {
		$this->submit_policies( '1', "Example.com\nnot a domain" );
		$html = $this->render_screen( 'policies' );

		$this->assertSame( 1, (int) get_option( 'campaignbridge_policy_separate_delivery' ) );
		$this->assertSame( array( 'example.com' ), ( new \CampaignBridge\Repository\Delivery_Policy_Repository() )->current()->test_domains() );
		$this->assertTrue( ( new \CampaignBridge\Repository\Delivery_Policy_Repository() )->current()->requires_separate_delivery() );
		$this->assertStringContainsString( 'tests may be sent only to example.com', $html );
		$this->assertStringContainsString( 'delivery_policies_wpnonce', $html );
	}

	public function test_people_who_approve_or_send_cannot_relax_the_policies(): void {
		update_option( 'campaignbridge_policy_separate_delivery', 1 );
		update_option( 'campaignbridge_policy_test_recipient_domains', 'example.com' );

		$sender = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_userdata( $sender )->add_cap( \CampaignBridge\Core\Capabilities::CREATE_CAMPAIGNS );
		get_userdata( $sender )->add_cap( \CampaignBridge\Core\Capabilities::SEND_CAMPAIGNS );
		wp_set_current_user( $sender );
		$this->submit_policies( '0', '' );
		$this->render_screen( 'policies' );
		$this->assertSame( 1, (int) get_option( 'campaignbridge_policy_separate_delivery' ) );
		$this->assertSame( 'example.com', get_option( 'campaignbridge_policy_test_recipient_domains' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->submit_policies( '0', '' );
		$_POST['delivery_policies_wpnonce'] = 'invalid';
		$this->render_screen( 'policies' );
		$this->assertSame( 1, (int) get_option( 'campaignbridge_policy_separate_delivery' ), 'A forged request cannot relax the policy.' );
	}

	private function submit_policies( string $separate, string $domains ): void {
		$_POST = array(
			'delivery_policies'         => array(
				'form_id'                       => 'delivery_policies',
				'policy_separate_delivery'      => $separate,
				'policy_test_recipient_domains' => $domains,
			),
			'delivery_policies_wpnonce' => wp_create_nonce( 'campaignbridge_form_delivery_policies' ),
		);
	}

	private function submit_general(): void {
		$_POST = array(
			'general_settings'         => array(
				'form_id'    => 'general_settings',
				'from_name'  => 'Sender name',
				'from_email' => 'sender@example.com',
				'reply_to'   => 'reply@example.com',
			),
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
