<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database -- Typed port signatures document repository operations; this class is the authorized custom-table boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Direct access is confined to this repository implementation.
/**
 * Append-only audit event repository.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Persists only bounded context already normalized by the domain. */
final class Audit_Event_Repository implements Audit_Event_Source {
	public function get( string $id ): ?Audit_Event {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'audit_events' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository detail lookup.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function add( Audit_Event $event ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		global $wpdb;
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Append-only repository write.
			Schema_Manager::table( 'audit_events' ),
			array(
				'id'            => $event->id(),
				'data_version'  => Audit_Event::SCHEMA_VERSION,
				'actor_user_id' => $event->actor_user_id(),
				'action'        => $event->action(),
				'target_type'   => $event->target_type(),
				'target_id'     => $event->target_id(),
				'result'        => $event->result(),
				'context_json'  => Database_Values::encode_json( $event->context()->to_array() ),
				'created_at'    => Database_Values::to_database_time( $event->created_at() ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppressed );
		return false !== $inserted;
	}

	/** @return array<int, Audit_Event> */
	public function for_target( string $target_type, string $target_id, int $limit = 100, int $offset = 0 ): array {
		if ( ! Schema_Manager::is_current() ) {
			return array();
		}
		global $wpdb;
		$table  = Schema_Manager::table( 'audit_events' );
		$limit  = max( 1, min( 100, $limit ) );
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded audit listing.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE target_type = %s AND target_id = %s ORDER BY created_at DESC, sequence_number DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$target_type,
				$target_id,
				$limit,
				max( 0, $offset )
			),
			ARRAY_A
		);
		$events = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$event = $this->hydrate( $row );
			if ( null !== $event ) {
				$events[] = $event;
			}
		}
		return $events;
	}

	public function count_for_target( string $target_type, string $target_id ): int {
		if ( ! Schema_Manager::is_current() ) {
			return 0;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'audit_events' );
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed audit count.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE target_type = %s AND target_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$target_type,
				$target_id
			)
		);
	}

	/** @param array<string, mixed> $row Database row. */
	private function hydrate( array $row ): ?Audit_Event {
		try {
			return Audit_Event::from_array(
				array(
					'schema_version' => Database_Values::integer( $row['data_version'] ?? null, 'Audit data version' ),
					'id'             => $row['id'] ?? null,
					'actor_user_id'  => Database_Values::integer( $row['actor_user_id'] ?? null, 'Audit actor', true ),
					'action'         => $row['action'] ?? null,
					'target_type'    => $row['target_type'] ?? null,
					'target_id'      => $row['target_id'] ?? null,
					'result'         => $row['result'] ?? null,
					'context'        => Database_Values::decode_json( $row['context_json'] ?? null ),
					'created_at'     => Database_Values::from_database_time( $row['created_at'] ?? null ),
				)
			);
		} catch ( \InvalidArgumentException | \JsonException ) {
			return null;
		}
	}
}
