<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database -- Port methods are documented by Job_Source; this class is the authorized custom-table boundary for jobs.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Repository-owned persistence boundary.
/**
 * Durable job storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_Claim;
use CampaignBridge\Domain\Job\Job_Source;
use CampaignBridge\Domain\Job\Job_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores jobs in a site-local table and lets the database decide who holds them.
 *
 * Every claim and every later transition is one conditional UPDATE whose
 * predicate restates what must still be true (the state, the expired lease,
 * the owner), so of two workers racing for the same row the engine admits
 * exactly one. A dedupe key is held only while a job is active, so the same
 * work cannot be queued twice but can be queued again once it has finished.
 */
final class Job_Repository implements Job_Source {
	public function add( Job $job ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		global $wpdb;
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository write; the dedupe key's unique index arbitrates duplicates.
			Schema_Manager::table( 'jobs' ),
			array(
				'id'           => $job->id(),
				'data_version' => Job::SCHEMA_VERSION,
				'job_type'     => $job->type(),
				'target_type'  => $job->target_type(),
				'target_id'    => $job->target_id(),
				'dedupe_key'   => $job->dedupe_key(),
				'payload_json' => array() === $job->payload() ? '{}' : Database_Values::encode_json( $job->payload() ),
				'state'        => $job->state(),
				'attempts'     => $job->attempts(),
				'max_attempts' => $job->max_attempts(),
				'run_after'    => Database_Values::to_database_time( $job->run_after() ),
				'created_at'   => Database_Values::to_database_time( $job->created_at() ),
				'updated_at'   => Database_Values::to_database_time( $job->updated_at() ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppressed );

		return 1 === $inserted;
	}

	public function get( string $id ): ?Job {
		return $this->one( 'id', $id );
	}

	public function find_active( string $dedupe_key ): ?Job {
		return $this->one( 'dedupe_key', $dedupe_key );
	}

	public function claim( string $owner, string $now, string $lease_until, int $limit ): array {
		if ( ! Schema_Manager::is_current() || $limit < 1 ) {
			return array();
		}
		$now_db   = Database_Values::to_database_time( $now );
		$lease_db = Database_Values::to_database_time( $lease_until );
		$claims   = array();

		// A worker stopped while holding these: take them over first.
		$expired = "state = 'claimed' AND lease_expires_at < %s AND attempts < max_attempts";
		foreach ( $this->candidates( $expired, $now_db, 'lease_expires_at', $limit ) as $id ) {
			if ( $this->take( $id, $expired, $now_db, $owner, $lease_db, true ) ) {
				$claims[] = $this->claimed( $id, true, false );
			}
		}
		// Stopped on their last attempt: claimed only so the runner can end them.
		$exhausted = "state = 'claimed' AND lease_expires_at < %s AND attempts >= max_attempts";
		foreach ( $this->candidates( $exhausted, $now_db, 'lease_expires_at', $limit - count( $claims ) ) as $id ) {
			if ( $this->take( $id, $exhausted, $now_db, $owner, $lease_db, false ) ) {
				$claims[] = $this->claimed( $id, true, true );
			}
		}
		$due = "state = 'queued' AND run_after <= %s AND attempts < max_attempts";
		foreach ( $this->candidates( $due, $now_db, 'run_after', $limit - count( $claims ) ) as $id ) {
			if ( $this->take( $id, $due, $now_db, $owner, $lease_db, true ) ) {
				$claims[] = $this->claimed( $id, false, false );
			}
		}

		return array_values( array_filter( $claims ) );
	}

	public function heartbeat( string $id, string $owner, string $lease_until, string $now ): bool {
		return $this->transition(
			$id,
			$owner,
			'lease_expires_at = %s, updated_at = %s',
			array( Database_Values::to_database_time( $lease_until ), Database_Values::to_database_time( $now ) )
		);
	}

	public function succeed( string $id, string $owner, string $now ): bool {
		return $this->transition(
			$id,
			$owner,
			"state = 'succeeded', lease_owner = NULL, lease_expires_at = NULL, dedupe_key = NULL, last_error = NULL, updated_at = %s",
			array( Database_Values::to_database_time( $now ) )
		);
	}

	public function retry( string $id, string $owner, string $run_after, string $error, string $now ): bool {
		return $this->transition(
			$id,
			$owner,
			"state = 'queued', run_after = %s, lease_owner = NULL, lease_expires_at = NULL, last_error = %s, updated_at = %s",
			array( Database_Values::to_database_time( $run_after ), $error, Database_Values::to_database_time( $now ) )
		);
	}

	public function finish( string $id, string $owner, string $state, string $error, string $now ): bool {
		if ( ! in_array( $state, array( Job_State::FAILED, Job_State::DEAD ), true ) ) {
			return false;
		}

		return $this->transition(
			$id,
			$owner,
			'state = %s, lease_owner = NULL, lease_expires_at = NULL, dedupe_key = NULL, last_error = %s, updated_at = %s',
			array( $state, $error, Database_Values::to_database_time( $now ) )
		);
	}

	public function counts_by_state(): array {
		if ( ! Schema_Manager::is_current() ) {
			return array();
		}
		global $wpdb;
		$table  = Schema_Manager::table( 'jobs' );
		$rows   = $wpdb->get_results( "SELECT state, COUNT(*) AS total FROM {$table} GROUP BY state", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table and no input; bounded by the closed state vocabulary.
		$counts = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_string( $row['state'] ?? null ) && in_array( $row['state'], Job_State::all(), true ) ) {
				$counts[ $row['state'] ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	public function stalled( string $overdue_before, string $now ): array {
		$none = array(
			'overdue'        => 0,
			'expired_leases' => 0,
			'oldest_overdue' => null,
		);
		if ( ! Schema_Manager::is_current() ) {
			return $none;
		}
		global $wpdb;
		$table   = Schema_Manager::table( 'jobs' );
		$overdue = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed health read.
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, MIN(run_after) AS oldest FROM {$table} WHERE state = 'queued' AND run_after < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				Database_Values::to_database_time( $overdue_before )
			),
			ARRAY_A
		);
		$expired = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed health read.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = 'claimed' AND lease_expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				Database_Values::to_database_time( $now )
			)
		);
		$oldest  = is_array( $overdue ) && is_string( $overdue['oldest'] ?? null ) ? $overdue['oldest'] : null;

		return array(
			'overdue'        => is_array( $overdue ) ? (int) ( $overdue['total'] ?? 0 ) : 0,
			'expired_leases' => (int) $expired,
			'oldest_overdue' => null === $oldest ? null : Database_Values::from_database_time( $oldest ),
		);
	}

	public function purge_succeeded( string $before, int $limit ): int {
		if ( ! Schema_Manager::is_current() || $limit < 1 ) {
			return 0;
		}
		global $wpdb;
		$table   = Schema_Manager::table( 'jobs' );
		$ids     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded cleanup selection.
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE state = 'succeeded' AND updated_at < %s ORDER BY updated_at LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				Database_Values::to_database_time( $before ),
				$limit
			)
		);
		$deleted = 0;
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$deleted += (int) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded cleanup of finished rows.
				$table,
				array(
					'id'    => (string) $id,
					'state' => Job_State::SUCCEEDED,
				),
				array( '%s', '%s' ) 
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded cleanup of finished rows.
		}

		return $deleted;
	}

	/**
	 * IDs matching a predicate, in order, at most `$limit`.
	 *
	 * @return array<int, string>
	 */
	private function candidates( string $predicate, string $now_db, string $order, int $limit ): array {
		if ( $limit < 1 ) {
			return array();
		}
		global $wpdb;
		$table = Schema_Manager::table( 'jobs' );
		$ids   = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded indexed claim candidates.
			$wpdb->prepare( "SELECT id FROM {$table} WHERE {$predicate} ORDER BY {$order}, id LIMIT %d", $now_db, $limit ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Allowlisted table; predicates and order columns are class constants, and each predicate holds one %s for $now_db.
		);

		return array_map( 'strval', is_array( $ids ) ? $ids : array() );
	}

	/** Claim one row while the predicate that selected it still holds. */
	private function take( string $id, string $predicate, string $now_db, string $owner, string $lease_db, bool $count_attempt ): bool {
		global $wpdb;
		$table    = Schema_Manager::table( 'jobs' );
		$attempts = $count_attempt ? ', attempts = attempts + 1' : '';
		$updated  = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic conditional claim.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The predicate constant holds one %s for $now_db.
				"UPDATE {$table} SET state = 'claimed', lease_owner = %s, lease_expires_at = %s, updated_at = %s{$attempts} WHERE id = %s AND {$predicate}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table; predicate and attempt clause are class constants.
				$owner,
				$lease_db,
				$now_db,
				$id,
				$now_db
			)
		);

		return 1 === (int) $updated;
	}

	private function claimed( string $id, bool $taken_over, bool $exhausted ): ?Job_Claim {
		$job = $this->get( $id );

		return null === $job ? null : new Job_Claim( $job, $taken_over, $exhausted );
	}

	/**
	 * Apply one update to a job the owner still holds.
	 *
	 * @param array<int, string> $values Values for the placeholders in `$set`.
	 */
	private function transition( string $id, string $owner, string $set, array $values ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		global $wpdb;
		$table   = Schema_Manager::table( 'jobs' );
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owner-checked conditional transition.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The SET clause's placeholders are supplied by $values.
				"UPDATE {$table} SET {$set} WHERE id = %s AND state = 'claimed' AND lease_owner = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table; the SET clause is a class constant.
				...array_merge( $values, array( $id, $owner ) )
			)
		);

		return 1 === (int) $updated;
	}

	private function one( string $column, string $value ): ?Job {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'jobs' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed single-row lookup.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %s LIMIT 1", $value ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table; the column is one of two class literals.
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/** @param array<string, mixed> $row Database row. */
	private function hydrate( array $row ): ?Job {
		try {
			$payload = Database_Values::decode_json( $row['payload_json'] ?? null );

			return Job::from_array(
				array(
					'schema_version'   => Database_Values::integer( $row['data_version'] ?? null, 'Job data version' ),
					'id'               => $row['id'] ?? null,
					'type'             => $row['job_type'] ?? null,
					'target_type'      => $row['target_type'] ?? null,
					'target_id'        => $row['target_id'] ?? null,
					'dedupe_key'       => $row['dedupe_key'] ?? null,
					'payload'          => $payload,
					'state'            => $row['state'] ?? null,
					'attempts'         => Database_Values::integer( $row['attempts'] ?? null, 'Job attempts' ),
					'max_attempts'     => Database_Values::integer( $row['max_attempts'] ?? null, 'Job maximum attempts' ),
					'run_after'        => Database_Values::from_database_time( $row['run_after'] ?? null ),
					'lease_owner'      => $row['lease_owner'] ?? null,
					'lease_expires_at' => null === ( $row['lease_expires_at'] ?? null ) ? null : Database_Values::from_database_time( $row['lease_expires_at'] ),
					'last_error'       => $row['last_error'] ?? null,
					'created_at'       => Database_Values::from_database_time( $row['created_at'] ?? null ),
					'updated_at'       => Database_Values::from_database_time( $row['updated_at'] ?? null ),
				)
			);
		} catch ( \InvalidArgumentException | \JsonException ) {
			return null;
		}
	}
}
