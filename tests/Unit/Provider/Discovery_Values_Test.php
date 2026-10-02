<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Discovered reference value tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Provider\Discovered_Audience;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovered_Segment;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Sender_Identity;
use WP_UnitTestCase;

/** Proves discovered references are bounded, display-safe, and round-trip exactly. */
final class Discovery_Values_Test extends WP_UnitTestCase {
	public function test_display_labels_are_single_line_and_bounded(): void {
		$audience = Discovered_Audience::create( 'abc123', "  Customers\n\tand\x07 Friends\u{200B} ", 42 );

		self::assertSame( 'Customers and Friends', $audience->name() );
		self::assertSame( 42, $audience->member_count() );
		self::assertNull( $audience->default_sender() );
	}

	/** @return array<string, array{callable(): mixed}> */
	public static function invalid_values(): array {
		return array(
			'empty name'          => array( static fn () => Discovered_Audience::create( 'abc123', " \n " ) ),
			'over-long name'      => array( static fn () => Discovered_Audience::create( 'abc123', str_repeat( 'a', 256 ) ) ),
			'unsafe id'           => array( static fn () => Discovered_Audience::create( '../lists', 'Customers' ) ),
			'negative count'      => array( static fn () => Discovered_Audience::create( 'abc123', 'Customers', -1 ) ),
			'string count'        => array( static fn () => Discovered_Audience::create( 'abc123', 'Customers', '42' ) ),
			'invalid sender'      => array( static fn () => Sender_Identity::create( 'Shop', 'not-an-email' ) ),
			'lowercase merge tag' => array( static fn () => Discovered_Merge_Field::create( 'fname', 'First', 'text', false ) ),
			'merge tag syntax'    => array( static fn () => Discovered_Merge_Field::create( '*|FNAME|*', 'First', 'text', false ) ),
			'non-bool required'   => array( static fn () => Discovered_Merge_Field::create( 'FNAME', 'First', 'text', 'no' ) ),
			'unknown segment'     => array( static fn () => Discovered_Segment::create( '7', 'VIP', 'static' ) ),
		);
	}

	/** @dataProvider invalid_values */
	public function test_invalid_remote_values_fail_closed( callable $factory ): void {
		$this->expectException( \InvalidArgumentException::class );
		$factory();
	}

	public function test_results_round_trip_and_bind_kind_to_scope(): void {
		$audiences = Discovery_Result::create(
			'mailchimp',
			'',
			Discovery_Batch::create(
				Discovery_Kind::AUDIENCES,
				array( Discovered_Audience::create( 'abc123', 'Customers', 3, Sender_Identity::create( 'Shop', 'Hello@Example.com' ) ) ),
				true
			),
			'2026-10-01T12:00:00Z'
		);
		self::assertSame( $audiences->to_array(), Discovery_Result::from_array( $audiences->to_array() )->to_array() );
		self::assertSame( 'hello@example.com', $audiences->to_array()['items'][0]['default_sender']['from_email'] );

		$fields = Discovery_Result::create(
			'mailchimp',
			'abc123',
			Discovery_Batch::create( Discovery_Kind::MERGE_FIELDS, array( Discovered_Merge_Field::create( 'FNAME', 'First Name', 'text', false ) ), false ),
			'2026-10-01T12:00:00Z'
		);
		self::assertFalse( Discovery_Result::from_array( $fields->to_array() )->is_complete() );

		$this->expectException( \InvalidArgumentException::class );
		Discovery_Result::create( 'mailchimp', '', Discovery_Batch::create( Discovery_Kind::SEGMENTS, array(), true ), '2026-10-01T12:00:00Z' );
	}

	public function test_batches_reject_mismatched_items_and_unbounded_lists(): void {
		try {
			Discovery_Batch::create( Discovery_Kind::AUDIENCES, array( Discovered_Segment::create( '7', 'VIP', 'tag' ) ), true );
			self::fail( 'A segment cannot be an audience.' );
		} catch ( \InvalidArgumentException ) {
			self::assertTrue( true );
		}

		$this->expectException( \InvalidArgumentException::class );
		Discovery_Batch::create(
			Discovery_Kind::SEGMENTS,
			array_fill( 0, Discovery_Batch::MAX_ITEMS + 1, Discovered_Segment::create( '7', 'VIP', 'tag' ) ),
			true
		);
	}

	public function test_stored_results_with_unknown_schema_fail_closed(): void {
		$this->expectException( \InvalidArgumentException::class );
		Discovery_Result::from_array(
			array(
				'schema_version' => 99,
				'provider'       => 'mailchimp',
				'kind'           => 'audiences',
				'scope'          => '',
				'items'          => array(),
				'complete'       => true,
				'fetched_at'     => '2026-10-01T12:00:00Z',
			)
		);
	}
}
