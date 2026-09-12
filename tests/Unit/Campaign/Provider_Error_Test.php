<?php
/**
 * Unit tests for Provider_Error.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Provider_Error value object.
 *
 * @covers \CampaignBridge\Domain\Campaign\Provider_Error
 */
final class Provider_Error_Test extends Test_Case {

	/**
	 * Test that from_category() creates a valid error with correct fields.
	 */
	public function test_from_category_creates_valid_error(): void {
		$error = Provider_Error::from_category( 'timeout', 'ERR_TIMEOUT', 'Request timed out', 'mailchimp' );

		$this->assertSame( 'timeout', $error->category() );
		$this->assertSame( 'ERR_TIMEOUT', $error->code() );
		$this->assertSame( 'Request timed out', $error->message() );
		$this->assertSame( 'mailchimp', $error->provider() );
		$this->assertTrue( $error->is_retryable() );
	}

	/**
	 * Test that from_category() with a non-retryable category sets retryable to false.
	 */
	public function test_from_category_non_retryable(): void {
		$error = Provider_Error::from_category( 'authentication', 'ERR_AUTH', 'Invalid credentials', 'mailchimp' );

		$this->assertSame( 'authentication', $error->category() );
		$this->assertFalse( $error->is_retryable() );
	}

	/**
	 * Test that from_category() falls back to unknown for invalid categories.
	 */
	public function test_from_category_falls_back_to_unknown(): void {
		$error = Provider_Error::from_category( 'nonexistent_category', 'ERR_X', 'Something happened', 'mailchimp' );

		$this->assertSame( 'unknown', $error->category() );
		$this->assertTrue( $error->is_retryable() );
	}

	/**
	 * Test that timeout() creates a retryable timeout error.
	 */
	public function test_timeout_creates_retryable_error(): void {
		$error = Provider_Error::timeout( 'ERR_TIMEOUT', 'Connection timed out', 'mailchimp' );

		$this->assertSame( 'timeout', $error->category() );
		$this->assertSame( 'ERR_TIMEOUT', $error->code() );
		$this->assertSame( 'Connection timed out', $error->message() );
		$this->assertSame( 'mailchimp', $error->provider() );
		$this->assertTrue( $error->is_retryable() );
	}

	/**
	 * Test that authentication() creates a non-retryable authentication error.
	 */
	public function test_authentication_creates_non_retryable_error(): void {
		$error = Provider_Error::authentication( 'ERR_AUTH', 'Invalid API key', 'mailchimp' );

		$this->assertSame( 'authentication', $error->category() );
		$this->assertSame( 'ERR_AUTH', $error->code() );
		$this->assertSame( 'Invalid API key', $error->message() );
		$this->assertSame( 'mailchimp', $error->provider() );
		$this->assertFalse( $error->is_retryable() );
	}

	/**
	 * Test that to_array() returns the correct structure.
	 */
	public function test_to_array_returns_correct_structure(): void {
		$error = Provider_Error::timeout( 'ERR_TIMEOUT', 'Connection timed out', 'mailchimp' );

		$array = $error->to_array();

		$this->assertIsArray( $array );
		$this->assertSame( 'timeout', $array['category'] );
		$this->assertSame( 'ERR_TIMEOUT', $array['code'] );
		$this->assertSame( 'Connection timed out', $array['message'] );
		$this->assertSame( 'mailchimp', $array['provider'] );
		$this->assertTrue( $array['retryable'] );
		$this->assertCount( 5, $array );
	}

	/**
	 * Test that to_array() for a non-retryable error.
	 */
	public function test_to_array_non_retryable(): void {
		$error = Provider_Error::authentication( 'ERR_AUTH', 'Invalid API key', 'mailchimp' );

		$array = $error->to_array();

		$this->assertFalse( $array['retryable'] );
	}

	/**
	 * Test that the error is immutable (no setters).
	 */
	public function test_error_is_immutable(): void {
		$error = Provider_Error::timeout( 'ERR_TIMEOUT', 'Connection timed out', 'mailchimp' );

		// Verify there are no public setter methods.
		$methods = get_class_methods( $error );
		$has_setters = false;

		foreach ( $methods as $method ) {
			if ( str_starts_with( $method, 'set' ) ) {
				$has_setters = true;
				break;
			}
		}

		$this->assertFalse( $has_setters, 'Provider_Error should not have setter methods' );
	}
}