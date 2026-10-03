<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,WordPress.PHP.DiscouragedPHPFunctions
/**
 * Encryption key source tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Core\Encryption_Keyring;
use CampaignBridge\Providers\Mailchimp_Provider;
use WP_UnitTestCase;

/** Proves external keys stay out of the database and every transition keeps ciphertext readable. */
final class Encryption_Keyring_Test extends WP_UnitTestCase {
	private const SECRET = 'mailchimp-secret-value-0123456789abcdef';

	public function setUp(): void {
		parent::setUp();
		$this->forget_database_keys();
	}

	public function tearDown(): void {
		$this->forget_database_keys();
		parent::tearDown();
	}

	private static function external_key(): string {
		return base64_encode( random_bytes( 32 ) );
	}

	private static function database(): Encryption_Keyring {
		return Encryption_Keyring::from_configuration( null, null );
	}

	public function test_without_an_external_key_the_database_fallback_encrypts(): void {
		$keyring   = self::database();
		$encrypted = Encryption::encrypt( self::SECRET, $keyring );

		self::assertTrue( $keyring->is_valid() );
		self::assertSame( Encryption_Keyring::SOURCE_DATABASE, $keyring->source() );
		self::assertTrue( Encryption_Keyring::database_key_exists() );
		self::assertSame( self::SECRET, Encryption::decrypt( $encrypted, $keyring ) );
		self::assertSame( 'current', Encryption::key_status( $encrypted, $keyring ) );
		self::assertSame( Encryption_Keyring::SOURCE_DATABASE, Encryption_Keyring::configured()->source(), 'An unconfigured site uses the fallback.' );
	}

	public function test_an_external_key_encrypts_and_never_enters_the_database(): void {
		$key       = self::external_key();
		$keyring   = Encryption_Keyring::from_configuration( $key, null );
		$encrypted = Encryption::encrypt( self::SECRET, $keyring );

		self::assertSame( Encryption_Keyring::SOURCE_EXTERNAL, $keyring->source() );
		self::assertSame( self::SECRET, Encryption::decrypt( $encrypted, $keyring ) );
		self::assertStringStartsWith( 'cbenc:v1:' . Encryption_Keyring::key_id( $key ) . ':', $encrypted );
		self::assertFalse( Encryption_Keyring::database_key_exists(), 'No fallback key is generated while an external key is configured.' );
		$this->assert_not_in_options( $key );
		$this->assert_not_in_options( base64_decode( $key ) );
	}

	public function test_surrounding_whitespace_in_the_configured_key_is_tolerated(): void {
		$key       = self::external_key();
		$encrypted = Encryption::encrypt( self::SECRET, Encryption_Keyring::from_configuration( "  {$key}\n", null ) );

		self::assertSame( self::SECRET, Encryption::decrypt( $encrypted, Encryption_Keyring::from_configuration( $key, null ) ) );
	}

	public function test_database_ciphertext_stays_readable_after_an_external_key_is_introduced(): void {
		$legacy   = Encryption::encrypt( self::SECRET, self::database() );
		$external = Encryption_Keyring::from_configuration( self::external_key(), null );

		self::assertSame( self::SECRET, Encryption::decrypt( $legacy, $external ) );
		self::assertSame( 'previous', Encryption::key_status( $legacy, $external ) );

		$moved = Encryption::reencrypt( $legacy, $external );
		self::assertNotSame( $legacy, $moved );
		self::assertSame( 'current', Encryption::key_status( $moved, $external ) );
		self::assertSame( self::SECRET, Encryption::decrypt( $moved, $external ) );

		self::assertTrue( Encryption_Keyring::database_key_exists(), 'Re-encryption never destroys the previous key.' );
		self::assertSame( self::SECRET, Encryption::decrypt( $legacy, $external ), 'The old envelope remains readable until the operator removes its key.' );
	}

	public function test_reencryption_of_a_current_value_is_a_no_op(): void {
		$keyring   = Encryption_Keyring::from_configuration( self::external_key(), null );
		$encrypted = Encryption::encrypt( self::SECRET, $keyring );

		self::assertSame( $encrypted, Encryption::reencrypt( $encrypted, $keyring ) );
	}

	public function test_an_external_key_rotates_through_the_retired_list(): void {
		$old      = self::external_key();
		$new      = self::external_key();
		$before   = Encryption::encrypt( self::SECRET, Encryption_Keyring::from_configuration( $old, null ) );
		$rotating = Encryption_Keyring::from_configuration( $new, $old );

		self::assertSame( self::SECRET, Encryption::decrypt( $before, $rotating ) );
		self::assertSame( 'previous', Encryption::key_status( $before, $rotating ) );
		$after = Encryption::reencrypt( $before, $rotating );
		self::assertStringStartsWith( 'cbenc:v1:' . Encryption_Keyring::key_id( $new ) . ':', $after );

		// Once the old key is removed, only re-encrypted values remain readable.
		$rotated = Encryption_Keyring::from_configuration( $new, null );
		self::assertSame( self::SECRET, Encryption::decrypt( $after, $rotated ) );
		self::assertSame( 'unavailable', Encryption::key_status( $before, $rotated ) );
		$this->expectException( \RuntimeException::class );
		Encryption::decrypt( $before, $rotated );
	}

	public function test_rollback_keeps_external_ciphertext_readable_through_the_retired_list(): void {
		$key      = self::external_key();
		$external = Encryption::encrypt( self::SECRET, Encryption_Keyring::from_configuration( $key, null ) );

		// The operator removes the current constant but keeps the key retired.
		$rolled_back = Encryption_Keyring::from_configuration( null, $key );
		self::assertSame( Encryption_Keyring::SOURCE_DATABASE, $rolled_back->source() );
		self::assertSame( self::SECRET, Encryption::decrypt( $external, $rolled_back ) );

		$moved = Encryption::reencrypt( $external, $rolled_back );
		self::assertSame( 'current', Encryption::key_status( $moved, $rolled_back ) );
		self::assertSame( self::SECRET, Encryption::decrypt( $moved, self::database() ) );
	}

	public function test_database_key_rotation_keeps_old_envelopes_and_refuses_with_an_external_key(): void {
		$before = Encryption::encrypt( self::SECRET, self::database() );
		self::assertTrue( self::database()->rotate_database_key() );

		$after = Encryption::encrypt( self::SECRET, self::database() );
		self::assertNotSame( Encryption::key_id_of( $before ), Encryption::key_id_of( $after ) );
		self::assertSame( self::SECRET, Encryption::decrypt( $before, self::database() ) );
		self::assertSame( 'previous', Encryption::key_status( $before, self::database() ) );

		self::assertFalse( Encryption_Keyring::from_configuration( self::external_key(), null )->rotate_database_key() );
	}

	/** @return array<string, array{0: mixed, 1: mixed}> */
	public static function invalid_configurations(): array {
		return array(
			'empty, e.g. a missing environment variable' => array( '', null ),
			'false from getenv()'                        => array( false, null ),
			'short'                                      => array( base64_encode( random_bytes( 16 ) ), null ),
			'long'                                       => array( base64_encode( random_bytes( 33 ) ), null ),
			'not base64'                                 => array( str_repeat( '!', 44 ), null ),
			'raw bytes instead of base64'                => array( str_repeat( 'k', 32 ), null ),
			'array'                                      => array( array( base64_encode( random_bytes( 32 ) ) ), null ),
			'malformed retired key'                      => array( base64_encode( random_bytes( 32 ) ), 'not-a-key' ),
			'non-string retired keys'                    => array( base64_encode( random_bytes( 32 ) ), array() ),
		);
	}

	/** @dataProvider invalid_configurations */
	public function test_a_malformed_external_configuration_fails_closed( mixed $current, mixed $retired ): void {
		$legacy  = Encryption::encrypt( self::SECRET, self::database() );
		$keyring = Encryption_Keyring::from_configuration( $current, $retired );

		self::assertFalse( $keyring->is_valid() );
		self::assertNull( $keyring->current_id() );
		self::assertSame( 'unavailable', Encryption::key_status( $legacy, $keyring ) );

		try {
			Encryption::encrypt( self::SECRET, $keyring );
			self::fail( 'An invalid key configuration must not encrypt, nor fall back to the database key.' );
		} catch ( \RuntimeException $e ) {
			self::assertSame( 'CampaignBridge encryption key configuration is invalid.', $e->getMessage() );
		}

		try {
			Encryption::decrypt( $legacy, $keyring );
			self::fail( 'An invalid key configuration must not decrypt.' );
		} catch ( \RuntimeException $e ) {
			self::assertSame( 'Invalid encrypted data', $e->getMessage() );
		}
	}

	public function test_an_invalid_configuration_never_generates_a_database_key(): void {
		try {
			Encryption::encrypt( self::SECRET, Encryption_Keyring::from_configuration( 'short', null ) );
		} catch ( \RuntimeException ) {
			self::assertFalse( Encryption_Keyring::database_key_exists() );
			return;
		}
		self::fail( 'Expected the invalid configuration to refuse.' );
	}

	public function test_key_material_never_appears_in_diagnostics_or_errors(): void {
		$key     = self::external_key();
		$keyring = Encryption_Keyring::from_configuration( $key, $key );

		self::assertStringNotContainsString( $key, print_r( $keyring, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Proves debug output is redacted.
		self::assertStringNotContainsString( $key, (string) wp_json_encode( Encryption::security_check() ) );
		self::assertStringNotContainsString( $key, (string) wp_json_encode( $keyring->current_id() ) );

		try {
			serialize( $keyring ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Proves keyrings refuse serialization.
			self::fail( 'A keyring must not serialize.' );
		} catch ( \LogicException ) {
			self::assertTrue( true );
		}

		$invalid = 'abcdefghijklmnop-not-a-valid-key';
		try {
			Encryption::encrypt( self::SECRET, Encryption_Keyring::from_configuration( $invalid, null ) );
		} catch ( \RuntimeException $e ) {
			self::assertStringNotContainsString( $invalid, $e->getMessage() );
			self::assertStringNotContainsString( self::SECRET, $e->getTraceAsString() );
			self::assertStringNotContainsString( substr( $invalid, 0, 10 ), $e->getTraceAsString() );
		}
	}

	public function test_credential_arguments_are_redacted_from_stack_traces(): void {
		$credential = 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-nodatacenter';
		try {
			Mailchimp_Provider::build_api_url( $credential, '/ping' );
			self::fail( 'Expected an invalid data center to refuse.' );
		} catch ( \InvalidArgumentException $e ) {
			self::assertStringNotContainsString( 'zzzzzzzz', $e->getTraceAsString() );
		}

		$key = new \ReflectionParameter( array( Encryption::class, 'encrypt' ), 'plaintext' );
		self::assertNotSame( array(), $key->getAttributes( \SensitiveParameter::class ) );
	}

	private function assert_not_in_options( string $needle ): void {
		global $wpdb;
		$matches = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( $needle ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::assertSame( 0, $matches, 'Key material must never be stored in options.' );
	}

	private function forget_database_keys(): void {
		delete_option( 'campaignbridge_master_key' );
		delete_option( 'campaignbridge_key_metadata' );
		delete_option( 'campaignbridge_retired_encryption_keys' );
	}
}
