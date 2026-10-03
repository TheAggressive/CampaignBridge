<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Delivery policy tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Delivery_Policy;
use WP_UnitTestCase;

/** Proves policies only add refusals and fail closed when misconfigured. */
final class Delivery_Policy_Test extends WP_UnitTestCase {
	public function test_the_default_policy_restricts_nothing(): void {
		$policy = Delivery_Policy::unrestricted();

		self::assertFalse( $policy->requires_separate_delivery() );
		self::assertTrue( $policy->allows_delivery_by( 7, 7 ) );
		self::assertTrue( $policy->allows_delivery_by( 7, null ) );
		self::assertNull( $policy->test_domains() );
		self::assertTrue( $policy->allows_test_recipient( 'anyone@anywhere.test' ) );
	}

	public function test_separate_delivery_requires_a_different_recorded_approver(): void {
		$policy = Delivery_Policy::from_settings( true, '' );

		self::assertTrue( $policy->allows_delivery_by( 8, 7 ) );
		self::assertFalse( $policy->allows_delivery_by( 7, 7 ), 'The approver cannot deliver.' );
		self::assertFalse( $policy->allows_delivery_by( 8, null ), 'An unrecorded approver fails closed.' );
	}

	public function test_test_domains_are_parsed_and_matched_exactly(): void {
		$policy = Delivery_Policy::from_settings( false, " Example.COM\n@example.org, example.com ; not a domain\n" );

		self::assertSame( array( 'example.com', 'example.org' ), $policy->test_domains() );
		self::assertTrue( $policy->allows_test_recipient( 'qa@example.com' ) );
		self::assertTrue( $policy->allows_test_recipient( 'lead@example.org' ) );
		self::assertFalse( $policy->allows_test_recipient( 'qa@mail.example.com' ), 'Subdomains are not implied.' );
		self::assertFalse( $policy->allows_test_recipient( 'qa@example.com.evil.test' ) );
		self::assertFalse( $policy->allows_test_recipient( 'qa@evilexample.com' ) );
		self::assertFalse( $policy->allows_test_recipient( 'not-an-address' ) );
	}

	public function test_a_policy_with_no_valid_domain_allows_no_test(): void {
		$policy = Delivery_Policy::from_settings( false, 'not a domain, *.example.com' );

		self::assertSame( array(), $policy->test_domains() );
		self::assertFalse( $policy->allows_test_recipient( 'qa@example.com' ), 'A mistyped policy fails closed.' );
	}
}
