<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database -- Typed port signatures document repository operations; this class is the authorized custom-table boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Direct access is confined to this repository implementation.
/**
 * Durable campaign repository.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_List_Filter;
use CampaignBridge\Domain\Campaign\Campaign_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Persists provider-neutral campaigns with optimistic concurrency. */
final class Campaign_Repository implements Campaign_Source {
	public function get( string $id ): ?Campaign {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}

		global $wpdb;
		$table = Schema_Manager::table( 'campaigns' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository detail lookup.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table comes from the Schema_Manager allowlist.
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function add( Campaign $campaign ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		if ( null !== $campaign->active_snapshot_id() && ! Database_Values::snapshot_belongs_to_campaign( $campaign->active_snapshot_id(), $campaign->id() ) ) {
			return false;
		}

		global $wpdb;
		$data       = $campaign->to_array();
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository write.
			Schema_Manager::table( 'campaigns' ),
			array(
				'id'                  => $campaign->id(),
				'data_version'        => Campaign::SCHEMA_VERSION,
				'state'               => $campaign->state(),
				'version'             => $campaign->version(),
				'owner_user_id'       => $campaign->owner_user_id(),
				'template_id'         => $campaign->template_id(),
				'provider'            => $data['provider'],
				'audience_reference'  => $data['audience_reference'],
				'active_snapshot_id'  => $data['active_snapshot_id'],
				'created_at'          => Database_Values::to_database_time( $campaign->created_at() ),
				'updated_at'          => Database_Values::to_database_time( $campaign->updated_at() ),
				'scheduled_for'       => self::database_time( $campaign->scheduled_for() ),
				'approved_by_user_id' => $campaign->approved_by_user_id(),
			),
			array( '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		$wpdb->suppress_errors( $suppressed );
		return false !== $inserted;
	}

	public function compare_and_swap( Campaign $replacement, int $expected_version ): bool {
		if ( ! Schema_Manager::is_current() || $replacement->version() !== $expected_version + 1 ) {
			return false;
		}
		if ( null !== $replacement->active_snapshot_id() && ! Database_Values::snapshot_belongs_to_campaign( $replacement->active_snapshot_id(), $replacement->id() ) ) {
			return false;
		}

		global $wpdb;
		$data    = $replacement->to_array();
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Version-guarded repository write.
			Schema_Manager::table( 'campaigns' ),
			array(
				'data_version'        => Campaign::SCHEMA_VERSION,
				'state'               => $replacement->state(),
				'version'             => $replacement->version(),
				'owner_user_id'       => $replacement->owner_user_id(),
				'template_id'         => $replacement->template_id(),
				'provider'            => $data['provider'],
				'audience_reference'  => $data['audience_reference'],
				'active_snapshot_id'  => $data['active_snapshot_id'],
				'updated_at'          => Database_Values::to_database_time( $replacement->updated_at() ),
				'scheduled_for'       => self::database_time( $replacement->scheduled_for() ),
				'approved_by_user_id' => $replacement->approved_by_user_id(),
			),
			array(
				'id'         => $replacement->id(),
				'version'    => $expected_version,
				'created_at' => Database_Values::to_database_time( $replacement->created_at() ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%s', '%d', '%s' )
		);

		return 1 === $updated;
	}

	/** @return array<int, Campaign> */
	public function for_owner( int $owner_user_id, int $limit = 50, int $offset = 0, ?Campaign_List_Filter $filter = null ): array {
		if ( ! Schema_Manager::is_current() || 1 > $owner_user_id ) {
			return array();
		}

		global $wpdb;
		$table                  = Schema_Manager::table( 'campaigns' );
		$limit                  = max( 1, min( 100, $limit ) );
		$offset                 = max( 0, $offset );
		list( $where, $values ) = self::owner_filter( $owner_user_id, $filter );
		$rows                   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded repository listing.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- owner_filter() returns one value per placeholder in its clause.
				"SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC, id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table comes from the Schema_Manager allowlist; the clause holds only placeholders.
				...array_merge( $values, array( $limit, $offset ) )
			),
			ARRAY_A
		);

		return $this->hydrate_many( is_array( $rows ) ? $rows : array() );
	}

	public function count_for_owner( int $owner_user_id, ?Campaign_List_Filter $filter = null ): int {
		if ( ! Schema_Manager::is_current() || 1 > $owner_user_id ) {
			return 0;
		}

		global $wpdb;
		$table                  = Schema_Manager::table( 'campaigns' );
		list( $where, $values ) = self::owner_filter( $owner_user_id, $filter );
		$count                  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed owner count for REST pagination.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table comes from the Schema_Manager allowlist; owner_filter() returns one value per placeholder in its clause.
		);

		return max( 0, (int) $count );
	}

	/**
	 * WHERE clause and values for one owner's filtered collection.
	 *
	 * The clause contains only fixed SQL and placeholders; every value is bound.
	 *
	 * @param int                       $owner_user_id Owner.
	 * @param Campaign_List_Filter|null $filter        Optional narrowing.
	 * @return array{0: string, 1: array<int, int|string>}
	 */
	private static function owner_filter( int $owner_user_id, ?Campaign_List_Filter $filter ): array {
		$where  = 'owner_user_id = %d';
		$values = array( $owner_user_id );
		$states = null === $filter ? array() : $filter->states();
		if ( array() !== $states ) {
			$where .= ' AND state IN (' . implode( ', ', array_fill( 0, count( $states ), '%s' ) ) . ')';
			$values = array_merge( $values, $states );
		}
		$provider = $filter?->provider();
		if ( Campaign_List_Filter::NO_PROVIDER === $provider ) {
			$where .= ' AND provider IS NULL';
		} elseif ( null !== $provider ) {
			$where   .= ' AND provider = %s';
			$values[] = $provider;
		}

		return array( $where, $values );
	}

	private static function database_time( ?string $timestamp ): ?string {
		return null === $timestamp ? null : Database_Values::to_database_time( $timestamp );
	}

	/** @param array<string, mixed> $row Database row. */
	private function hydrate( array $row ): ?Campaign {
		try {
			return Campaign::from_array(
				array(
					'schema_version'      => Database_Values::integer( $row['data_version'] ?? null, 'Campaign data version' ),
					'id'                  => $row['id'] ?? null,
					'state'               => $row['state'] ?? null,
					'version'             => Database_Values::integer( $row['version'] ?? null, 'Campaign version' ),
					'owner_user_id'       => Database_Values::integer( $row['owner_user_id'] ?? null, 'Campaign owner' ),
					'template_id'         => Database_Values::integer( $row['template_id'] ?? null, 'Campaign template' ),
					'provider'            => $row['provider'] ?? null,
					'audience_reference'  => $row['audience_reference'] ?? null,
					'active_snapshot_id'  => $row['active_snapshot_id'] ?? null,
					'created_at'          => Database_Values::from_database_time( $row['created_at'] ?? null ),
					'updated_at'          => Database_Values::from_database_time( $row['updated_at'] ?? null ),
					'scheduled_for'       => null === ( $row['scheduled_for'] ?? null ) ? null : Database_Values::from_database_time( $row['scheduled_for'] ),
					'approved_by_user_id' => null === ( $row['approved_by_user_id'] ?? null ) ? null : Database_Values::integer( $row['approved_by_user_id'], 'Campaign approver' ),
				)
			);
		} catch ( \InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Database rows.
	 * @return array<int, Campaign>
	 */
	private function hydrate_many( array $rows ): array {
		$records = array();
		foreach ( $rows as $row ) {
			$record = $this->hydrate( $row );
			if ( null !== $record ) {
				$records[] = $record;
			}
		}
		return $records;
	}
}
