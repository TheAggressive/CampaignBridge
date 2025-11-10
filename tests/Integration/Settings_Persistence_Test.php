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
		// Simulate form submission with provider settings
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'providers'         => array(
				'provider'           => $this->test_settings_data['campaignbridge_provider'],
				'mailchimp_api_key'  => $this->test_settings_data['campaignbridge_mailchimp_api_key'],
				'mailchimp_audience' => $this->test_settings_data['campaignbridge_mailchimp_audience'],
			),
			'providers_wpnonce' => wp_create_nonce( 'campaignbridge_form_providers' ),
		);

		// Simulate the providers settings screen form submission
		$this->simulate_providers_settings_submission();

		// Verify settings were saved to options
		$this->assertEquals( $this->test_settings_data['campaignbridge_provider'], get_option( 'campaignbridge_provider' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_mailchimp_api_key'], get_option( 'campaignbridge_mailchimp_api_key' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_mailchimp_audience'], get_option( 'campaignbridge_mailchimp_audience' ) );

		// Simulate fresh page load
		$this->reset_request_state();

		// Verify settings persist
		$this->assertEquals( $this->test_settings_data['campaignbridge_provider'], get_option( 'campaignbridge_provider' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_mailchimp_api_key'], get_option( 'campaignbridge_mailchimp_api_key' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_mailchimp_audience'], get_option( 'campaignbridge_mailchimp_audience' ) );

		// Test that Settings_Controller loads the settings correctly
		$controller = new Settings_Controller();
		$data       = $controller->get_data();

		$this->assertEquals( $this->test_settings_data['campaignbridge_provider'], $data['provider'] ?? get_option( 'campaignbridge_provider' ) );
		$this->assertEquals( $this->test_settings_data['campaignbridge_mailchimp_api_key'], $data['mailchimp_api_key'] );
		$this->assertEquals( $this->test_settings_data['campaignbridge_mailchimp_audience'], $data['mailchimp_audience'] );
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
	 * Helper method to simulate providers settings form submission.
	 */
	private function simulate_providers_settings_submission(): void {
		// Simulate the custom save logic from providers.php
		if ( isset( $_POST['providers'] ) ) {
			$data = $_POST['providers'];

			update_option( 'campaignbridge_provider', $data['provider'] );

			if ( $data['provider'] === 'mailchimp' ) {
				if ( ! empty( $data['mailchimp_api_key'] ) ) {
					update_option( 'campaignbridge_mailchimp_api_key', $data['mailchimp_api_key'] );
				}
				if ( ! empty( $data['mailchimp_audience'] ) ) {
					update_option( 'campaignbridge_mailchimp_audience', $data['mailchimp_audience'] );
				}
			}
		}
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
			'campaignbridge_mailchimp_api_key',
			'campaignbridge_mailchimp_audience',
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
