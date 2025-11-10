<?php
/**
 * Tests for Encryption class
 *
 * @package CampaignBridge
 * @subpackage Tests\Unit
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Encryption;
use WP_UnitTestCase;

/**
 * Test Encryption functionality
 */
class Encryption_Test extends WP_UnitTestCase {
	/**
	 * Test data for encryption operations
	 */
	private array $test_data;

	/**
	 * Test admin user ID for cleanup
	 */
	private int $test_admin_user_id;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up admin user for testing (Encryption requires admin for key operations)
		$this->test_admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->test_admin_user_id );

		$this->test_data = array(
			'simple_string'  => 'test_api_key_123',
			'complex_string' => 'api_key_with_special_chars!@#$%^&*()',
			'unicode_string' => '测试API密钥', // Unicode characters
			'long_string'    => str_repeat( 'A', 1000 ), // Long string
			'json_string'    => '{"key": "value", "nested": {"data": "here"}}',
		);
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up encryption-related options
		delete_option( 'campaignbridge_master_key' );
		delete_option( 'campaignbridge_key_metadata' );

		// Clean up test admin user
		if ( isset( $this->test_admin_user_id ) ) {
			wp_delete_user( $this->test_admin_user_id );
		}
	}

	/**
	 * Test basic encryption and decryption round-trip
	 */
	public function test_encrypt_decrypt_round_trip(): void {
		foreach ( $this->test_data as $description => $plaintext ) {
			// Encrypt the plaintext
			$encrypted = Encryption::encrypt( $plaintext );
			$this->assertIsString( $encrypted, "Encryption failed for: $description" );
			$this->assertNotEmpty( $encrypted, "Encrypted result is empty for: $description" );
			$this->assertNotEquals( $plaintext, $encrypted, "Encryption did not change plaintext for: $description" );

			// Decrypt and verify
			$decrypted = Encryption::decrypt( $encrypted );
			$this->assertEquals( $plaintext, $decrypted, "Decryption failed for: $description" );
		}
	}

	/**
	 * Test decryption with invalid data
	 */
	public function test_decrypt_invalid_data(): void {
		// Test data that appears encrypted but is malformed
		$invalid_encrypted_data = array(
			// Create data that looks like valid encrypted format but has wrong key/content
			$this->create_fake_encrypted_data(), // Fake encrypted structure that will fail decryption
		);

		foreach ( $invalid_encrypted_data as $data ) {
			try {
				Encryption::decrypt( $data );
				$this->fail( "Expected RuntimeException for invalid encrypted data: $data" );
			} catch ( \RuntimeException $e ) {
				$message = strtolower( $e->getMessage() );
				// Accept any of the expected decryption error messages
				$this->assertTrue(
					strpos( $message, 'invalid encrypted data' ) !== false ||
					strpos( $message, 'encrypted data too short' ) !== false ||
					strpos( $message, 'invalid encrypted data format' ) !== false,
					"Unexpected error message: {$e->getMessage()}"
				);
			}
		}

		// Test that non-encrypted data is returned as-is (secure behavior)
		$non_encrypted_data = array(
			'invalid_data', // Random string
			'not_encrypted_data', // Non-encrypted data
			'short', // Too short to be encrypted
		);

		foreach ( $non_encrypted_data as $data ) {
			$result = Encryption::decrypt( $data );
			$this->assertEquals( $data, $result, "Non-encrypted data should be returned as-is: $data" );
		}
	}

	/**
	 * Create fake encrypted data that has correct structure but wrong content.
	 * This will pass is_encrypted_value() but fail during actual decryption.
	 */
	private function create_fake_encrypted_data(): string {
		// Create data with correct structure: IV (12) + tag (16) + fake ciphertext
		$iv         = random_bytes( 12 ); // Correct IV length
		$tag        = random_bytes( 16 ); // Correct tag length
		$ciphertext = random_bytes( 50 ); // Fake ciphertext

		$combined = $iv . $tag . $ciphertext;
		return base64_encode( $combined );
	}

	/**
	 * Test decryption for different contexts
	 */
	public function test_decrypt_for_context(): void {
		$plaintext = 'test_sensitive_data';
		$encrypted = Encryption::encrypt( $plaintext );

		// Test different contexts
		$contexts = array( 'api_key', 'sensitive', 'personal', 'public' );

		foreach ( $contexts as $context ) {
			$decrypted = Encryption::decrypt_for_context( $encrypted, $context );
			$this->assertEquals( $plaintext, $decrypted, "Context decryption failed for: $context" );
		}
	}

	/**
	 * Test decrypt for display (admin-only)
	 */
	public function test_decrypt_for_display_admin(): void {
		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$plaintext = 'display_sensitive_data';
		$encrypted = Encryption::encrypt( $plaintext );

		$decrypted = Encryption::decrypt_for_display( $encrypted );
		$this->assertEquals( $plaintext, $decrypted );
	}

	/**
	 * Test decrypt for display (non-admin should be restricted)
	 */
	public function test_decrypt_for_display_non_admin(): void {
		// Create non-admin user
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$encrypted = Encryption::encrypt( 'sensitive_data' );

		try {
			Encryption::decrypt_for_display( $encrypted );
			$this->fail( 'Expected RuntimeException for non-admin user' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'unauthorized attempt to view decrypted data', strtolower( $e->getMessage() ) );
		}
	}

	/**
	 * Test encryption with empty string
	 */
	public function test_encrypt_empty_string(): void {
		$result = Encryption::encrypt( '' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test decryption with empty string
	 */
	public function test_decrypt_empty_string(): void {
		$result = Encryption::decrypt( '' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test key rotation
	 */
	public function test_key_rotation(): void {
		// First, ensure we have a master key
		Encryption::encrypt( 'dummy_data' );

		// Get initial key metadata
		$initial_meta = get_option( 'campaignbridge_key_metadata', array() );

		// Perform key rotation (force it for testing)
		$result = Encryption::rotate_master_key( true );
		$this->assertTrue( $result );

		// Verify key metadata was updated
		$new_meta = get_option( 'campaignbridge_key_metadata', array() );
		$this->assertNotEquals( $initial_meta, $new_meta );

		// Test that new data can be encrypted/decrypted with the rotated key
		// Note: Key rotation intentionally invalidates old encrypted data for security
		$test_data       = 'data_encrypted_after_rotation';
		$encrypted_after = Encryption::encrypt( $test_data );
		$decrypted_after = Encryption::decrypt( $encrypted_after );
		$this->assertEquals( $test_data, $decrypted_after );
	}

	/**
	 * Test security check method
	 */
	public function test_security_check(): void {
		$security_info = Encryption::security_check();

		$this->assertIsArray( $security_info );
		$this->assertArrayHasKey( 'php_version_supported', $security_info );
		$this->assertArrayHasKey( 'openssl_available', $security_info );
		$this->assertArrayHasKey( 'gcm_supported', $security_info );
		$this->assertArrayHasKey( 'master_key_exists', $security_info );
		$this->assertArrayHasKey( 'key_rotation_due', $security_info );

		// Should have reasonable PHP version support
		$this->assertIsBool( $security_info['php_version_supported'] );
	}

	/**
	 * Test encrypted value detection
	 */
	public function test_is_encrypted_value(): void {
		// Test encrypted values
		$encrypted = Encryption::encrypt( 'test_data' );
		$this->assertTrue( Encryption::is_encrypted_value( $encrypted ) );

		// Test non-encrypted values
		$plain_values = array(
			'plain_text',
			'{"key": "value"}',
			'not_encrypted_data',
			'', // Empty string
		);

		foreach ( $plain_values as $value ) {
			$this->assertFalse( Encryption::is_encrypted_value( $value ), "Incorrectly identified as encrypted: $value" );
		}
	}

	/**
	 * Test API key format validation
	 */
	public function test_is_valid_api_key_format(): void {
		$valid_keys = array(
			'sk_test_1234567890abcdef',
			'pk_live_1234567890abcdef',
			'api_key_123',
		);

		$invalid_keys = array(
			'', // Empty
			'short', // Too short
			str_repeat( 'a', 101 ), // Too long
			'key with spaces', // Contains spaces
			'key-with-invalid-chars!@#', // Invalid characters
		);

		foreach ( $valid_keys as $key ) {
			$this->assertTrue( Encryption::is_valid_api_key_format( $key ), "Valid key rejected: $key" );
		}

		foreach ( $invalid_keys as $key ) {
			$this->assertFalse( Encryption::is_valid_api_key_format( $key ), "Invalid key accepted: $key" );
		}
	}

	/**
	 * Test API key format validation with custom pattern
	 */
	public function test_is_valid_api_key_format_custom_pattern(): void {
		$custom_pattern = '/^[A-Z]{3}_\d{8}$/'; // ABC_12345678 format

		$valid_keys = array(
			'ABC_12345678',
			'XYZ_98765432',
		);

		$invalid_keys = array(
			'abc_12345678', // Lowercase
			'ABC_123456789', // Too many digits
			'AB_12345678', // Too few letters
		);

		foreach ( $valid_keys as $key ) {
			$this->assertTrue(
				Encryption::is_valid_api_key_format( $key, $custom_pattern ),
				"Valid key rejected with custom pattern: $key"
			);
		}

		foreach ( $invalid_keys as $key ) {
			$this->assertFalse(
				Encryption::is_valid_api_key_format( $key, $custom_pattern ),
				"Invalid key accepted with custom pattern: $key"
			);
		}
	}

	/**
	 * Test encryption/decryption with special characters
	 */
	public function test_special_characters_handling(): void {
		$special_strings = array(
			'string_with_quotes_"\'',
			"string_with_newlines\n\r\t",
			'string_with_unicode_测试',
			'string_with_emoji_🚀⭐',
			'string_with_html_<script>alert("xss")</script>',
		);

		foreach ( $special_strings as $string ) {
			$encrypted = Encryption::encrypt( $string );
			$decrypted = Encryption::decrypt( $encrypted );
			$this->assertEquals( $string, $decrypted, 'Special characters not handled correctly' );
		}
	}

	/**
	 * Test multiple encryption operations produce different ciphertexts
	 */
	public function test_unique_ciphertexts(): void {
		$plaintext  = 'same_plaintext';
		$encrypted1 = Encryption::encrypt( $plaintext );
		$encrypted2 = Encryption::encrypt( $plaintext );

		// Different encryptions should produce different ciphertexts (due to different IVs)
		$this->assertNotEquals( $encrypted1, $encrypted2 );

		// But both should decrypt to the same plaintext
		$this->assertEquals( $plaintext, Encryption::decrypt( $encrypted1 ) );
		$this->assertEquals( $plaintext, Encryption::decrypt( $encrypted2 ) );
	}

	/**
	 * Test tampering detection (GCM authentication)
	 */
	public function test_tampering_detection(): void {
		$plaintext = 'sensitive_data';
		$encrypted = Encryption::encrypt( $plaintext );

		// Tamper with the encrypted data
		$tampered = substr( $encrypted, 0, -1 ) . 'X'; // Change last character

		try {
			Encryption::decrypt( $tampered );
			$this->fail( 'Expected RuntimeException for tampered data' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'invalid encrypted data', strtolower( $e->getMessage() ) );
		}
	}
}
