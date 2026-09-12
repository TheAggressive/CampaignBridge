<?php
/**
 * Unit tests for Campaign_State.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Campaign_State value object.
 *
 * @covers \CampaignBridge\Domain\Campaign\Campaign_State
 */
final class Campaign_State_Test extends Test_Case {

	/**
	 * Test that all() returns exactly ten states.
	 */
	public function test_all_returns_ten_states(): void {
		$this->assertCount( 10, Campaign_State::all() );
	}

	/**
	 * Test that all known states are present.
	 */
	public function test_all_contains_known_states(): void {
		$states = Campaign_State::all();

		$this->assertContains( 'draft', $states );
		$this->assertContains( 'ready_for_review', $states );
		$this->assertContains( 'approved', $states );
		$this->assertContains( 'provider_draft', $states );
		$this->assertContains( 'scheduled', $states );
		$this->assertContains( 'sending', $states );
		$this->assertContains( 'sent', $states );
		$this->assertContains( 'failed', $states );
		$this->assertContains( 'cancelled', $states );
		$this->assertContains( 'unknown', $states );
	}

	/**
	 * Test that is_valid() accepts all known states.
	 */
	public function test_is_valid_accepts_known_states(): void {
		foreach ( Campaign_State::all() as $state ) {
			$this->assertTrue( Campaign_State::is_valid( $state ), "Expected '{$state}' to be valid" );
		}
	}

	/**
	 * Test that is_valid() rejects unknown states.
	 */
	public function test_is_valid_rejects_unknown_states(): void {
		$this->assertFalse( Campaign_State::is_valid( 'nonexistent' ) );
		$this->assertFalse( Campaign_State::is_valid( '' ) );
		$this->assertFalse( Campaign_State::is_valid( 'DRAFT' ) );
	}

	/**
	 * Test that is_terminal() identifies terminal states.
	 */
	public function test_is_terminal_identifies_terminal_states(): void {
		$this->assertTrue( Campaign_State::is_terminal( 'sent' ) );
		$this->assertTrue( Campaign_State::is_terminal( 'cancelled' ) );
	}

	/**
	 * Test that is_terminal() rejects non-terminal states.
	 */
	public function test_is_terminal_rejects_non_terminal_states(): void {
		$this->assertFalse( Campaign_State::is_terminal( 'draft' ) );
		$this->assertFalse( Campaign_State::is_terminal( 'sending' ) );
		$this->assertFalse( Campaign_State::is_terminal( 'failed' ) );
		$this->assertFalse( Campaign_State::is_terminal( 'unknown' ) );
	}

	/**
	 * Test that is_failure() identifies failure states.
	 */
	public function test_is_failure_identifies_failure_states(): void {
		$this->assertTrue( Campaign_State::is_failure( 'failed' ) );
		$this->assertTrue( Campaign_State::is_failure( 'unknown' ) );
	}

	/**
	 * Test that is_failure() rejects non-failure states.
	 */
	public function test_is_failure_rejects_non_failure_states(): void {
		$this->assertFalse( Campaign_State::is_failure( 'draft' ) );
		$this->assertFalse( Campaign_State::is_failure( 'sent' ) );
		$this->assertFalse( Campaign_State::is_failure( 'cancelled' ) );
	}
}