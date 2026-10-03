<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Schedule time validation tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Schedule_Time;
use WP_UnitTestCase;

/** Proves a delivery time is unambiguous, on the provider interval, and plausibly intended. */
final class Schedule_Time_Test extends WP_UnitTestCase {
	private const NOW = '2026-10-05T12:00:00Z';

	public function test_offsets_are_normalized_to_utc(): void {
		self::assertSame( '2026-10-05T15:00:00Z', Schedule_Time::parse( '2026-10-05T15:00:00Z', self::NOW, 15 )->utc() );
		self::assertSame( '2026-10-05T15:00:00Z', Schedule_Time::parse( '2026-10-05T08:00:00-07:00', self::NOW, 15 )->utc() );
		self::assertSame( '2026-10-05T15:15:00Z', Schedule_Time::parse( '2026-10-05T21:00:00+05:45', self::NOW, 15 )->utc() );
		self::assertSame( '2026-10-05T12:13:00Z', Schedule_Time::parse( '2026-10-05T12:13:00Z', self::NOW, 1 )->utc(), 'A provider without an interval accepts any minute.' );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_times(): array {
		return array(
			'no offset'          => array( '2026-10-05T15:00:00', 'explicit time zone' ),
			'no seconds'         => array( '2026-10-05T15:00Z', 'explicit time zone' ),
			'date only'          => array( '2026-10-05', 'explicit time zone' ),
			'impossible date'    => array( '2026-02-30T15:00:00Z', 'valid calendar' ),
			'impossible hour'    => array( '2026-10-05T25:00:00Z', 'valid calendar' ),
			'off the interval'   => array( '2026-10-05T15:05:00Z', '15-minute boundary' ),
			'seconds'            => array( '2026-10-05T15:00:30Z', '15-minute boundary' ),
			'in the past'        => array( '2026-10-05T11:45:00Z', 'in the future' ),
			'inside the lead'    => array( '2026-10-05T12:00:00Z', 'in the future' ),
			'beyond the horizon' => array( '2027-10-07T12:00:00Z', 'within one year' ),
		);
	}

	/** @dataProvider invalid_times */
	public function test_ambiguous_or_implausible_times_are_refused( string $requested, string $reason ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( $reason );
		Schedule_Time::parse( $requested, self::NOW, 15 );
	}

	public function test_the_minimum_lead_is_inclusive(): void {
		self::assertSame( '2026-10-05T12:15:00Z', Schedule_Time::parse( '2026-10-05T12:15:00Z', '2026-10-05T12:05:00Z', 15 )->utc() );

		$this->expectException( \InvalidArgumentException::class );
		Schedule_Time::parse( '2026-10-05T12:15:00Z', '2026-10-05T12:05:01Z', 15 );
	}
}
