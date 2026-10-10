<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Port methods are documented by Lock_Source.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Repository-owned persistence boundary.
/**
 * Expiring named locks.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Lock\Lock_Acquisition;
use CampaignBridge\Domain\Lock\Lock_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locks in a site-local table, arbitrated by the database.
 *
 * Acquiring inserts the name, so the primary key admits one owner. Taking
 * over an expired lock is one conditional update that re-checks the expiry,
 * so of two takers only one succeeds. Release deletes only the caller's own
 * row, so a holder that ran past its expiry cannot free a lock someone else
 * has since taken.
 */
final class Lock_Repository implements Lock_Source {
	public function acquire( string $name, string $owner, string $purpose, string $until, string $now ): Lock_Acquisition {
		if ( ! Schema_Manager::is_current() ) {
			return new Lock_Acquisition( false );
		}
		global $wpdb;
		$table    = Schema_Manager::table( 'locks' );
		$now_db   = Database_Values::to_database_time( $now );
		$until_db = Database_Values::to_database_time( $until );

		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The primary key arbitrates concurrent acquisition.
			$table,
			array(
				'lock_name'   => $name,
				'owner'       => $owner,
				'purpose'     => $purpose,
				'acquired_at' => $now_db,
				'expires_at'  => $until_db,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppressed );
		if ( 1 === $inserted ) {
			return new Lock_Acquisition( true );
		}

		$held = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Primary-key read of the current holder.
			$wpdb->prepare( "SELECT purpose, expires_at FROM {$table} WHERE lock_name = %s", $name ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
			ARRAY_A
		);
		if ( ! is_array( $held ) ) {
			return new Lock_Acquisition( false );
		}
		$taken = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic takeover of an expired lock.
			$wpdb->prepare(
				"UPDATE {$table} SET owner = %s, purpose = %s, acquired_at = %s, expires_at = %s WHERE lock_name = %s AND expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$owner,
				$purpose,
				$now_db,
				$until_db,
				$name,
				$now_db
			)
		);
		if ( 1 === (int) $taken ) {
			return new Lock_Acquisition( true, is_string( $held['purpose'] ?? null ) ? $held['purpose'] : null );
		}

		$expires = is_string( $held['expires_at'] ?? null ) ? $held['expires_at'] : null;

		return new Lock_Acquisition( false, null, null === $expires ? null : Database_Values::from_database_time( $expires ) );
	}

	public function release( string $name, string $owner ): bool {
		if ( ! Schema_Manager::is_current() ) {
			return false;
		}
		global $wpdb;

		return 1 === (int) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owner-checked release.
			Schema_Manager::table( 'locks' ),
			array(
				'lock_name' => $name,
				'owner'     => $owner,
			),
			array( '%s', '%s' )
		);
	}
}
