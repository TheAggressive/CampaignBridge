<?php
/**
 * Unit tests for Token_Definition.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email\Token;

use CampaignBridge\Domain\Email\Token\Token_Definition;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Token_Definition value object.
 *
 * @covers \CampaignBridge\Domain\Email\Token\Token_Definition
 */
final class Token_Definition_Test extends Test_Case {

	/**
	 * Test that create() produces a valid definition with correct fields.
	 */
	public function test_create_produces_valid_definition(): void {
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

		$this->assertSame( 'cb:subscriber.first_name', $def->get_id() );
		$this->assertSame( 'First Name', $def->get_label() );
		$this->assertSame( 'subscriber', $def->get_category() );
		$this->assertSame( 'string', $def->get_value_type() );
		$this->assertTrue( $def->is_portable() );
		$this->assertSame( 'sample', $def->get_preview_behavior() );
		$this->assertTrue( $def->requires_provider_resolution() );
		$this->assertFalse( $def->is_compliance_relevant() );
	}

	/**
	 * Test that get_name() strips the cb: prefix.
	 */
	public function test_get_name_strips_prefix(): void {
		$def = Token_Definition::create(
			'cb:campaign.unsubscribe_url',
			'Unsubscribe URL',
			Token_Definition::CATEGORY_CAMPAIGN,
			Token_Definition::VALUE_TYPE_URL,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			false,
			false
		);

		$this->assertSame( 'campaign.unsubscribe_url', $def->get_name() );
	}

	/**
	 * Test that an invalid ID (missing cb: prefix) throws.
	 */
	public function test_create_rejects_id_without_prefix(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token ID format' );

		Token_Definition::create(
			'subscriber.first_name',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);
	}

	/**
	 * Test that an invalid ID (uppercase) throws.
	 */
	public function test_create_rejects_uppercase_id(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token ID format' );

		Token_Definition::create(
			'cb:subscriber.FirstName',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);
	}

	/**
	 * Test that an invalid ID (empty) throws.
	 */
	public function test_create_rejects_empty_id(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token ID format' );

		Token_Definition::create(
			'',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);
	}

	/**
	 * Test that an invalid ID (no dot after prefix) throws.
	 */
	public function test_create_rejects_id_without_dot(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token ID format' );

		Token_Definition::create(
			'cb:subscriber',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);
	}

	/**
	 * Test that an invalid category throws.
	 */
	public function test_create_rejects_invalid_category(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token category' );

		Token_Definition::create(
			'cb:subscriber.first_name',
			'First Name',
			'invalid_category',
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);
	}

	/**
	 * Test that an invalid value type throws.
	 */
	public function test_create_rejects_invalid_value_type(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token value type' );

		Token_Definition::create(
			'cb:subscriber.first_name',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			'invalid_type',
			true,
			Token_Definition::PREVIEW_SAMPLE,
			true,
			false
		);
	}

	/**
	 * Test that an invalid preview behavior throws.
	 */
	public function test_create_rejects_invalid_preview_behavior(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid preview behavior' );

		Token_Definition::create(
			'cb:subscriber.first_name',
			'First Name',
			Token_Definition::CATEGORY_SUBSCRIBER,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			'invalid_behavior',
			true,
			false
		);
	}

	/**
	 * Test that CATEGORY_ORGANIZATION is accepted.
	 */
	public function test_create_accepts_organization_category(): void {
		$def = Token_Definition::create(
			'cb:organization.name',
			'Organization Name',
			Token_Definition::CATEGORY_ORGANIZATION,
			Token_Definition::VALUE_TYPE_STRING,
			true,
			Token_Definition::PREVIEW_SAMPLE,
			false,
			false
		);

		$this->assertSame( 'organization', $def->get_category() );
	}

	/**
	 * The ID namespace must equal the category.
	 *
	 * @dataProvider mismatched_categories
	 *
	 * @param string $id       Token ID.
	 * @param string $category Supplied category.
	 */
	public function test_create_rejects_id_category_mismatch( string $id, string $category ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Token ID namespace does not match category' );

		Token_Definition::create( $id, 'Label', $category, Token_Definition::VALUE_TYPE_STRING, true, Token_Definition::PREVIEW_SAMPLE, false, false );
	}

	/**
	 * ID and category pairs that disagree.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function mismatched_categories(): array {
		return array(
			'subscriber id, campaign category'     => array( 'cb:subscriber.first_name', Token_Definition::CATEGORY_CAMPAIGN ),
			'campaign id, subscriber category'     => array( 'cb:campaign.unsubscribe_url', Token_Definition::CATEGORY_SUBSCRIBER ),
			'organization id, system category'     => array( 'cb:organization.address', Token_Definition::CATEGORY_SYSTEM ),
			'system id, organization category'     => array( 'cb:system.foo', Token_Definition::CATEGORY_ORGANIZATION ),
			'category prefix is not the namespace' => array( 'cb:subscribers.first_name', Token_Definition::CATEGORY_SUBSCRIBER ),
		);
	}

	/**
	 * Every category accepts IDs in its own namespace.
	 *
	 * @dataProvider matching_categories
	 *
	 * @param string $id       Token ID.
	 * @param string $category Supplied category.
	 */
	public function test_create_accepts_matching_namespace( string $id, string $category ): void {
		$def = Token_Definition::create( $id, 'Label', $category, Token_Definition::VALUE_TYPE_STRING, true, Token_Definition::PREVIEW_SAMPLE, false, false );

		$this->assertSame( $category, $def->get_category() );
	}

	/**
	 * ID and category pairs that agree.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function matching_categories(): array {
		return array(
			'subscriber'   => array( 'cb:subscriber.first_name', Token_Definition::CATEGORY_SUBSCRIBER ),
			'campaign'     => array( 'cb:campaign.unsubscribe_url', Token_Definition::CATEGORY_CAMPAIGN ),
			'organization' => array( 'cb:organization.address', Token_Definition::CATEGORY_ORGANIZATION ),
			'system'       => array( 'cb:system.foo', Token_Definition::CATEGORY_SYSTEM ),
		);
	}
}
