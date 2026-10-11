<?php // phpcs:disable CampaignBridge.Standard.Sniffs.Database -- Typed port signatures document repository operations; this class is the authorized custom-table boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Direct access is confined to this repository implementation.
/**
 * Normalized remote campaign reference repository.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Enforces one local/provider and one provider/remote identity mapping. */
final class Remote_Campaign_Reference_Repository implements Remote_Campaign_Reference_Source {
	/**
	 * {@inheritDoc}
	 */
	public function get( string $campaign_id, string $provider ): ?Remote_Campaign_Reference {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}

		global $wpdb;
		$table = Schema_Manager::table( 'remote_campaigns' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository identity lookup.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE campaign_id = %s AND provider = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$campaign_id,
				$provider
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_remote( string $provider, string $remote_id ): ?Remote_Campaign_Reference {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}

		global $wpdb;
		$table = Schema_Manager::table( 'remote_campaigns' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository reverse-identity lookup.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE provider = %s AND remote_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$provider,
				$remote_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function add( Remote_Campaign_Reference $reference ): bool {
		if ( ! Schema_Manager::is_current() || ! Database_Values::campaign_exists( $reference->campaign_id() ) ) {
			return false;
		}

		global $wpdb;
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository write.
			Schema_Manager::table( 'remote_campaigns' ),
			array(
				'campaign_id'     => $reference->campaign_id(),
				'provider'        => $reference->provider(),
				'data_version'    => Remote_Campaign_Reference::SCHEMA_VERSION,
				'remote_id'       => $reference->remote_id(),
				'observed_state'  => $reference->observed_state(),
				'provider_cursor' => $reference->cursor(),
				'observed_at'     => Database_Values::to_database_time( $reference->observed_at() ),
				'reconciled_at'   => null === $reference->reconciled_at() ? null : Database_Values::to_database_time( $reference->reconciled_at() ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppressed );
		return false !== $inserted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_observation( Remote_Campaign_Reference $reference ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		$current = $this->get( $reference->campaign_id(), $reference->provider() );
		if ( null === $current || $current->remote_id() !== $reference->remote_id() ) {
			return false;
		}
		if ( $current->to_array() === $reference->to_array() ) {
			return true;
		}

		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identity-guarded repository write.
			Schema_Manager::table( 'remote_campaigns' ),
			array(
				'data_version'    => Remote_Campaign_Reference::SCHEMA_VERSION,
				'observed_state'  => $reference->observed_state(),
				'provider_cursor' => $reference->cursor(),
				'observed_at'     => Database_Values::to_database_time( $reference->observed_at() ),
				'reconciled_at'   => null === $reference->reconciled_at() ? null : Database_Values::to_database_time( $reference->reconciled_at() ),
			),
			array(
				'campaign_id' => $reference->campaign_id(),
				'provider'    => $reference->provider(),
				'remote_id'   => $reference->remote_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s' ),
			array( '%s', '%s', '%s' )
		);
		return 1 === $updated;
	}

	/**
	 * Rebuild a reference from a database row, or null when the row is malformed.
	 *
	 * @param array<string, mixed> $row Database row.
	 */
	private function hydrate( array $row ): ?Remote_Campaign_Reference {
		try {
			return Remote_Campaign_Reference::from_array(
				array(
					'schema_version' => Database_Values::integer( $row['data_version'] ?? null, 'Remote-reference data version' ),
					'campaign_id'    => $row['campaign_id'] ?? null,
					'provider'       => $row['provider'] ?? null,
					'remote_id'      => $row['remote_id'] ?? null,
					'observed_state' => $row['observed_state'] ?? null,
					'cursor'         => $row['provider_cursor'] ?? null,
					'observed_at'    => Database_Values::from_database_time( $row['observed_at'] ?? null ),
					'reconciled_at'  => null === ( $row['reconciled_at'] ?? null ) ? null : Database_Values::from_database_time( $row['reconciled_at'] ),
				)
			);
		} catch ( \InvalidArgumentException ) {
			return null;
		}
	}
}
