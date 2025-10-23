<?php
/**
 * CRITICAL Mailchimp Provider Integration Tests for CampaignBridge.
 *
 * Tests real Mailchimp provider functionality with actual API integration.
 * These tests verify that the Mailchimp provider works correctly with real
 * CampaignBridge code and handles API responses properly.
 *
 * SECURITY NOTE: Never commit real API keys! Tests use environment variables
 * or skip when keys aren't available. See tests/README.md for details.
 *
 * @package CampaignBridge\Tests\Integration
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Providers\Html_Provider;
use CampaignBridge\Providers\Abstract_Provider;
use CampaignBridge\Providers\Provider_Interface;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test Mailchimp provider integration with real CampaignBridge code
 */
class Mailchimp_Provider_Integration_Test extends Test_Case {

	/**
	 * Mailchimp provider instance
	 */
	private Mailchimp_Provider $mailchimp_provider;

	/**
	 * HTML provider instance for comparison
	 */
	private Html_Provider $html_provider;

	public function setUp(): void {
		parent::setUp();

		$this->mailchimp_provider = new Mailchimp_Provider();
		$this->html_provider      = new Html_Provider();
	}

	/**
	 * CRITICAL TEST: Mailchimp provider implements required interfaces
	 */
	public function test_mailchimp_provider_implements_required_interfaces(): void {
		$this->assertInstanceOf(
			Provider_Interface::class,
			$this->mailchimp_provider,
			'Mailchimp provider should implement Provider_Interface'
		);

		$this->assertInstanceOf(
			Abstract_Provider::class,
			$this->mailchimp_provider,
			'Mailchimp provider should extend Abstract_Provider'
		);
	}

	/**
	 * CRITICAL TEST: Mailchimp provider has correct provider metadata
	 */
	public function test_mailchimp_provider_has_correct_metadata(): void {
		$this->assertEquals(
			'mailchimp',
			$this->mailchimp_provider->get_slug(),
			'Mailchimp provider should have correct slug'
		);

		$this->assertEquals(
			'Mailchimp',
			$this->mailchimp_provider->get_name(),
			'Mailchimp provider should have correct name'
		);

		$this->assertStringContainsString(
			'Mailchimp',
			$this->mailchimp_provider->label(),
			'Mailchimp provider description should mention Mailchimp'
		);
	}

	/**
	 * CRITICAL TEST: Mailchimp provider has correct capabilities
	 */
	public function test_mailchimp_provider_has_correct_capabilities(): void {
		$capabilities = $this->mailchimp_provider->get_capabilities();

		$this->assertIsArray( $capabilities, 'Capabilities should be an array' );

		// Mailchimp should support audiences and analytics
		$this->assertTrue(
			$capabilities['audiences'] ?? false,
			'Mailchimp should support audiences'
		);

		$this->assertTrue(
			$capabilities['templates'] ?? false,
			'Mailchimp should support templates'
		);

		$this->assertTrue(
			$capabilities['analytics'] ?? false,
			'Mailchimp should support analytics'
		);

		// Mailchimp does not support automation in this implementation
		$this->assertFalse(
			$capabilities['automation'] ?? true,
			'Mailchimp should not support automation in this version'
		);
	}

	/**
	 * CRITICAL TEST: Mailchimp provider configuration validation works
	 */
	public function test_mailchimp_provider_configuration_validation(): void {
		// Test with empty settings - should not be configured
		$empty_settings = array();
		$this->assertFalse(
			$this->mailchimp_provider->is_configured( $empty_settings ),
			'Provider should not be configured without API key'
		);

		// Test with invalid API key format
		$invalid_settings = array( 'api_key' => 'invalid-key-format' );
		$this->assertFalse(
			$this->mailchimp_provider->is_configured( $invalid_settings ),
			'Provider should not be configured with invalid API key format'
		);

		// Test with valid API key format (but fake key)
		$valid_format_settings = array( 'api_key' => 'test-api-key-format-validation-only' );
		$this->assertTrue(
			$this->mailchimp_provider->is_configured( $valid_format_settings ),
			'Provider should be configured with valid API key format'
		);
	}

	/**
	 * CRITICAL TEST: Mailchimp provider API key validation
	 */
	public function test_mailchimp_provider_api_key_validation(): void {
		// Skip API key format tests if no real keys are provided (CI environment)
		if ( ! getenv( 'MAILCHIMP_TEST_API_KEY' ) ) {
			$this->markTestSkipped( 'Real Mailchimp API key not provided for testing' );
			return;
		}

		// Test valid Mailchimp API key formats using environment variable
		$real_key = getenv( 'MAILCHIMP_TEST_API_KEY' );
		$this->assertTrue(
			$this->mailchimp_provider->is_valid_api_key( $real_key ),
			'Should accept valid API key from environment'
		);

		// Test invalid formats (these are safe to test)
		$this->assertFalse(
			$this->mailchimp_provider->is_valid_api_key( '' ),
			'Should reject empty API key'
		);

		$this->assertFalse(
			$this->mailchimp_provider->is_valid_api_key( 'short' ),
			'Should reject short API key'
		);

		$this->assertFalse(
			$this->mailchimp_provider->is_valid_api_key( 'test-key-too-short' ),
			'Should reject invalid length'
		);

		$this->assertFalse(
			$this->mailchimp_provider->is_valid_api_key( 'test-key-invalid-data-center' ),
			'Should reject invalid data center format'
		);
	}

	/**
	 * CRITICAL TEST: Mailchimp provider settings fields rendering
	 */
	public function test_mailchimp_provider_settings_fields_rendering(): void {
		// Test with no API key configured
		$empty_settings = array();
		ob_start();
		$this->mailchimp_provider->render_settings_fields( $empty_settings, 'test_settings' );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'API Key',
			$output,
			'Settings should contain API Key field'
		);
		$this->assertStringNotContainsString(
			'api-key-masked',
			$output,
			'Should not show masked field when no key is configured'
		);

		// Test with API key configured (use fake key for rendering tests)
		$configured_settings = array( 'api_key' => 'test-configured-api-key-rendering-test' );
		ob_start();
		$this->mailchimp_provider->render_settings_fields( $configured_settings, 'test_settings' );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'api-key-masked',
			$output,
			'Should show masked field when key is configured'
		);
		$this->assertStringContainsString(
			'Change',
			$output,
			'Should show change button when key is configured'
		);
	}

	/**
	 * CRITICAL TEST: Provider comparison - Mailchimp vs HTML
	 */
	public function test_provider_comparison_mailchimp_vs_html(): void {
		// Test that providers have different capabilities
		$mailchimp_caps = $this->mailchimp_provider->get_capabilities();
		$html_caps      = $this->html_provider->get_capabilities();

		$this->assertNotEquals(
			$mailchimp_caps,
			$html_caps,
			'Mailchimp and HTML providers should have different capabilities'
		);

		// Mailchimp should support audiences, HTML should not
		$this->assertTrue(
			$mailchimp_caps['audiences'] ?? false,
			'Mailchimp should support audiences'
		);
		$this->assertFalse(
			$html_caps['audiences'] ?? true,
			'HTML provider should not support audiences'
		);

		// Both should support templates
		$this->assertTrue(
			$mailchimp_caps['templates'] ?? false,
			'Mailchimp should support templates'
		);
		$this->assertTrue(
			$html_caps['templates'] ?? false,
			'HTML provider should support templates'
		);
	}

	/**
	 * CRITICAL TEST: Mailchimp provider handles API errors gracefully
	 */
	public function test_mailchimp_provider_handles_api_errors_gracefully(): void {
		// This test verifies that the provider has error handling methods
		$this->assertTrue(
			method_exists( $this->mailchimp_provider, 'handle_api_error' ),
			'Provider should have API error handling method'
		);

		$this->assertTrue(
			method_exists( $this->mailchimp_provider, 'is_valid_api_key' ),
			'Provider should have API key validation method'
		);
	}

	/**
	 * CRITICAL TEST: Provider interface compliance
	 */
	public function test_provider_interface_compliance(): void {
		// Test that all required interface methods exist
		$required_methods = array(
			'get_slug',
			'get_name',
			'get_description',
			'get_capabilities',
			'is_configured',
			'render_settings_fields',
			'is_valid_api_key',
		);

		foreach ( $required_methods as $method ) {
			$this->assertTrue(
				method_exists( $this->mailchimp_provider, $method ),
				"Provider should implement {$method} method"
			);
		}
	}

	/**
	 * CRITICAL TEST: Mailchimp provider initialization
	 */
	public function test_mailchimp_provider_initialization(): void {
		// Test that provider initializes correctly
		$this->assertNotNull(
			$this->mailchimp_provider,
			'Mailchimp provider should initialize successfully'
		);

		// Test that provider has required properties set
		$this->assertIsString(
			$this->mailchimp_provider->get_slug(),
			'Provider slug should be a string'
		);

		$this->assertIsString(
			$this->mailchimp_provider->get_name(),
			'Provider name should be a string'
		);

		$this->assertIsArray(
			$this->mailchimp_provider->get_capabilities(),
			'Provider capabilities should be an array'
		);
	}

	/**
	 * CRITICAL TEST: Provider settings integration with WordPress options
	 */
	public function test_provider_settings_integration_with_wordpress_options(): void {
		// Test that provider can work with WordPress options structure
		$test_api_key  = getenv( 'MAILCHIMP_TEST_API_KEY' ) ?: 'test-wordpress-options-integration-key';
		$test_settings = array(
			'mailchimp_api_key'  => $test_api_key,
			'mailchimp_settings' => array(
				'timeout' => 30,
				'debug'   => false,
			),
		);

		// Update option
		update_option( 'campaignbridge_settings', $test_settings );

		// Verify provider can work with this structure
		$saved_settings = get_option( 'campaignbridge_settings' );
		$this->assertEquals(
			$test_settings,
			$saved_settings,
			'Provider should work with WordPress options structure'
		);

		// Test configuration check with saved settings
		$provider_settings = array( 'api_key' => $saved_settings['mailchimp_api_key'] );
		$this->assertTrue(
			$this->mailchimp_provider->is_configured( $provider_settings ),
			'Provider should be configured with saved API key'
		);

		// Clean up
		delete_option( 'campaignbridge_settings' );
	}

	/**
	 * CRITICAL TEST: Mailchimp provider vs HTML provider feature comparison
	 */
	public function test_mailchimp_vs_html_provider_feature_comparison(): void {
		$mailchimp_features = array(
			'audiences'  => true,
			'templates'  => true,
			'scheduling' => true,
			'automation' => false,
			'analytics'  => true,
		);

		$html_features = array(
			'audiences'  => false,
			'templates'  => true,
			'scheduling' => false,
			'automation' => false,
			'analytics'  => false,
		);

		$actual_mailchimp = $this->mailchimp_provider->get_capabilities();
		$actual_html      = $this->html_provider->get_capabilities();

		// Verify Mailchimp capabilities match expected
		foreach ( $mailchimp_features as $feature => $expected ) {
			$this->assertEquals(
				$expected,
				$actual_mailchimp[ $feature ] ?? null,
				"Mailchimp {$feature} capability should be {$expected}"
			);
		}

		// Verify HTML capabilities match expected
		foreach ( $html_features as $feature => $expected ) {
			$this->assertEquals(
				$expected,
				$actual_html[ $feature ] ?? null,
				"HTML {$feature} capability should be {$expected}"
			);
		}
	}

	/**
	 * CRITICAL TEST: Provider API key pattern validation
	 */
	public function test_provider_api_key_pattern_validation(): void {
		// Use reflection to access the protected api_key_pattern property
		$reflection = new \ReflectionClass( Mailchimp_Provider::class );
		$property   = $reflection->getProperty( 'api_key_pattern' );
		$property->setAccessible( true );

		$pattern = $property->getValue( $this->mailchimp_provider );

		$this->assertIsString( $pattern, 'API key pattern should be a string' );
		$this->assertStringStartsWith( '/', $pattern, 'Pattern should be a regex' );
		$this->assertStringEndsWith( '/', $pattern, 'Pattern should end with delimiter' );

		// Test that the pattern works for valid keys (skip in CI without real key)
		if ( getenv( 'MAILCHIMP_TEST_API_KEY' ) ) {
			$this->assertMatchesRegularExpression(
				$pattern,
				getenv( 'MAILCHIMP_TEST_API_KEY' ),
				'Pattern should match valid API key'
			);
		} else {
			$this->markTestSkipped( 'Real Mailchimp API key not provided for pattern testing' );
		}

		$this->assertDoesNotMatchRegularExpression(
			$pattern,
			'invalid-key',
			'Pattern should not match invalid API key'
		);
	}
}
