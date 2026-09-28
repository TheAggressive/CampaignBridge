<?php
/**
 * Remote reference, delivery attempt, and audit persistence tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;

/** Proves normalized remote identities, idempotency, and safe audit storage. */
final class Campaign_Operation_Repository_Test extends Test_Case {
	/** Ensure each test owns an empty custom-table fixture. */
	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
		}
		self::assertTrue( ( new Campaign_Repository() )->add( $this->campaign( 'campaign-one' ) ) );
		self::assertTrue( ( new Campaign_Repository() )->add( $this->campaign( 'campaign-two' ) ) );
	}

	/** Both local/provider and provider/remote identities are unique and queryable. */
	public function test_remote_reference_round_trip_and_uniqueness(): void {
		$repository = new Remote_Campaign_Reference_Repository();
		$reference  = $this->remote_reference( 'campaign-one', 'remote-1', 'draft' );

		self::assertNull( $repository->get( 'campaign-one', 'provider-one' ) );
		self::assertTrue( $repository->add( $reference ) );
		self::assertSame( $reference->to_array(), $repository->get( 'campaign-one', 'provider-one' )?->to_array() );
		self::assertSame( 'campaign-one', $repository->find_remote( 'provider-one', 'remote-1' )?->campaign_id() );
		self::assertFalse( $repository->add( $this->remote_reference( 'campaign-one', 'remote-2', 'draft' ) ) );
		self::assertFalse( $repository->add( $this->remote_reference( 'campaign-two', 'remote-1', 'draft' ) ) );
	}

	/** Observation updates cannot remap either side of a remote identity. */
	public function test_remote_observation_update_preserves_identity(): void {
		$repository = new Remote_Campaign_Reference_Repository();
		self::assertTrue( $repository->add( $this->remote_reference( 'campaign-one', 'remote-1', 'draft' ) ) );
		$updated = Remote_Campaign_Reference::from_array(
			array(
				'schema_version' => 1,
				'campaign_id'    => 'campaign-one',
				'provider'       => 'provider-one',
				'remote_id'      => 'remote-1',
				'observed_state' => 'scheduled',
				'cursor'         => 'etag-2',
				'observed_at'    => '2026-01-01T00:02:00Z',
				'reconciled_at'  => '2026-01-01T00:03:00Z',
			)
		);

		self::assertTrue( $repository->update_observation( $updated ) );
		self::assertSame( 'scheduled', $repository->get( 'campaign-one', 'provider-one' )?->observed_state() );
		self::assertFalse( $repository->update_observation( $this->remote_reference( 'campaign-one', 'remote-other', 'sent' ) ) );
	}

	/** Unknown outcomes round trip and a duplicate idempotency identity is refused. */
	public function test_delivery_attempt_idempotency_and_unknown_outcome(): void {
		$repository = new Delivery_Attempt_Repository();
		$pending    = $this->attempt( 'attempt-one', 'pending', 'unknown', null );

		self::assertNull( $repository->get( 'missing-attempt' ) );
		self::assertTrue( $repository->add( $pending ) );
		self::assertFalse( $repository->add( $this->attempt( 'attempt-duplicate', 'pending', 'unknown', null ) ) );
		self::assertSame( 'attempt-one', $repository->find_idempotency( 'campaign-one', 'send', 'request-1' )?->id() );

		$unknown = $this->attempt( 'attempt-one', 'unknown', 'unknown', 'request-correlation' );
		self::assertTrue( $repository->update_result( $unknown ) );
		self::assertSame( 'unknown', $repository->get( 'attempt-one' )?->status() );
		self::assertSame( 'request-correlation', $repository->get( 'attempt-one' )?->remote_correlation() );
	}

	/** Delivery result updates keep immutable identity fields and reads are bounded. */
	public function test_delivery_attempt_identity_and_bounded_listing(): void {
		$repository = new Delivery_Attempt_Repository();
		foreach ( array( 'attempt-a', 'attempt-b', 'attempt-c' ) as $id ) {
			$attempt = Delivery_Attempt::from_array( array_merge( $this->attempt( $id, 'pending', 'unknown', null )->to_array(), array( 'idempotency_key' => $id ) ) );
			self::assertTrue( $repository->add( $attempt ) );
		}
		self::assertCount( 2, $repository->for_campaign( 'campaign-one', 2 ) );

		$wrong_campaign = Delivery_Attempt::from_array( array_merge( $this->attempt( 'attempt-a', 'failed', 'retryable', null )->to_array(), array( 'campaign_id' => 'campaign-two', 'idempotency_key' => 'attempt-a' ) ) );
		self::assertFalse( $repository->update_result( $wrong_campaign ) );
	}

	/** A future delivery-attempt row is unreadable and is never rewritten. */
	public function test_future_delivery_attempt_returns_null(): void {
		global $wpdb;
		$repository = new Delivery_Attempt_Repository();
		self::assertTrue( $repository->add( $this->attempt( 'attempt-future', 'pending', 'unknown', null ) ) );
		$wpdb->update( Schema_Manager::table( 'delivery_attempts' ), array( 'data_version' => 99 ), array( 'id' => 'attempt-future' ) );

		self::assertNull( $repository->get( 'attempt-future' ) );
		self::assertSame( '99', (string) $wpdb->get_var( 'SELECT data_version FROM ' . Schema_Manager::table( 'delivery_attempts' ) . " WHERE id = 'attempt-future'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted table and fixed fixture.
	}

	/** Audit context redacts secrets and PII before the append-only write. */
	public function test_audit_event_round_trip_redacts_sensitive_context(): void {
		global $wpdb;
		$repository = new Audit_Event_Repository();
		$event      = $this->audit_event(
			'audit-one',
			array(
				'provider'       => 'provider-one',
				'api_token'      => 'never-store-me',
				'customer_email' => 'person@example.org',
				'nested'         => array( 'password' => 'also-secret' ),
			)
		);

		self::assertNull( $repository->get( 'missing-audit' ) );
		self::assertTrue( $repository->add( $event ) );
		self::assertFalse( $repository->add( $event ) );
		$loaded = $repository->get( 'audit-one' );
		self::assertSame( '[redacted]', $loaded?->context()->to_array()['api_token'] );
		self::assertSame( '[redacted]', $loaded?->context()->to_array()['customer_email'] );
		self::assertSame( '[redacted]', $loaded?->context()->to_array()['nested']['password'] );
		$raw = (string) $wpdb->get_var( 'SELECT context_json FROM ' . Schema_Manager::table( 'audit_events' ) . " WHERE id = 'audit-one'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted table and fixed fixture.
		self::assertStringNotContainsString( 'never-store-me', $raw );
		self::assertStringNotContainsString( 'person@example.org', $raw );
	}

	/** Audit context is bounded, map-only, scalar-only, and finite. */
	public function test_audit_context_rejects_unsafe_or_oversized_values(): void {
		foreach (
			array(
				array( 'too_long' => str_repeat( 'x', 513 ) ),
				array( 'list' => array( 'not', 'a', 'map' ) ),
				array( 'object' => new \stdClass() ),
				array( 'number' => INF ),
			) as $context
		) {
			try {
				Audit_Context::from_array( $context );
				self::fail( 'Unsafe audit context must be rejected.' );
			} catch ( \InvalidArgumentException ) {
				self::addToAssertionCount( 1 );
			}
		}

		$this->expectException( \InvalidArgumentException::class );
		Audit_Context::from_array( array_fill_keys( array_map( static fn ( int $index ): string => 'field_' . $index, range( 1, 9 ) ), str_repeat( 'x', 500 ) ) );
	}

	/** Audit target history uses a bounded newest-first read. */
	public function test_audit_target_listing_is_bounded(): void {
		$repository = new Audit_Event_Repository();
		foreach ( array( 'audit-a', 'audit-b', 'audit-c' ) as $id ) {
			self::assertTrue( $repository->add( $this->audit_event( $id, array( 'safe' => true ) ) ) );
		}

		self::assertCount( 2, $repository->for_target( 'campaign', 'campaign-one', 2 ) );
	}

	/** Malformed/future structured rows fail closed and remain untouched. */
	public function test_malformed_repository_rows_return_null(): void {
		global $wpdb;
		$remote = new Remote_Campaign_Reference_Repository();
		self::assertTrue( $remote->add( $this->remote_reference( 'campaign-one', 'remote-1', 'draft' ) ) );
		$wpdb->update( Schema_Manager::table( 'remote_campaigns' ), array( 'data_version' => 99 ), array( 'campaign_id' => 'campaign-one', 'provider' => 'provider-one' ) );
		self::assertNull( $remote->get( 'campaign-one', 'provider-one' ) );

		$audit = new Audit_Event_Repository();
		self::assertTrue( $audit->add( $this->audit_event( 'audit-malformed', array( 'safe' => true ) ) ) );
		$wpdb->update( Schema_Manager::table( 'audit_events' ), array( 'context_json' => '{broken' ), array( 'id' => 'audit-malformed' ) );
		self::assertNull( $audit->get( 'audit-malformed' ) );
	}

	/** Create a campaign fixture. */
	private function campaign( string $id ): Campaign {
		return Campaign::create( $id, 7, 42, 'provider-one', 'audience-one', '2026-01-01T00:00:00Z' );
	}

	/** Create a normalized remote mapping fixture. */
	private function remote_reference( string $campaign_id, string $remote_id, string $state ): Remote_Campaign_Reference {
		return Remote_Campaign_Reference::from_array(
			array(
				'schema_version' => 1,
				'campaign_id'    => $campaign_id,
				'provider'       => 'provider-one',
				'remote_id'      => $remote_id,
				'observed_state' => $state,
				'cursor'         => null,
				'observed_at'    => '2026-01-01T00:00:00Z',
				'reconciled_at'  => null,
			)
		);
	}

	/** Create one provider-neutral attempt fixture. */
	private function attempt( string $id, string $status, string $retryability, ?string $correlation ): Delivery_Attempt {
		return Delivery_Attempt::from_array(
			array(
				'schema_version'     => 1,
				'id'                 => $id,
				'campaign_id'        => 'campaign-one',
				'operation'          => 'send',
				'idempotency_key'    => 'request-1',
				'status'             => $status,
				'retryability'       => $retryability,
				'remote_correlation' => $correlation,
				'created_at'         => '2026-01-01T00:00:00Z',
				'updated_at'         => '2026-01-01T00:01:00Z',
			)
		);
	}

	/** Create one append-only audit fixture. */
	private function audit_event( string $id, array $context ): Audit_Event {
		return Audit_Event::from_array(
			array(
				'schema_version' => 1,
				'id'             => $id,
				'actor_user_id'  => 7,
				'action'         => 'campaign_reviewed',
				'target_type'    => 'campaign',
				'target_id'      => 'campaign-one',
				'result'         => 'success',
				'context'        => $context,
				'created_at'     => '2026-01-01T00:00:00Z',
			)
		);
	}
}
