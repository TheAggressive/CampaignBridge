<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Test-delivery request value tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Test_Delivery;
use CampaignBridge\Domain\Provider\Action_Outcome;
use WP_UnitTestCase;

/** Proves test requests are bounded before any provider call and outcomes are classified truthfully. */
final class Test_Delivery_Test extends WP_UnitTestCase {
	public function test_recipients_are_normalized_and_deduplicated(): void {
		$delivery = Test_Delivery::create( array( ' QA@Example.com ', 'qa@example.com', 'lead@example.org' ), Test_Delivery::FORMAT_TEXT );

		self::assertSame( array( 'qa@example.com', 'lead@example.org' ), $delivery->recipients() );
		self::assertSame( 2, $delivery->recipient_count() );
		self::assertSame( 'text', $delivery->format() );
	}

	/** @return array<string, array{array<mixed>, string}> */
	public static function invalid_requests(): array {
		$six = array_map( static fn ( int $i ): string => "qa{$i}@example.com", range( 1, 6 ) );

		return array(
			'no recipients'        => array( array(), 'html' ),
			'too many recipients'  => array( $six, 'html' ),
			'not an address'       => array( array( 'qa-at-example.com' ), 'html' ),
			'header injection'     => array( array( "qa@example.com\r\nBcc: all@example.com" ), 'html' ),
			'non-string recipient' => array( array( 42 ), 'html' ),
			'named map'            => array( array( 'to' => 'qa@example.com' ), 'html' ),
			'oversized address'    => array( array( str_repeat( 'a', 250 ) . '@example.com' ), 'html' ),
			'unknown format'       => array( array( 'qa@example.com' ), 'amp' ),
		);
	}

	/**
	 * @dataProvider invalid_requests
	 * @param array<mixed> $recipients Candidate recipients.
	 */
	public function test_invalid_requests_are_refused( array $recipients, string $format ): void {
		$this->expectException( \InvalidArgumentException::class );
		Test_Delivery::create( $recipients, $format );
	}

	public function test_six_addresses_that_deduplicate_to_five_are_still_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		Test_Delivery::create( array( 'a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com', 'A@example.com' ), 'html' );
	}

	public function test_only_definite_refusals_are_failures(): void {
		$definite  = array( Provider_Error_Category::VALIDATION, Provider_Error_Category::AUTHENTICATION, Provider_Error_Category::AUTHORIZATION, Provider_Error_Category::NOT_FOUND, Provider_Error_Category::CONFLICT, Provider_Error_Category::RATE_LIMITED );
		$ambiguous = array( Provider_Error_Category::TIMEOUT, Provider_Error_Category::NETWORK, Provider_Error_Category::PROVIDER_ERROR, Provider_Error_Category::UNKNOWN );

		foreach ( $definite as $category ) {
			self::assertSame( Action_Outcome::FAILED, Action_Outcome::from_error( Provider_Error::from_category( $category, 'code', 'Message.', 'mailchimp' ) )->status(), $category );
		}
		foreach ( $ambiguous as $category ) {
			self::assertSame( Action_Outcome::AMBIGUOUS, Action_Outcome::from_error( Provider_Error::from_category( $category, 'code', 'Message.', 'mailchimp' ) )->status(), $category );
		}
		self::assertSame( Action_Outcome::ACCEPTED, Action_Outcome::accepted()->status() );
		self::assertNull( Action_Outcome::accepted()->error() );
	}
}
