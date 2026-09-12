<?php
/**
 * Unit tests for Provider_Error_Category.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Provider_Error_Category value object.
 *
 * @covers \CampaignBridge\Domain\Campaign\Provider_Error_Category
 */
final class Provider_Error_Category_Test extends Test_Case {

	/**
	 * Test that all() returns exactly ten categories.
	 */
	public function test_all_returns_ten_categories(): void {
		$this->assertCount( 10, Provider_Error_Category::all() );
	}

	/**
	 * Test that all known categories are present.
	 */
	public function test_all_contains_known_categories(): void {
		$categories = Provider_Error_Category::all();

		$this->assertContains( 'authentication', $categories );
		$this->assertContains( 'authorization', $categories );
		$this->assertContains( 'rate_limited', $categories );
		$this->assertContains( 'validation', $categories );
		$this->assertContains( 'not_found', $categories );
		$this->assertContains( 'conflict', $categories );
		$this->assertContains( 'timeout', $categories );
		$this->assertContains( 'network', $categories );
		$this->assertContains( 'provider_error', $categories );
		$this->assertContains( 'unknown', $categories );
	}

	/**
	 * Test that is_valid() accepts all known categories.
	 */
	public function test_is_valid_accepts_known_categories(): void {
		foreach ( Provider_Error_Category::all() as $category ) {
			$this->assertTrue( Provider_Error_Category::is_valid( $category ), "Expected '{$category}' to be valid" );
		}
	}

	/**
	 * Test that is_valid() rejects unknown categories.
	 */
	public function test_is_valid_rejects_unknown_categories(): void {
		$this->assertFalse( Provider_Error_Category::is_valid( 'nonexistent' ) );
		$this->assertFalse( Provider_Error_Category::is_valid( '' ) );
		$this->assertFalse( Provider_Error_Category::is_valid( 'AUTHENTICATION' ) );
	}

	/**
	 * Test that is_retryable() identifies retryable categories.
	 */
	public function test_is_retryable_identifies_retryable_categories(): void {
		$this->assertTrue( Provider_Error_Category::is_retryable( 'rate_limited' ) );
		$this->assertTrue( Provider_Error_Category::is_retryable( 'timeout' ) );
		$this->assertTrue( Provider_Error_Category::is_retryable( 'network' ) );
		$this->assertTrue( Provider_Error_Category::is_retryable( 'unknown' ) );
	}

	/**
	 * Test that is_retryable() rejects non-retryable categories.
	 */
	public function test_is_retryable_rejects_non_retryable_categories(): void {
		$this->assertFalse( Provider_Error_Category::is_retryable( 'authentication' ) );
		$this->assertFalse( Provider_Error_Category::is_retryable( 'authorization' ) );
		$this->assertFalse( Provider_Error_Category::is_retryable( 'validation' ) );
		$this->assertFalse( Provider_Error_Category::is_retryable( 'not_found' ) );
		$this->assertFalse( Provider_Error_Category::is_retryable( 'conflict' ) );
		$this->assertFalse( Provider_Error_Category::is_retryable( 'provider_error' ) );
	}
}