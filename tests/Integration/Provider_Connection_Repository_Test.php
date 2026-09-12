<?php
/**
 * Provider connection repository persistence tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Provider_Connection_Repository.
 *
 * @covers \CampaignBridge\Repository\Provider_Connection_Repository
 */
final class Provider_Connection_Repository_Test extends Test_Case {

	public function tearDown(): void {
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		( new Provider_Connection_Repository() )->delete( 'other_provider' );
		parent::tearDown();
	}

	public function test_save_load_round_trip(): void {
		$repository = new Provider_Connection_Repository();
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );

		$this->assertTrue( $repository->save( $connection ) );

		$loaded = $repository->get( 'mailchimp' );
		$this->assertNotNull( $loaded );
		$this->assertSame( 'mailchimp', $loaded->provider_slug() );
		$this->assertSame( 'encrypted-abc123', $loaded->api_key() );
		$this->assertSame( 'aud-456', $loaded->audience_id() );
		$this->assertSame( 1, $loaded->schema_version() );
		$this->assertNull( $loaded->last_verified_at() );
		$this->assertFalse( $loaded->is_verified() );
		$this->assertSame( array(), $loaded->account_details() );
	}

	public function test_round_trip_with_verified_state(): void {
		$repository = new Provider_Connection_Repository();
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' )
			->with_verification( true, '2024-01-15T10:30:00Z', array( 'name' => 'Test Account' ) );

		$this->assertTrue( $repository->save( $connection ) );

		$loaded = $repository->get( 'mailchimp' );
		$this->assertNotNull( $loaded );
		$this->assertTrue( $loaded->is_verified() );
		$this->assertSame( '2024-01-15T10:30:00Z', $loaded->last_verified_at() );
		$this->assertSame( array( 'name' => 'Test Account' ), $loaded->account_details() );
	}

	public function test_missing_record_returns_null(): void {
		$repository = new Provider_Connection_Repository();

		$this->assertNull( $repository->get( 'nonexistent_provider' ) );
	}

	public function test_malformed_stored_record_returns_null(): void {
		$repository = new Provider_Connection_Repository();

		update_option( 'campaignbridge_provider_connection_mailchimp', array(
			'schema_version' => 1,
			'provider_slug'  => 'mailchimp',
			'api_key'        => '',
		) );

		$this->assertNull( $repository->get( 'mailchimp' ) );
	}

	public function test_unsupported_future_schema_returns_null(): void {
		$repository = new Provider_Connection_Repository();

		update_option( 'campaignbridge_provider_connection_mailchimp', array(
			'schema_version' => 99,
			'provider_slug'  => 'mailchimp',
			'api_key'        => 'encrypted-abc123',
		) );

		$this->assertNull( $repository->get( 'mailchimp' ) );
	}

	public function test_delete_existing_record(): void {
		$repository = new Provider_Connection_Repository();
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123' );

		$this->assertTrue( $repository->save( $connection ) );
		$this->assertNotNull( $repository->get( 'mailchimp' ) );

		$this->assertTrue( $repository->delete( 'mailchimp' ) );
		$this->assertNull( $repository->get( 'mailchimp' ) );
	}

	public function test_delete_missing_record_is_safe(): void {
		$repository = new Provider_Connection_Repository();

		$this->assertTrue( $repository->delete( 'never_stored_provider' ) );
	}

	public function test_encrypted_credential_remains_opaque(): void {
		$repository = new Provider_Connection_Repository();
		$encrypted  = 'base64:c2VjcmV0LWtleS1kYXRh==:iv:randomsalt';
		$connection = Provider_Connection::create( 'mailchimp', $encrypted, 'aud-456' );

		$this->assertTrue( $repository->save( $connection ) );

		$loaded = $repository->get( 'mailchimp' );
		$this->assertNotNull( $loaded );
		$this->assertSame( $encrypted, $loaded->api_key() );

		$raw = get_option( 'campaignbridge_provider_connection_mailchimp' );
		$this->assertIsArray( $raw );
		$this->assertSame( $encrypted, $raw['api_key'] );
	}

	public function test_provider_slug_mismatch_returns_null(): void {
		$repository = new Provider_Connection_Repository();

		// Manually store a record whose provider_slug does not match the key.
		update_option( 'campaignbridge_provider_connection_mailchimp', array(
			'schema_version' => 1,
			'provider_slug'  => 'other_provider',
			'api_key'        => 'encrypted-abc123',
		) );

		$this->assertNull( $repository->get( 'mailchimp' ) );
	}

	public function test_saving_unchanged_record_reports_success(): void {
		$repository = new Provider_Connection_Repository();
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123' );

		$this->assertTrue( $repository->save( $connection ) );
		$this->assertTrue( $repository->save( $connection ) );
		$this->assertNotNull( $repository->get( 'mailchimp' ) );
	}
}