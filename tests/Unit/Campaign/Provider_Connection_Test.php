<?php
/**
 * Unit tests for Provider_Connection.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Provider_Connection value object.
 *
 * @covers \CampaignBridge\Domain\Campaign\Provider_Connection
 */
final class Provider_Connection_Test extends Test_Case {

	/**
	 * Test that create() creates a valid connection.
	 */
	public function test_create_creates_valid_connection(): void {
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );
		$this->assertSame( 'mailchimp', $connection->provider_slug() );
		$this->assertSame( 'encrypted-abc123', $connection->api_key() );
		$this->assertSame( 'aud-456', $connection->audience_id() );
		$this->assertSame( 1, $connection->schema_version() );
		$this->assertNull( $connection->last_verified_at() );
		$this->assertFalse( $connection->is_verified() );
		$this->assertSame( array(), $connection->account_details() );
	}

	/**
	 * Test that create() accepts an empty audience_id.
	 */
	public function test_create_with_empty_audience_id(): void {
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', '' );
		$this->assertSame( '', $connection->audience_id() );
	}

	/**
	 * Test that create() with empty provider_slug throws.
	 */
	public function test_create_empty_provider_slug_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Provider slug must not be empty.' );
		Provider_Connection::create( '', 'encrypted-abc123' );
	}

	/**
	 * Test that create() with empty api_key throws.
	 */
	public function test_create_empty_api_key_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'API key must not be empty.' );
		Provider_Connection::create( 'mailchimp', '' );
	}

	/**
	 * Test that from_array() with valid data creates a connection.
	 */
	public function test_from_array_valid_data(): void {
		$data       = array(
			'schema_version'   => 1,
			'provider_slug'    => 'mailchimp',
			'api_key'          => 'encrypted-abc123',
			'audience_id'      => 'aud-456',
			'last_verified_at' => '2024-01-15T10:30:00Z',
			'verified'         => true,
			'account_details'  => array( 'name' => 'Test Account' ),
		);
		$connection = Provider_Connection::from_array( $data );
		$this->assertSame( 'mailchimp', $connection->provider_slug() );
		$this->assertSame( 'encrypted-abc123', $connection->api_key() );
		$this->assertSame( 'aud-456', $connection->audience_id() );
		$this->assertSame( 1, $connection->schema_version() );
		$this->assertSame( '2024-01-15T10:30:00Z', $connection->last_verified_at() );
		$this->assertTrue( $connection->is_verified() );
		$this->assertSame( array( 'name' => 'Test Account' ), $connection->account_details() );
	}

	/**
	 * Test that from_array() with missing schema_version throws.
	 */
	public function test_from_array_missing_schema_version_throws(): void {
		$data = array(
			'provider_slug' => 'mailchimp',
			'api_key'       => 'encrypted-abc123',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid schema version.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with missing provider_slug throws.
	 */
	public function test_from_array_missing_provider_slug_throws(): void {
		$data = array(
			'schema_version' => 1,
			'api_key'        => 'encrypted-abc123',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid provider slug.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with missing api_key throws.
	 */
	public function test_from_array_missing_api_key_throws(): void {
		$data = array(
			'schema_version' => 1,
			'provider_slug'  => 'mailchimp',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid API key.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with empty api_key throws.
	 */
	public function test_from_array_empty_api_key_throws(): void {
		$data = array(
			'schema_version' => 1,
			'provider_slug'  => 'mailchimp',
			'api_key'        => '',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid API key.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with unsupported schema_version throws.
	 */
	public function test_from_array_unsupported_schema_version_throws(): void {
		$data = array(
			'schema_version' => 99,
			'provider_slug'  => 'mailchimp',
			'api_key'        => 'encrypted-abc123',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unsupported provider connection schema version 99.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with non-integer schema_version throws.
	 */
	public function test_from_array_non_integer_schema_version_throws(): void {
		$data = array(
			'schema_version' => 'one',
			'provider_slug'  => 'mailchimp',
			'api_key'        => 'encrypted-abc123',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid schema version.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with non-string provider_slug throws.
	 */
	public function test_from_array_non_string_provider_slug_throws(): void {
		$data = array(
			'schema_version' => 1,
			'provider_slug'  => 123,
			'api_key'        => 'encrypted-abc123',
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid provider slug.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that from_array() with non-string api_key throws.
	 */
	public function test_from_array_non_string_api_key_throws(): void {
		$data = array(
			'schema_version' => 1,
			'provider_slug'  => 'mailchimp',
			'api_key'        => 12345,
		);
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid API key.' );
		Provider_Connection::from_array( $data );
	}

	/**
	 * Test that to_array() returns the correct structure.
	 */
	public function test_to_array_returns_correct_structure(): void {
		$connection = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );
		$array      = $connection->to_array();
		$this->assertIsArray( $array );
		$this->assertSame( 1, $array['schema_version'] );
		$this->assertSame( 'mailchimp', $array['provider_slug'] );
		$this->assertSame( 'encrypted-abc123', $array['api_key'] );
		$this->assertSame( 'aud-456', $array['audience_id'] );
		$this->assertNull( $array['last_verified_at'] );
		$this->assertFalse( $array['verified'] );
		$this->assertSame( array(), $array['account_details'] );
		$this->assertCount( 7, $array );
	}

	/**
	 * Test that from_array(to_array()) round-trips correctly.
	 */
	public function test_round_trip(): void {
		$original = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );
		$restored = Provider_Connection::from_array( $original->to_array() );
		$this->assertSame( $original->provider_slug(), $restored->provider_slug() );
		$this->assertSame( $original->api_key(), $restored->api_key() );
		$this->assertSame( $original->audience_id(), $restored->audience_id() );
		$this->assertSame( $original->schema_version(), $restored->schema_version() );
		$this->assertSame( $original->is_verified(), $restored->is_verified() );
		$this->assertSame( $original->account_details(), $restored->account_details() );
	}

	/**
	 * Test round-trip with verified state and account details.
	 */
	public function test_round_trip_with_verified_state(): void {
		$data     = array(
			'schema_version'   => 1,
			'provider_slug'    => 'mailchimp',
			'api_key'          => 'encrypted-abc123',
			'audience_id'      => 'aud-456',
			'last_verified_at' => '2024-01-15T10:30:00Z',
			'verified'         => true,
			'account_details'  => array( 'name' => 'Test' ),
		);
		$original = Provider_Connection::from_array( $data );
		$restored = Provider_Connection::from_array( $original->to_array() );
		$this->assertSame( '2024-01-15T10:30:00Z', $restored->last_verified_at() );
		$this->assertTrue( $restored->is_verified() );
		$this->assertSame( array( 'name' => 'Test' ), $restored->account_details() );
	}

	/**
	 * Test that with_verification() returns a new instance.
	 */
	public function test_with_verification_returns_new_instance(): void {
		$original = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );
		$updated  = $original->with_verification( true, '2024-01-15T10:30:00Z', array( 'name' => 'Test' ) );
		$this->assertNotSame( $original, $updated );
		$this->assertFalse( $original->is_verified() );
		$this->assertNull( $original->last_verified_at() );
		$this->assertTrue( $updated->is_verified() );
		$this->assertSame( '2024-01-15T10:30:00Z', $updated->last_verified_at() );
		$this->assertSame( array( 'name' => 'Test' ), $updated->account_details() );
	}

	/**
	 * Test that with_audience() returns a new instance.
	 */
	public function test_with_audience_returns_new_instance(): void {
		$original = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );
		$updated  = $original->with_audience( 'aud-789' );
		$this->assertNotSame( $original, $updated );
		$this->assertSame( 'aud-456', $original->audience_id() );
		$this->assertSame( 'aud-789', $updated->audience_id() );
	}

	/**
	 * Test that Provider_Connection has no setter methods (immutability).
	 */
	public function test_connection_is_immutable(): void {
		$connection  = Provider_Connection::create( 'mailchimp', 'encrypted-abc123', 'aud-456' );
		$methods     = get_class_methods( $connection );
		$has_setters = false;
		foreach ( $methods as $method ) {
			if ( str_starts_with( $method, 'set' ) ) {
				$has_setters = true;
				break;
			}
		}
		$this->assertFalse( $has_setters, 'Provider_Connection should not have setter methods' );
	}
}
