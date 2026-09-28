<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database -- Typed port signatures document repository operations; this class is the authorized custom-table boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Direct access is confined to this repository implementation.
/**
 * Durable delivery attempt repository.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Persists idempotency identities and known/unknown remote outcomes. */
final class Delivery_Attempt_Repository implements Delivery_Attempt_Source {
	public function get( string $id ): ?Delivery_Attempt {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'delivery_attempts' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository detail lookup.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function find_idempotency( string $campaign_id, string $operation, string $idempotency_key ): ?Delivery_Attempt {
		if ( ! Schema_Manager::is_current() || '' === $idempotency_key ) {
			return null;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'delivery_attempts' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotency lookup.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE campaign_id = %s AND operation = %s AND idempotency_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$campaign_id,
				$operation,
				$idempotency_key
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function add( Delivery_Attempt $attempt ): bool {
		if ( ! Schema_Manager::is_current() || ! Database_Values::campaign_exists( $attempt->campaign_id() ) ) {
			return false;
		}
		global $wpdb;
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository write.
			Schema_Manager::table( 'delivery_attempts' ),
			array(
				'id'                 => $attempt->id(),
				'data_version'       => Delivery_Attempt::SCHEMA_VERSION,
				'campaign_id'        => $attempt->campaign_id(),
				'operation'          => $attempt->operation(),
				'idempotency_key'    => $attempt->idempotency_key(),
				'status'             => $attempt->status(),
				'retryability'       => $attempt->retryability(),
				'remote_correlation' => $attempt->remote_correlation(),
				'created_at'         => Database_Values::to_database_time( $attempt->created_at() ),
				'updated_at'         => Database_Values::to_database_time( $attempt->updated_at() ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppressed );
		return false !== $inserted;
	}

	public function update_result( Delivery_Attempt $attempt ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		$current = $this->get( $attempt->id() );
		if (
			null === $current
			|| $current->campaign_id() !== $attempt->campaign_id()
			|| $current->operation() !== $attempt->operation()
			|| $current->idempotency_key() !== $attempt->idempotency_key()
			|| $current->created_at() !== $attempt->created_at()
		) {
			return false;
		}
		if ( $current->to_array() === $attempt->to_array() ) {
			return true;
		}

		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identity-guarded repository write.
			Schema_Manager::table( 'delivery_attempts' ),
			array(
				'data_version'       => Delivery_Attempt::SCHEMA_VERSION,
				'status'             => $attempt->status(),
				'retryability'       => $attempt->retryability(),
				'remote_correlation' => $attempt->remote_correlation(),
				'updated_at'         => Database_Values::to_database_time( $attempt->updated_at() ),
			),
			array( 'id' => $attempt->id() ),
			array( '%d', '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);
		return 1 === $updated;
	}

	/** @return array<int, Delivery_Attempt> */
	public function for_campaign( string $campaign_id, int $limit = 50 ): array {
		if ( ! Schema_Manager::is_current() ) {
			return array();
		}
		global $wpdb;
		$table   = Schema_Manager::table( 'delivery_attempts' );
		$limit   = max( 1, min( 100, $limit ) );
		$rows    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded repository listing.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE campaign_id = %s ORDER BY created_at DESC, id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$campaign_id,
				$limit
			),
			ARRAY_A
		);
		$records = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$record = $this->hydrate( $row );
			if ( null !== $record ) {
				$records[] = $record;
			}
		}
		return $records;
	}

	/** @param array<string, mixed> $row Database row. */
	private function hydrate( array $row ): ?Delivery_Attempt {
		try {
			return Delivery_Attempt::from_array(
				array(
					'schema_version'     => Database_Values::integer( $row['data_version'] ?? null, 'Attempt data version' ),
					'id'                 => $row['id'] ?? null,
					'campaign_id'        => $row['campaign_id'] ?? null,
					'operation'          => $row['operation'] ?? null,
					'idempotency_key'    => $row['idempotency_key'] ?? null,
					'status'             => $row['status'] ?? null,
					'retryability'       => $row['retryability'] ?? null,
					'remote_correlation' => $row['remote_correlation'] ?? null,
					'created_at'         => Database_Values::from_database_time( $row['created_at'] ?? null ),
					'updated_at'         => Database_Values::from_database_time( $row['updated_at'] ?? null ),
				)
			);
		} catch ( \InvalidArgumentException ) {
			return null;
		}
	}
}
