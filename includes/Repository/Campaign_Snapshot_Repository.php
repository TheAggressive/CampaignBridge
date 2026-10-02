<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database -- Typed port signatures document repository operations; this class is the authorized custom-table boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Direct access is confined to this repository implementation.
/**
 * Durable immutable campaign snapshot repository.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores exact M1 inputs/artifacts with insert-only revision identities. */
final class Campaign_Snapshot_Repository implements Campaign_Snapshot_Source {
	public function get( string $id ): ?Campaign_Snapshot {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'campaign_snapshots' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository detail lookup.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function add( Campaign_Snapshot $snapshot ): bool {
		if ( ! Schema_Manager::is_current() || ! Database_Values::campaign_exists( $snapshot->campaign_id() ) ) {
			return false;
		}

		global $wpdb;
		$artifact   = $snapshot->artifact();
		$envelope   = $snapshot->envelope();
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert-only repository write.
			Schema_Manager::table( 'campaign_snapshots' ),
			array(
				'id'                   => $snapshot->id(),
				'data_version'         => $snapshot->to_array()['schema_version'],
				'campaign_id'          => $snapshot->campaign_id(),
				'revision'             => $snapshot->revision(),
				'review_input'         => Database_Values::encode_json( $snapshot->review_input()->to_array() ),
				'artifact_html'        => $artifact->html(),
				'artifact_text'        => $artifact->text(),
				'assets_json'          => Database_Values::encode_json( $artifact->assets() ),
				'artifact_fingerprint' => $artifact->fingerprint(),
				'compiler_version'     => $artifact->compiler_version(),
				'profile_version'      => $artifact->profile_version(),
				'created_at'           => Database_Values::to_database_time( $snapshot->created_at() ),
				'envelope'             => null === $envelope ? null : Database_Values::encode_json( $envelope->to_array() ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppressed );
		return false !== $inserted;
	}

	/** @return array<int, Campaign_Snapshot> */
	public function for_campaign( string $campaign_id, int $limit = 50 ): array {
		if ( ! Schema_Manager::is_current() ) {
			return array();
		}
		global $wpdb;
		$table = Schema_Manager::table( 'campaign_snapshots' );
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded repository listing.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE campaign_id = %s ORDER BY revision DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
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
	private function hydrate( array $row ): ?Campaign_Snapshot {
		try {
			$review = Database_Values::decode_json( $row['review_input'] ?? null );
			$assets = Database_Values::decode_json( $row['assets_json'] ?? null );
			$stored = $row['envelope'] ?? null;
			$data   = array(
				'schema_version' => Database_Values::integer( $row['data_version'] ?? null, 'Snapshot data version' ),
				'id'             => $row['id'] ?? null,
				'campaign_id'    => $row['campaign_id'] ?? null,
				'revision'       => Database_Values::integer( $row['revision'] ?? null, 'Snapshot revision' ),
				'review_input'   => $review,
				'artifact'       => array(
					'schema_version'   => 1,
					'html'             => $row['artifact_html'] ?? null,
					'text'             => $row['artifact_text'] ?? null,
					'assets'           => $assets,
					'fingerprint'      => $row['artifact_fingerprint'] ?? null,
					'compiler_version' => $row['compiler_version'] ?? null,
					'profile_version'  => $row['profile_version'] ?? null,
				),
				'created_at'     => Database_Values::from_database_time( $row['created_at'] ?? null ),
			);
			if ( null !== $stored && '' !== $stored ) {
				$data['envelope'] = Database_Values::decode_json( $stored );
			}

			return Campaign_Snapshot::from_array( $data );
		} catch ( \InvalidArgumentException | \JsonException ) {
			return null;
		}
	}
}
