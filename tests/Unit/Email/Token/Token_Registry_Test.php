<?php
/**
 * Unit tests for Token_Registry.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email\Token;

use CampaignBridge\Domain\Email\Token\Token_Definition;
use CampaignBridge\Domain\Email\Token\Token_Registry;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Token_Registry.
 *
 * @covers \CampaignBridge\Domain\Email\Token\Token_Registry
 */
final class Token_Registry_Test extends Test_Case {

	/**
	 * Test that the registry version is at least 1.
	 */
	public function test_version_is_at_least_one(): void {
		$registry = Token_Registry::default();
		$this->assertGreaterThanOrEqual( 1, $registry->get_version() );
	}

	/**
	 * Test that the default registry contains all 7 canonical token IDs.
	 */
	public function test_default_contains_all_seven_ids(): void {
		$registry = Token_Registry::default();
		$expected_ids = array(
			'cb:subscriber.first_name',
			'cb:subscriber.last_name',
			'cb:subscriber.email',
			'cb:campaign.view_online_url',
			'cb:campaign.unsubscribe_url',
			'cb:organization.name',
			'cb:organization.address',
		);

		foreach ( $expected_ids as $id ) {
			$this->assertTrue( $registry->has( $id ), "Registry should contain {$id}" );
		}
	}

	/**
	 * Test that the default registry has exactly 7 tokens.
	 */
	public function test_default_has_exactly_seven_tokens(): void {
		$registry = Token_Registry::default();
		$this->assertCount( 7, $registry->all() );
	}

	/**
	 * Test that duplicate IDs are rejected.
	 */
	public function test_duplicate_ids_are_rejected(): void {
		$def = Token_Definition::create(
			'cb:subscriber.first_name',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Duplicate token ID' );

		Token_Registry::create( 1, array( $def, $def ) );
	}

	/**
	 * Test that an unknown ID lookup returns null.
	 */
	public function test_get_unknown_id_returns_null(): void {
		$registry = Token_Registry::default();
		$this->assertNull( $registry->get( 'cb:unknown.token' ) );
	}

	/**
	 * Test that has() returns false for unknown IDs.
	 */
	public function test_has_unknown_id_returns_false(): void {
		$registry = Token_Registry::default();
		$this->assertFalse( $registry->has( 'cb:unknown.token' ) );
	}

	/**
	 * Test that default() is deterministic (two calls produce identical arrays).
	 */
	public function test_default_is_deterministic(): void {
		$a = Token_Registry::default();
		$b = Token_Registry::default();

		$this->assertEquals( $a->all(), $b->all() );
	}

	/**
	 * Test that subscriber tokens require provider resolution.
	 */
	public function test_subscriber_tokens_require_provider_resolution(): void {
		$registry = Token_Registry::default();

		foreach ( array( 'cb:subscriber.first_name', 'cb:subscriber.last_name', 'cb:subscriber.email' ) as $id ) {
			$def = $registry->get( $id );
			$this->assertNotNull( $def, "Expected {$id} to exist" );
			$this->assertTrue( $def->requires_provider_resolution(), "{$id} should require provider resolution" );
		}
	}

	/**
	 * Test that campaign and organization tokens do not require provider resolution.
	 */
	public function test_campaign_and_organization_tokens_do_not_require_provider_resolution(): void {
		$registry = Token_Registry::default();

		foreach ( array( 'cb:campaign.view_online_url', 'cb:campaign.unsubscribe_url', 'cb:organization.name', 'cb:organization.address' ) as $id ) {
			$def = $registry->get( $id );
			$this->assertNotNull( $def, "Expected {$id} to exist" );
			$this->assertFalse( $def->requires_provider_resolution(), "{$id} should not require provider resolution" );
		}
	}

	/**
	 * Test that subscriber.email is the only compliance-relevant token.
	 */
	public function test_subscriber_email_is_only_compliance_relevant(): void {
		$registry = Token_Registry::default();

		$compliance_tokens = array();
		foreach ( $registry->all() as $def ) {
			if ( $def->is_compliance_relevant() ) {
				$compliance_tokens[] = $def->get_id();
			}
		}

		$this->assertSame( array( 'cb:subscriber.email' ), $compliance_tokens );
	}

	/**
	 * Test that subscriber.email has PREVIEW_OMIT behavior.
	 */
	public function test_subscriber_email_has_preview_omit(): void {
		$registry = Token_Registry::default();
		$def = $registry->get( 'cb:subscriber.email' );
		$this->assertNotNull( $def );
		$this->assertSame( Token_Definition::PREVIEW_OMIT, $def->get_preview_behavior() );
	}
}
