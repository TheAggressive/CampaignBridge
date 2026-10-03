<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,WordPress.PHP.DiscouragedPHPFunctions
/**
 * wp-config.php encryption key contract test.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Core\Encryption_Keyring;
use WP_UnitTestCase;

/**
 * Proves the documented constant is the key source when defined.
 *
 * Constants cannot be undefined, so this runs in its own process.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class Encryption_Key_Constant_Test extends WP_UnitTestCase {
	public function test_the_wp_config_constant_supplies_the_encryption_key(): void {
		delete_option( 'campaignbridge_master_key' );
		$key = base64_encode( random_bytes( 32 ) );
		define( 'CAMPAIGNBRIDGE_ENCRYPTION_KEY', $key );

		$encrypted = Encryption::encrypt( 'credential-value' );

		self::assertSame( Encryption_Keyring::SOURCE_EXTERNAL, Encryption_Keyring::configured()->source() );
		self::assertStringStartsWith( 'cbenc:v1:' . Encryption_Keyring::key_id( $key ) . ':', $encrypted );
		self::assertSame( 'credential-value', Encryption::decrypt( $encrypted ) );
		self::assertFalse( Encryption_Keyring::database_key_exists() );
		self::assertSame( 'external', Encryption::security_check()['key_source'] );
	}
}
