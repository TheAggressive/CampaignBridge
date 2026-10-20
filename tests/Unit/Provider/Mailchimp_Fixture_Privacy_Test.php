<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Recorded Mailchimp fixture privacy tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use WP_UnitTestCase;

/** Proves exchanges recorded from a live account carry their provenance and no personal data. */
final class Mailchimp_Fixture_Privacy_Test extends WP_UnitTestCase {
	/** @return array<string, array{string}> */
	public static function fixtures(): array {
		$files = glob( dirname( __DIR__, 2 ) . '/Fixtures/Mailchimp/*.json' );
		self::assertNotEmpty( $files );
		$cases = array();
		foreach ( $files as $file ) {
			$cases[ basename( $file ) ] = array( $file );
		}

		return $cases;
	}

	/** @dataProvider fixtures */
	public function test_a_recorded_fixture_states_its_source_and_holds_no_personal_data( string $file ): void {
		$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
		$data = json_decode( $text, true );

		self::assertIsArray( $data );
		self::assertIsString( $data['source'] ?? null, 'Say where and when the exchange was recorded.' );
		self::assertNotEmpty( $data['exchanges'] ?? null );
		self::assertSame( 0, preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text ), 'No email address may be recorded.' );
		self::assertSame( 0, preg_match( '/Bearer\s+[0-9a-f]{32}|[0-9a-f]{32}-us\d+/i', $text ), 'No credential may be recorded.' );
	}

	public function test_the_report_fixture_keeps_the_aggregate_shapes_reporting_needs(): void {
		$data      = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Mailchimp/reports.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
		$exchanges = array_column( $data['exchanges'], null, 'name' );

		$report = $exchanges['report']['body'];
		self::assertSame( 'mc0042', $report['id'] );
		self::assertSame( 1, $report['emails_sent'] );
		self::assertSame( array( 'hard_bounces', 'soft_bounces', 'syntax_errors' ), array_keys( $report['bounces'] ) );
		self::assertArrayHasKey( 'proxy_excluded_unique_opens', $report['opens'], 'Apple Mail Privacy Protection opens are reported separately.' );
		self::assertSame( 'delivered', $report['delivery_status']['status'], 'Mailchimp says delivered once the send finishes, even for a bounce.' );
		self::assertSame( 1, $report['bounces']['soft_bounces'] );
		self::assertSame( 0, $exchanges['draft-report']['body']['emails_sent'], 'An unsent draft still has a report, with nothing sent.' );
		self::assertSame( 404, $exchanges['missing-report']['status'] );
	}
}
