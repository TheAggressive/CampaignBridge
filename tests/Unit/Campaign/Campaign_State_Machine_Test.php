<?php
/**
 * Unit tests for Campaign_State_Machine.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign_State_Machine;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Campaign_State_Machine transition guard.
 *
 * @covers \CampaignBridge\Domain\Campaign\Campaign_State_Machine
 */
final class Campaign_State_Machine_Test extends Test_Case {

	/**
	 * Test the happy path: draft → ready_for_review → approved → provider_draft → scheduled → sending → sent.
	 */
	public function test_happy_path_transitions(): void {
		$this->assertTrue( Campaign_State_Machine::can_transition( 'draft', 'ready_for_review' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'ready_for_review', 'approved' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'approved', 'provider_draft' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'provider_draft', 'scheduled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'scheduled', 'sending' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'sending', 'sent' ) );
	}

	/**
	 * Test that provider_draft can go directly to sending (unscheduled send).
	 */
	public function test_provider_draft_can_send_directly(): void {
		$this->assertTrue( Campaign_State_Machine::can_transition( 'provider_draft', 'sending' ) );
	}

	/**
	 * Test that cancellation is allowed from non-terminal states.
	 */
	public function test_cancellation_allowed_from_non_terminal_states(): void {
		$this->assertTrue( Campaign_State_Machine::can_transition( 'draft', 'cancelled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'ready_for_review', 'cancelled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'approved', 'cancelled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'provider_draft', 'cancelled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'scheduled', 'cancelled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'failed', 'cancelled' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'unknown', 'cancelled' ) );
	}

	/**
	 * Test that cancellation is NOT allowed from terminal states.
	 */
	public function test_cancellation_not_allowed_from_terminal_states(): void {
		$this->assertFalse( Campaign_State_Machine::can_transition( 'sent', 'cancelled' ) );
		$this->assertFalse( Campaign_State_Machine::can_transition( 'cancelled', 'cancelled' ) );
	}

	/**
	 * Test that terminal states have no outgoing transitions.
	 */
	public function test_terminal_states_have_no_transitions(): void {
		$this->assertSame( array(), Campaign_State_Machine::allowed_transitions( 'sent' ) );
		$this->assertSame( array(), Campaign_State_Machine::allowed_transitions( 'cancelled' ) );
	}

	/**
	 * Test that failed state can retry to provider_draft or revert to draft.
	 */
	public function test_failed_state_recovery_paths(): void {
		$this->assertTrue( Campaign_State_Machine::can_transition( 'failed', 'provider_draft' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'failed', 'draft' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'failed', 'cancelled' ) );
	}

	/**
	 * Test that unknown state can recover to provider_draft or fail.
	 */
	public function test_unknown_state_recovery_paths(): void {
		$this->assertTrue( Campaign_State_Machine::can_transition( 'unknown', 'provider_draft' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'unknown', 'failed' ) );
		$this->assertTrue( Campaign_State_Machine::can_transition( 'unknown', 'cancelled' ) );
	}

	/**
	 * Test that invalid states are rejected.
	 */
	public function test_invalid_states_are_rejected(): void {
		$this->assertFalse( Campaign_State_Machine::can_transition( 'nonexistent', 'draft' ) );
		$this->assertFalse( Campaign_State_Machine::can_transition( 'draft', 'nonexistent' ) );
		$this->assertFalse( Campaign_State_Machine::can_transition( '', '' ) );
	}

	/**
	 * Test that assert_transition() throws on invalid transitions.
	 */
	public function test_assert_transition_throws_on_invalid_transition(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Campaign state transition from sent to draft is not allowed.' );

		Campaign_State_Machine::assert_transition( 'sent', 'draft' );
	}

	/**
	 * Test that assert_transition() does not throw on valid transitions.
	 */
	public function test_assert_transition_passes_on_valid_transition(): void {
		Campaign_State_Machine::assert_transition( 'draft', 'ready_for_review' );
		$this->assertTrue( true );
	}

	/**
	 * Test that allowed_transitions() returns the correct set for each state.
	 */
	public function test_allowed_transitions_returns_correct_sets(): void {
		$this->assertSame(
			array( 'ready_for_review', 'cancelled', 'failed' ),
			Campaign_State_Machine::allowed_transitions( 'draft' )
		);

		$this->assertSame(
			array( 'scheduled', 'sending', 'approved', 'cancelled', 'failed', 'unknown' ),
			Campaign_State_Machine::allowed_transitions( 'provider_draft' )
		);

		$this->assertSame(
			array( 'sent', 'failed', 'unknown' ),
			Campaign_State_Machine::allowed_transitions( 'sending' )
		);
	}

	/**
	 * Test that allowed_transitions() returns empty array for invalid states.
	 */
	public function test_allowed_transitions_returns_empty_for_invalid_state(): void {
		$this->assertSame( array(), Campaign_State_Machine::allowed_transitions( 'nonexistent' ) );
	}
}