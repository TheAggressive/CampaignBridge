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
	 * Canonical IDs are exact and ordered deterministically.
	 */
	public function test_default_ids_are_exact_and_ordered(): void {
		$this->assertSame(
			array(
				'cb:subscriber.first_name',
				'cb:subscriber.last_name',
				'cb:subscriber.email',
				'cb:campaign.view_online_url',
				'cb:campaign.unsubscribe_url',
				'cb:organization.name',
				'cb:organization.address',
			),
			Token_Registry::default()->get_ids()
		);
	}

	/**
	 * Provider resolution follows Issue #70: subscriber values and the
	 * view-online/unsubscribe links resolve in a provider context; organization
	 * values resolve locally in CampaignBridge.
	 */
	public function test_provider_resolution_contract(): void {
		$this->assertSame(
			array(
				'cb:subscriber.first_name'    => true,
				'cb:subscriber.last_name'     => true,
				'cb:subscriber.email'         => true,
				'cb:campaign.view_online_url' => true,
				'cb:campaign.unsubscribe_url' => true,
				'cb:organization.name'        => false,
				'cb:organization.address'     => false,
			),
			$this->flags( static fn ( Token_Definition $def ): bool => $def->requires_provider_resolution() )
		);
	}

	/**
	 * Compliance relevance matches the approval-blocking compliance
	 * requirements: the unsubscribe link and the physical postal address.
	 */
	public function test_compliance_relevance_contract(): void {
		$this->assertSame(
			array(
				'cb:subscriber.first_name'    => false,
				'cb:subscriber.last_name'     => false,
				'cb:subscriber.email'         => false,
				'cb:campaign.view_online_url' => false,
				'cb:campaign.unsubscribe_url' => true,
				'cb:organization.name'        => false,
				'cb:organization.address'     => true,
			),
			$this->flags( static fn ( Token_Definition $def ): bool => $def->is_compliance_relevant() )
		);
	}

	/**
	 * Every default token is portable and never uses provider syntax.
	 */
	public function test_default_tokens_are_portable_and_provider_neutral(): void {
		foreach ( Token_Registry::default()->all() as $def ) {
			$this->assertTrue( $def->is_portable(), $def->get_id() );
			$this->assertMatchesRegularExpression( '/^cb:[a-z]+\.[a-z][a-z0-9_]*$/', $def->get_id() );
			$this->assertStringNotContainsString( '*|', $def->get_id() );
		}
	}

	/**
	 * Collect one boolean flag per default token ID.
	 *
	 * @param callable(Token_Definition): bool $flag Flag reader.
	 * @return array<string, bool>
	 */
	private function flags( callable $flag ): array {
		$flags = array();
		foreach ( Token_Registry::default()->all() as $def ) {
			$flags[ $def->get_id() ] = $flag( $def );
		}

		return $flags;
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
