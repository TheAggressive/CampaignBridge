<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Provider capability contract tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Providers\Html_Provider;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Providers\Mailchimp_Provider;
use WP_UnitTestCase;

/** Proves capabilities are explicit, validated, and truthful per provider. */
final class Provider_Capabilities_Test extends WP_UnitTestCase {
	public function test_every_operation_is_reported_and_missing_flags_are_unsupported(): void {
		$capabilities = Provider_Capabilities::from_flags( 'example', array( 'verify_connection' => true ) );

		self::assertSame( Provider_Operation::all(), array_keys( $capabilities->to_array() ) );
		self::assertSame( array( Provider_Operation::VERIFY_CONNECTION ), $capabilities->supported() );
		self::assertTrue( $capabilities->supports( Provider_Operation::VERIFY_CONNECTION ) );
		self::assertFalse( $capabilities->supports( Provider_Operation::DISCOVER_AUDIENCES ) );
		self::assertFalse( $capabilities->supports( 'not_an_operation' ) );
	}

	/** @return array<string, array{string, array<string, mixed>}> */
	public static function invalid_advertisements(): array {
		return array(
			'unknown operation'   => array( 'example', array( 'send_everything' => true ) ),
			'non-boolean flag'    => array( 'example', array( 'send' => 'yes' ) ),
			'truthy integer flag' => array( 'example', array( 'send' => 1 ) ),
			'invalid slug'        => array( 'Not A Slug', array() ),
		);
	}

	/**
	 * @dataProvider invalid_advertisements
	 * @param array<string, mixed> $flags Advertised flags.
	 */
	public function test_invalid_advertisements_are_rejected( string $provider, array $flags ): void {
		$this->expectException( \InvalidArgumentException::class );
		Provider_Capabilities::from_flags( $provider, $flags );
	}

	public function test_mailchimp_advertises_only_implemented_operations(): void {
		$capabilities = ( new Mailchimp_Provider() )->capabilities();

		self::assertSame(
			array(
				Provider_Operation::VERIFY_CONNECTION,
				Provider_Operation::DISCOVER_AUDIENCES,
				Provider_Operation::DISCOVER_MERGE_FIELDS,
				Provider_Operation::DISCOVER_SEGMENTS,
				Provider_Operation::DISCOVER_SENDERS,
				Provider_Operation::CREATE_DRAFT,
				Provider_Operation::SEND_TEST,
				Provider_Operation::SCHEDULE,
				Provider_Operation::UNSCHEDULE,
			),
			$capabilities->supported()
		);
		// Immediate send, in-flight cancel, reconciliation, and reports are not implemented yet.
		foreach ( array( 'send', 'cancel', 'reconcile', 'reports' ) as $operation ) {
			self::assertFalse( $capabilities->supports( $operation ), $operation );
		}
		self::assertSame( $capabilities->to_array(), ( new Mailchimp_Discovery() )->capabilities()->to_array() );
	}

	public function test_html_export_advertises_only_local_operations(): void {
		$capabilities = ( new Html_Provider() )->capabilities();

		self::assertTrue( $capabilities->supports( Provider_Operation::EXPORT ) );
		self::assertFalse( $capabilities->supports( Provider_Operation::DISCOVER_AUDIENCES ) );
		self::assertFalse( $capabilities->supports( Provider_Operation::SEND ) );
	}
}
