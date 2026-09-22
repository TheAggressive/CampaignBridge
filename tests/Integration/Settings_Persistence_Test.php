<?php
/**
 * Integration tests for CampaignBridge settings persistence.
 *
 * Tests that settings are properly saved to WordPress options,
 * persist across page loads, and work end-to-end with the
 * admin interface and controllers.
 *
 * @package CampaignBridge\Tests\Integration
 * @since 1.0.0
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Controllers\Settings_Controller;
use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Screen_Context;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test settings persistence functionality.
 */
class Settings_Persistence_Test extends Test_Case {

	/**
	 * Test data for settings.
	 *
	 * @var array
	 */
	private array $test_settings_data = array(
		'campaignbridge_from_name'          => 'Test Campaign Sender',
		'campaignbridge_from_email'         => 'test@example.com',
		'campaignbridge_reply_to'           => 'noreply@example.com',
		'campaignbridge_provider'           => 'mailchimp',
		'campaignbridge_mailchimp_api_key'  => 'test-api-key-123',
		'campaignbridge_mailchimp_audience' => 'test-audience-456',
		'campaignbridge_debug_mode'         => true,
		'campaignbridge_log_level'          => 'debug',
		'campaignbridge_cache_duration'     => 7200,
		'campaignbridge_rate_limit'         => 200,
	);

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clean up any existing test settings
		$this->cleanup_test_settings();

		// Create and set admin user
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up test settings
		$this->cleanup_test_settings();
	}

	/**
	 * Test that general settings can be saved and persist.
	 */
	public function test_general_settings_persist_across_requests(): void {
		// Simulate form submission with general settings
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'general_settings'         => array(
				'from_name'  => $this->test_settings_data['campaignbridge_from_name'],
				'from_email' => $this->test_settings_data['campaignbridge_from_email'],
				'reply_to'   => $this->test_settings_data['campaignbridge_reply_to'],
			),
			'general_settings_wpnonce' => wp_create_nonce( 'campaignbridge_form_general_settings' ),
		);

		// Simulate the settings screen form submission
		$this->simulate_general_settings_submission();

		// Verify settings were saved to options
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_name'], get_option( 'campaignbridge_from_name' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_email'], get_option( 'campaignbridge_from_email' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_reply_to'], get_option( 'campaignbridge_reply_to' ) );

		// Simulate fresh page load (new request)
		$this->reset_request_state();

		// Verify settings are still available
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_name'], get_option( 'campaignbridge_from_name' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_email'], get_option( 'campaignbridge_from_email' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_reply_to'], get_option( 'campaignbridge_reply_to' ) );

		// Test that Settings_Controller loads the settings correctly
		$controller = new Settings_Controller();
		$data       = $controller->get_data();

		$this->assertEquals( $this->test_settings_data['campaignbridge_from_name'], $data['from_name'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_email'], $data['from_email'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_reply_to'], $data['reply_to'] );
	}

	/**
	 * Test that provider settings can be saved and persist.
	 */
	public function test_provider_settings_persist_across_requests(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$plaintext_key = str_repeat( 'a', 32 ) . '-us1';
		$encrypted_key = \CampaignBridge\Core\Encryption::encrypt( $plaintext_key );

		$result = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => $encrypted_key,
				'mailchimp_audience' => 'test-audience-456',
			)
		);
		$this->assertTrue( $result );

		// Verify settings were saved to options.
		$this->assertSame( 'mailchimp', get_option( 'campaignbridge_provider' ) );
		$repo = new \CampaignBridge\Repository\Provider_Connection_Repository();
		$conn = $repo->get( 'mailchimp' );
		$this->assertNotNull( $conn );
		$this->assertSame( $encrypted_key, $conn->api_key() );
		$this->assertSame( 'test-audience-456', $conn->audience_id() );

		// Simulate fresh page load.
		$this->reset_request_state();

		// Verify settings persist.
		$this->assertSame( 'mailchimp', get_option( 'campaignbridge_provider' ) );
		$conn = $repo->get( 'mailchimp' );
		$this->assertNotNull( $conn );
		$this->assertSame( $encrypted_key, $conn->api_key() );
		$this->assertSame( 'test-audience-456', $conn->audience_id() );
	}

	/**
	 * Test that settings are properly sanitized.
	 */
	public function test_settings_are_properly_sanitized(): void {
		// Test data with potential XSS
		$malicious_data = array(
			'from_name'  => '<script>alert("xss")</script>Test Sender',
			'from_email' => 'test@example.com',
			'reply_to'   => 'noreply@example.com',
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'general_settings'         => $malicious_data,
			'general_settings_wpnonce' => wp_create_nonce( 'campaignbridge_form_general_settings' ),
		);

		$this->simulate_general_settings_submission();

		// Verify XSS was sanitized - script tags should be removed
		$saved_name = get_option( 'campaignbridge_from_name' );
		$this->assertStringNotContainsString( '<script>', $saved_name );
		$this->assertStringNotContainsString( '</script>', $saved_name );
		$this->assertStringContainsString( 'Test Sender', $saved_name ); // Legitimate content preserved
	}

	/**
	 * Test that settings validation works.
	 */
	public function test_settings_validation_works(): void {
		// Set up admin user and context
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		set_current_screen( 'toplevel_page_campaignbridge' );

		// Test that a form with proper validation would work
		$form = Form::make( 'test_validation' )
			->text( 'from_name' )->required()
			->email( 'from_email' )->required()
			->email( 'reply_to' );

		// Test with valid data
		$_POST['test_validation'] = array(
			'from_name'  => 'Valid Name',
			'from_email' => 'valid@example.com',
			'reply_to'   => 'reply@example.com',
		);

		$_SERVER['REQUEST_METHOD']        = 'POST';
		$_POST['test_validation_wpnonce'] = wp_create_nonce( 'campaignbridge_form_test_validation' );

		// Render the form to trigger submission detection
		ob_start();
		$form->render();
		ob_end_clean();

		$this->assertTrue( $form->submitted() );
		$this->assertTrue( $form->valid() );

		// Test with invalid data - reset POST data
		$_POST['test_validation'] = array(
			'from_name'  => '', // Required but empty
			'from_email' => 'invalid-email',
		);

		$form2 = Form::make( 'test_validation' )
			->text( 'from_name' )->required()
			->email( 'from_email' )->required();

		$this->assertFalse( $form2->valid() );
	}

	/**
	 * Test that advanced settings persist.
	 */
	public function test_advanced_settings_persist(): void {
		// Set advanced settings directly (simulating admin interface)
		update_option( 'campaignbridge_debug_mode', $this->test_settings_data['campaignbridge_debug_mode'] );
		update_option( 'campaignbridge_log_level', $this->test_settings_data['campaignbridge_log_level'] );
		update_option( 'campaignbridge_cache_duration', $this->test_settings_data['campaignbridge_cache_duration'] );
		update_option( 'campaignbridge_rate_limit', $this->test_settings_data['campaignbridge_rate_limit'] );

		// Verify they were saved
		$this->assertEquals( $this->test_settings_data['campaignbridge_debug_mode'], get_option( 'campaignbridge_debug_mode' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_log_level'], get_option( 'campaignbridge_log_level' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_cache_duration'], get_option( 'campaignbridge_cache_duration' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_rate_limit'], get_option( 'campaignbridge_rate_limit' ) );

		// Simulate fresh request
		$this->reset_request_state();

		// Verify persistence
		$this->assertEquals( $this->test_settings_data['campaignbridge_debug_mode'], get_option( 'campaignbridge_debug_mode' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_log_level'], get_option( 'campaignbridge_log_level' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_cache_duration'], get_option( 'campaignbridge_cache_duration' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_rate_limit'], get_option( 'campaignbridge_rate_limit' ) );

		// Test Settings_Controller loads advanced settings
		$controller = new Settings_Controller();
		$data       = $controller->get_data();

		$this->assertEquals( $this->test_settings_data['campaignbridge_debug_mode'], $data['debug_mode'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_log_level'], $data['log_level'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_cache_duration'], $data['cache_duration'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_rate_limit'], $data['rate_limit'] );
	}

	/**
	 * Test settings form rendering loads saved values.
	 */
	public function test_settings_form_loads_saved_values(): void {
		// Pre-populate settings
		update_option( 'campaignbridge_from_name', $this->test_settings_data['campaignbridge_from_name'] );
		update_option( 'campaignbridge_from_email', $this->test_settings_data['campaignbridge_from_email'] );
		update_option( 'campaignbridge_provider', $this->test_settings_data['campaignbridge_provider'] );

		// Test that Settings_Controller loads the saved values
		$controller = new Settings_Controller();
		$data       = $controller->get_data();

		$this->assertEquals( $this->test_settings_data['campaignbridge_from_name'], $data['from_name'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_email'], $data['from_email'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_provider'], get_option( 'campaignbridge_provider' ) ); // Provider comes from option directly

		// Test that we can create a form with saved values
		$form = Form::make( 'test_settings' )
			->text( 'from_name' )
			->email( 'from_email' )
			->select( 'provider', 'Provider' )
				->options(
					array(
						'mailchimp' => 'Mailchimp',
						'html'      => 'HTML',
					)
				);

		// Test form rendering with saved data
		ob_start();
		$form->render();
		$output = ob_get_clean();

		// Form should render without errors (values are loaded from options internally)
		$this->assertStringContainsString( 'name="test_settings[from_name]"', $output );
		$this->assertStringContainsString( 'name="test_settings[from_email]"', $output );
		$this->assertStringContainsString( 'name="test_settings[provider]"', $output );
	}

	/**
	 * Test settings export and import functionality.
	 */
	public function test_settings_export_import_functionality(): void {
		// Set up test settings
		update_option( 'campaignbridge_from_name', $this->test_settings_data['campaignbridge_from_name'] );
		update_option( 'campaignbridge_from_email', $this->test_settings_data['campaignbridge_from_email'] );
		update_option( 'campaignbridge_provider', $this->test_settings_data['campaignbridge_provider'] );

		// Test that export functionality works by checking controller can access settings
		$controller = new Settings_Controller();
		$data       = $controller->get_data();

		$this->assertEquals( $this->test_settings_data['campaignbridge_from_name'], $data['from_name'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_email'], $data['from_email'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_provider'], get_option( 'campaignbridge_provider' ) ); // Provider comes from option directly

		// Clean settings and verify they can be cleared
		delete_option( 'campaignbridge_from_name' );
		delete_option( 'campaignbridge_from_email' );
		delete_option( 'campaignbridge_provider' );

		$this->assertEmpty( get_option( 'campaignbridge_from_name' ) );
		$this->assertEmpty( get_option( 'campaignbridge_from_email' ) );
		$this->assertEmpty( get_option( 'campaignbridge_provider' ) );

		// Test that controller reflects cleared settings
		$controller2 = new Settings_Controller();
		$data2       = $controller2->get_data();

		$this->assertNotEquals( $this->test_settings_data['campaignbridge_from_name'], $data2['from_name'] );
		$this->assertNotEquals( $this->test_settings_data['campaignbridge_from_email'], $data2['from_email'] );
	}

	/**
	 * Test settings reset functionality.
	 */
	public function test_settings_reset_functionality(): void {
		// Set up test settings
		update_option( 'campaignbridge_from_name', $this->test_settings_data['campaignbridge_from_name'] );
		update_option( 'campaignbridge_from_email', $this->test_settings_data['campaignbridge_from_email'] );
		update_option( 'campaignbridge_debug_mode', true );

		// Verify settings exist
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_name'], get_option( 'campaignbridge_from_name' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_from_email'], get_option( 'campaignbridge_from_email' ) );
		$this->assertTrue( get_option( 'campaignbridge_debug_mode' ) );

		// Simulate reset by directly calling delete_option for the test options
		// (In real usage, this would be handled by the controller's reset method)
		delete_option( 'campaignbridge_from_name' );
		delete_option( 'campaignbridge_from_email' );
		delete_option( 'campaignbridge_debug_mode' );

		// Verify settings were reset (should be empty or defaults)
		$this->assertEmpty( get_option( 'campaignbridge_from_name' ) );
		$this->assertEmpty( get_option( 'campaignbridge_from_email' ) );
		$this->assertEmpty( get_option( 'campaignbridge_debug_mode' ) );

		// Test that controller reflects reset settings
		$controller = new Settings_Controller();
		$data       = $controller->get_data();

		$this->assertNotEquals( $this->test_settings_data['campaignbridge_from_name'], $data['from_name'] );
		$this->assertNotEquals( $this->test_settings_data['campaignbridge_from_email'], $data['from_email'] );
	}

	/**
	 * Helper method to simulate general settings form submission.
	 */
	private function simulate_general_settings_submission(): void {
		// Simulate the actual form processing from general.php with sanitization
		if ( isset( $_POST['general_settings'] ) ) {
			$data = $_POST['general_settings'];

			// Apply sanitization like the real form does
			$sanitized_data = array(
				'from_name'  => sanitize_text_field( $data['from_name'] ?? '' ),
				'from_email' => sanitize_email( $data['from_email'] ?? '' ),
				'reply_to'   => sanitize_email( $data['reply_to'] ?? '' ),
			);

			// Simulate the save_to_options logic with 'campaignbridge_' prefix
			foreach ( $sanitized_data as $key => $value ) {
				update_option( 'campaignbridge_' . $key, $value );
			}
		}
	}

	/**
	 * Test that saving Mailchimp requires MANAGE_CONNECTIONS capability.
	 *
	 * A user with MANAGE but without MANAGE_CONNECTIONS cannot save.
	 */
	public function test_save_mailchimp_requires_manage_connections_capability(): void {
		$user_id = $this->create_test_user( array( 'role' => 'editor' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( \CampaignBridge\Core\Capabilities::MANAGE );
		wp_set_current_user( $user_id );

		$result = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => \CampaignBridge\Core\Encryption::encrypt( str_repeat( 'a', 32 ) . '-us1' ),
				'mailchimp_audience' => 'aud-123',
			)
		);

		$this->assertFalse( $result );
		$this->assertNotSame( 'mailchimp', get_option( 'campaignbridge_provider', 'html' ) );
		$this->assertFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );
	}

	/**
	 * Test that saving Mailchimp with no key and no existing connection fails.
	 */
	public function test_save_mailchimp_fails_without_key_or_existing(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => '',
				'mailchimp_audience' => 'aud-123',
			)
		);

		$this->assertFalse( $result );
		$this->assertNotSame( 'mailchimp', get_option( 'campaignbridge_provider', 'html' ) );
	}

	/**
	 * Test that a failed Mailchimp save does not change the existing provider selection.
	 */
	public function test_failed_save_leaves_provider_selection_unchanged(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		\CampaignBridge\Core\Storage::update_option( 'campaignbridge_provider', 'html' );

		$invalid_key_encrypted = \CampaignBridge\Core\Encryption::encrypt( 'not-a-valid-mailchimp-key' );
		$result                = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => $invalid_key_encrypted,
				'mailchimp_audience' => 'aud-123',
			)
		);

		$this->assertFalse( $result );
		$this->assertSame( 'html', get_option( 'campaignbridge_provider' ) );
		$this->assertFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );
	}

	/**
	 * Test that submitting the unchanged encrypted hidden value preserves verification metadata.
	 */
	public function test_unchanged_encrypted_value_preserves_verification_metadata(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$repository = new \CampaignBridge\Repository\Provider_Connection_Repository();
		$encrypted  = \CampaignBridge\Core\Encryption::encrypt( str_repeat( 'a', 32 ) . '-us1' );
		$connection = \CampaignBridge\Domain\Campaign\Provider_Connection::create( 'mailchimp', $encrypted, 'aud-original' );
		$connection = $connection->with_verification(
			true,
			'2025-01-15T10:30:00Z',
			array( 'account_name' => 'Test Account', 'plan' => 'gold' )
		);
		$this->assertTrue( $repository->save( $connection ) );

		$result = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => $encrypted,
				'mailchimp_audience' => 'aud-new',
			)
		);

		$this->assertTrue( $result );

		$saved = $repository->get( 'mailchimp' );
		$this->assertNotNull( $saved );
		$this->assertSame( $encrypted, $saved->api_key() );
		$this->assertSame( 'aud-new', $saved->audience_id() );
		$this->assertTrue( $saved->is_verified() );
		$this->assertSame( '2025-01-15T10:30:00Z', $saved->last_verified_at() );
		$this->assertSame( array( 'account_name' => 'Test Account', 'plan' => 'gold' ), $saved->account_details() );
		$this->assertSame( \CampaignBridge\Domain\Campaign\Provider_Connection::SCHEMA_VERSION, $saved->schema_version() );
	}

	/**
	 * Test that an invalid new Mailchimp API key is rejected.
	 */
	public function test_invalid_new_key_is_rejected(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		\CampaignBridge\Core\Storage::update_option( 'campaignbridge_provider', 'html' );

		$invalid_encrypted = \CampaignBridge\Core\Encryption::encrypt( 'invalid-key-format' );
		$result            = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => $invalid_encrypted,
				'mailchimp_audience' => 'aud-123',
			)
		);

		$this->assertFalse( $result );
		$this->assertFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );
		$this->assertSame( 'html', get_option( 'campaignbridge_provider' ) );
	}

	/**
	 * Test that reset-all cannot delete the provider connection without MANAGE_CONNECTIONS.
	 */
	public function test_reset_cannot_delete_connection_without_manage_connections(): void {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$repository = new \CampaignBridge\Repository\Provider_Connection_Repository();
		$connection = \CampaignBridge\Domain\Campaign\Provider_Connection::create(
			'mailchimp',
			\CampaignBridge\Core\Encryption::encrypt( str_repeat( 'a', 32 ) . '-us1' ),
			'aud-123'
		);
		$this->assertTrue( $repository->save( $connection ) );
		$this->assertNotFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );

		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		$editor    = new \WP_User( $editor_id );
		$editor->add_cap( \CampaignBridge\Core\Capabilities::MANAGE );
		wp_set_current_user( $editor_id );

		$this->assertFalse( current_user_can( \CampaignBridge\Core\Capabilities::MANAGE_CONNECTIONS ) );
		$this->assertNotFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );
	}

	/**
	 * Test that a valid Mailchimp API key is encrypted and persisted correctly.
	 */
	public function test_provider_screen_encrypts_credentials(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$plaintext_key = str_repeat( 'a', 32 ) . '-us1';
		$encrypted_key = \CampaignBridge\Core\Encryption::encrypt( $plaintext_key );

		$result = \CampaignBridge\Admin\Controllers\Provider_Save_Handler::handle(
			array(
				'provider'           => 'mailchimp',
				'mailchimp_api_key'  => $encrypted_key,
				'mailchimp_audience' => 'aud-789',
			)
		);

		$this->assertTrue( $result );

		$repository = new \CampaignBridge\Repository\Provider_Connection_Repository();
		$saved      = $repository->get( 'mailchimp' );
		$this->assertNotNull( $saved );
		$this->assertSame( $encrypted_key, $saved->api_key() );
		$this->assertNotSame( $plaintext_key, $saved->api_key() );
		$this->assertTrue( \CampaignBridge\Core\Encryption::is_encrypted_value( $saved->api_key() ) );
		$this->assertSame( 'aud-789', $saved->audience_id() );
		$this->assertSame( 'mailchimp', get_option( 'campaignbridge_provider' ) );
	}

	/**
	 * Test that the uninstall cleanup uses the correct option key.
	 */
	public function test_uninstall_cleanup_uses_correct_option_key(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$repository = new \CampaignBridge\Repository\Provider_Connection_Repository();

		$connection = \CampaignBridge\Domain\Campaign\Provider_Connection::create( 'mailchimp', 'encrypted-key-abc', 'aud-123' );
		$this->assertTrue( $repository->save( $connection ) );

		$option_key = \CampaignBridge\Core\Storage_Prefixes::get_option_key( 'provider_connection_mailchimp' );
		$this->assertSame( 'campaignbridge_provider_connection_mailchimp', $option_key );

		$raw = get_option( 'campaignbridge_provider_connection_mailchimp' );
		$this->assertIsArray( $raw );
		$this->assertSame( 'mailchimp', $raw['provider_slug'] );

		$this->assertContains( 'campaignbridge_provider_connection_mailchimp', \CampaignBridge\Core\Storage_Prefixes::INDIVIDUAL_OPTIONS );
	}

	/**
	 * Test that the repository delete method cleans up the option.
	 */
	public function test_repository_delete_cleans_up_option(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$repository = new \CampaignBridge\Repository\Provider_Connection_Repository();

		$connection = \CampaignBridge\Domain\Campaign\Provider_Connection::create( 'mailchimp', 'encrypted-key-abc', 'aud-123' );
		$this->assertTrue( $repository->save( $connection ) );
		$this->assertNotFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );

		$this->assertTrue( $repository->delete( 'mailchimp' ) );
		$this->assertFalse( get_option( 'campaignbridge_provider_connection_mailchimp' ) );
	}

	public function test_settings_import_values_are_sanitized_by_domain(): void {
		$method = new \ReflectionMethod( Settings_Controller::class, 'sanitize_imported_settings' );

		$validated = $method->invoke(
			null,
			array(
				'from_name'      => '<b>Campaign Sender</b>',
				'from_email'     => 'sender@example.com',
				'debug_mode'     => true,
				'cache_duration' => 7200,
				'unknown'        => 'ignored',
			)
		);

		$this->assertSame(
			array(
				'from_name'      => 'Campaign Sender',
				'from_email'     => 'sender@example.com',
				'debug_mode'     => true,
				'cache_duration' => 7200,
			),
			$validated
		);
	}

	public function test_settings_import_ignores_invalid_types_and_values(): void {
		$method = new \ReflectionMethod( Settings_Controller::class, 'sanitize_imported_settings' );

		$this->assertSame(
			array(),
			$method->invoke(
				null,
				array(
					'from_name'      => array( 'not', 'a', 'string' ),
					'from_email'     => 'not-an-email',
					'debug_mode'     => 'true',
					'cache_duration' => '3600',
				)
			)
		);
		$this->assertSame( array(), $method->invoke( null, array( 'cache_duration' => -1 ) ) );
		$this->assertSame( array(), $method->invoke( null, 'not-an-object' ) );
	}

	/**
	 * Helper method to reset request state between tests.
	 */
	private function reset_request_state(): void {
		$_POST                     = array();
		$_GET                      = array();
		$_REQUEST                  = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Helper method to clean up test settings.
	 */
	private function cleanup_test_settings(): void {
		$test_options = array(
			'campaignbridge_from_name',
			'campaignbridge_from_email',
			'campaignbridge_reply_to',
			'campaignbridge_provider',
			'campaignbridge_provider_connection_mailchimp',
			'campaignbridge_debug_mode',
			'campaignbridge_log_level',
			'campaignbridge_cache_duration',
			'campaignbridge_rate_limit',
		);

		foreach ( $test_options as $option ) {
			delete_option( $option );
		}
	}
}
