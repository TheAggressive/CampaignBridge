<?php
/**
 * Connection_Result value object tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Connection_Result;
use CampaignBridge\Domain\Campaign\Provider_Error;
use WP_UnitTestCase;

/**
 * Verify the Connection_Result value object behaves correctly.
 */
class Connection_Result_Test extends WP_UnitTestCase {
	public function test_success_has_no_error(): void {
		$result = Connection_Result::success();
		$this->assertTrue( $result->connected() );
		$this->assertNull( $result->error() );
	}

	public function test_failure_carries_provider_error(): void {
		$error  = Provider_Error::authentication( 'bad_key', 'Invalid key', 'mailchimp' );
		$result = Connection_Result::failure( $error );

		$this->assertFalse( $result->connected() );
		$this->assertNotNull( $result->error() );
		$this->assertSame( $error, $result->error() );
		$this->assertSame( 'authentication', $result->error()->category() );
		$this->assertSame( 'bad_key', $result->error()->code() );
		$this->assertSame( 'Invalid key', $result->error()->message() );
		$this->assertSame( 'mailchimp', $result->error()->provider() );
	}

	public function test_immutability(): void {
		$success = Connection_Result::success();
		$failure = Connection_Result::failure(
			Provider_Error::authentication( 'bad_key', 'Invalid key', 'mailchimp' )
		);

		// Each factory call returns a distinct instance.
		$this->assertNotSame( $success, $failure );

		// Values are stable across reads.
		$this->assertTrue( $success->connected() );
		$this->assertTrue( $success->connected() );
		$this->assertFalse( $failure->connected() );
		$this->assertFalse( $failure->connected() );
	}
}
