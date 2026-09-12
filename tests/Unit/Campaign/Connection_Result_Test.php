<?php
/**
 * Unit tests for Connection_Result.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Connection_Result;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Connection_Result value object.
 *
 * @covers \CampaignBridge\Domain\Campaign\Connection_Result
 */
final class Connection_Result_Test extends Test_Case {

	/**
	 * Test that success() creates a valid result.
	 */
	public function test_success_creates_valid_result(): void {
		$details = array(
			'name' => 'Test Account',
			'plan' => 'free',
		);
		$result  = Connection_Result::success( $details );
		$this->assertTrue( $result->is_success() );
		$this->assertNull( $result->error() );
		$this->assertSame( $details, $result->account_details() );
	}

	/**
	 * Test that success() with no details creates an empty details array.
	 */
	public function test_success_with_empty_details(): void {
		$result = Connection_Result::success();
		$this->assertTrue( $result->is_success() );
		$this->assertNull( $result->error() );
		$this->assertSame( array(), $result->account_details() );
	}

	/**
	 * Test that failure() creates a valid result with error.
	 */
	public function test_failure_creates_valid_result(): void {
		$error  = Provider_Error::authentication( 'AUTH_FAILED', 'Invalid API key.', 'mailchimp' );
		$result = Connection_Result::failure( $error );
		$this->assertFalse( $result->is_success() );
		$this->assertSame( $error, $result->error() );
		$this->assertSame( array(), $result->account_details() );
	}

	/**
	 * Test that failure() with a timeout error is retryable.
	 */
	public function test_failure_with_timeout_error(): void {
		$error  = Provider_Error::timeout( 'TIMEOUT', 'Request timed out.', 'mailchimp' );
		$result = Connection_Result::failure( $error );
		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->error()->is_retryable() );
	}

	/**
	 * Test that to_array() on success includes account details.
	 */
	public function test_to_array_success(): void {
		$details = array(
			'name' => 'Test',
			'plan' => 'pro',
		);
		$result  = Connection_Result::success( $details );
		$array   = $result->to_array();
		$this->assertArrayHasKey( 'verified', $array );
		$this->assertTrue( $array['verified'] );
		$this->assertArrayHasKey( 'account', $array );
		$this->assertSame( $details, $array['account'] );
		$this->assertArrayNotHasKey( 'error', $array );
	}

	/**
	 * Test that to_array() on failure includes error details.
	 */
	public function test_to_array_failure(): void {
		$error  = Provider_Error::authentication( 'AUTH_FAILED', 'Invalid API key.', 'mailchimp' );
		$result = Connection_Result::failure( $error );
		$array  = $result->to_array();
		$this->assertArrayHasKey( 'verified', $array );
		$this->assertFalse( $array['verified'] );
		$this->assertArrayHasKey( 'error', $array );
		$this->assertSame( $error->to_array(), $array['error'] );
		$this->assertArrayNotHasKey( 'account', $array );
	}

	/**
	 * Test that Connection_Result has no setter methods (immutability).
	 */
	public function test_result_is_immutable(): void {
		$result      = Connection_Result::success( array( 'name' => 'Test' ) );
		$methods     = get_class_methods( $result );
		$has_setters = false;
		foreach ( $methods as $method ) {
			if ( str_starts_with( $method, 'set' ) ) {
				$has_setters = true;
				break;
			}
		}
		$this->assertFalse( $has_setters, 'Connection_Result should not have setter methods' );
	}
}
