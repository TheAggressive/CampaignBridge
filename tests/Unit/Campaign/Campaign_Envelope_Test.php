<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Campaign envelope tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign_Envelope;
use WP_UnitTestCase;

/** Proves the envelope is frozen as authored and reports handoff problems precisely. */
final class Campaign_Envelope_Test extends WP_UnitTestCase {
	public function test_capture_normalizes_single_line_values_without_judging_completeness(): void {
		$envelope = Campaign_Envelope::capture( "  Spring\n sale\t ", null, "Example\x07 Shop", ' News@Example.COM ' );

		self::assertSame(
			array(
				'subject'      => 'Spring sale',
				'preview_text' => '',
				'from_name'    => 'Example Shop',
				'from_email'   => 'news@example.com',
			),
			$envelope->to_array()
		);
		self::assertSame( array(), $envelope->problems() );
		self::assertSame( $envelope->to_array(), Campaign_Envelope::from_array( $envelope->to_array() )->to_array() );
	}

	/** @return array<string, array{array{mixed, mixed, mixed, mixed}, array<int, string>}> */
	public static function problems(): array {
		return array(
			'empty envelope'      => array( array( '', '', '', '' ), array( 'subject_missing', 'sender_name_missing', 'sender_email_invalid' ) ),
			'long subject'        => array( array( str_repeat( 's', 151 ), '', 'Shop', 'a@example.com' ), array( 'subject_too_long' ) ),
			'malformed token'     => array( array( 'Hi {{cb:subscriber.FIRST}}', '', 'Shop', 'a@example.com' ), array( 'subject_tokens_invalid' ) ),
			'unknown token'       => array( array( 'Hi', '{{cb:subscriber.shoe_size}}', 'Shop', 'a@example.com' ), array( 'preview_text_tokens_invalid' ) ),
			'long preview'        => array( array( 'Hi', str_repeat( 'p', 151 ), 'Shop', 'a@example.com' ), array( 'preview_text_too_long' ) ),
			'long sender name'    => array( array( 'Hi', '', str_repeat( 'n', 101 ), 'a@example.com' ), array( 'sender_name_too_long' ) ),
			'invalid sender'      => array( array( 'Hi', '', 'Shop', 'not-an-email' ), array( 'sender_email_invalid' ) ),
			'valid token subject' => array( array( 'Hi {{cb:subscriber.first_name}}', '', 'Shop', 'a@example.com' ), array() ),
		);
	}

	/**
	 * @dataProvider problems
	 * @param array{mixed, mixed, mixed, mixed} $values   Authored values.
	 * @param array<int, string>                $expected Expected problem codes.
	 */
	public function test_problems_report_exactly_what_a_handoff_would_refuse( array $values, array $expected ): void {
		self::assertSame( $expected, Campaign_Envelope::capture( ...$values )->problems() );
	}

	public function test_unbounded_or_unknown_stored_values_fail_closed(): void {
		try {
			Campaign_Envelope::capture( str_repeat( 'x', 1001 ), '', '', '' );
			self::fail( 'An unbounded value must not be truncated silently.' );
		} catch ( \InvalidArgumentException ) {
			self::assertTrue( true );
		}

		$this->expectException( \InvalidArgumentException::class );
		Campaign_Envelope::from_array(
			array(
				'subject'  => 'Hi',
				'reply_to' => 'a@example.com',
			)
		);
	}
}
