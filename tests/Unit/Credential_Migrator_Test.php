<?php
/**
 * Credential migration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Credential_Migrator;
use CampaignBridge\Core\Encryption;
use WP_UnitTestCase;

/**
 * Verify the one-time provider credential migration.
 */
class Credential_Migrator_Test extends WP_UnitTestCase {
	/**
	 * Reset migration state.
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( 'campaignbridge_credential_schema_version' );
		delete_option( 'campaignbridge_mailchimp_api_key' );
	}

	/**
	 * Clean up migration state.
	 */
	public function tearDown(): void {
		delete_option( 'campaignbridge_credential_schema_version' );
		delete_option( 'campaignbridge_mailchimp_api_key' );
		delete_option( 'campaignbridge_master_key' );
		delete_option( 'campaignbridge_key_metadata' );
		delete_option( 'campaignbridge_retired_encryption_keys' );
		parent::tearDown();
	}

	/**
	 * Plaintext credentials are migrated exactly once.
	 */
	public function test_plaintext_credential_is_migrated_idempotently(): void {
		$plaintext = 'mailchimp-test-fixture-us20';
		update_option( 'campaignbridge_mailchimp_api_key', $plaintext );

		Credential_Migrator::migrate();

		$migrated = get_option( 'campaignbridge_mailchimp_api_key' );
		$this->assertIsString( $migrated );
		$this->assertTrue( Encryption::is_encrypted_value( $migrated ) );
		$this->assertSame( $plaintext, Encryption::decrypt( $migrated ) );
		$this->assertSame( 1, get_option( 'campaignbridge_credential_schema_version' ) );

		Credential_Migrator::migrate();
		$this->assertSame( $migrated, get_option( 'campaignbridge_mailchimp_api_key' ) );
	}
}
