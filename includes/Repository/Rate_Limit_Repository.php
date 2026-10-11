<?php // phpcs:disable CampaignBridge.Standard.Sniffs.Database -- Typed private helpers document themselves; this class is the authorized custom-table boundary for request counters.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Direct access is confined to this repository implementation.
/**
 * Atomic fixed-window request counters.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts requests per scope in fixed windows without a read-modify-write race.
 *
 * Admission is decided by the database in one conditional statement,
 * `UPDATE … SET hits = hits + 1 WHERE … AND hits < maximum`, so concurrent
 * requests can never be admitted beyond the maximum: the engine serializes
 * writers to the row and re-checks the predicate for each one. The first
 * request of a window creates its row through the primary key, so two
 * requests racing to open the same window cannot both start a fresh count.
 *
 * Counters live in a site-local table, so multisite sites never share them,
 * and they do not depend on a persistent object cache being present.
 */
final class Rate_Limit_Repository {
	/**
	 * Claim one request for a scope in the current window.
	 *
	 * @param string $scope   Caller-built scope, such as an endpoint and user.
	 * @param int    $maximum Requests allowed per window.
	 * @param int    $window  Window length in seconds.
	 * @param int    $now     Current Unix time.
	 * @return bool|null True when admitted, false when the window is full, or
	 *                   null when the counter cannot be established.
	 */
	public function claim( string $scope, int $maximum, int $window, int $now ): ?bool {
		if ( $maximum < 1 || $window < 1 || ! Schema_Manager::is_current() ) {
			return null;
		}

		global $wpdb;
		$table  = Schema_Manager::table( 'rate_limits' );
		$bucket = intdiv( $now, $window );
		$key    = hash( 'sha256', $scope . '|' . $window . '|' . $bucket );

		$admitted = $this->increment( $table, $key, $maximum );
		if ( false !== $admitted ) {
			return $admitted;
		}

		// No row admitted this request: the window is new, or it is full.
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository write; the primary key arbitrates concurrent window creation.
			$table,
			array(
				'limit_key'  => $key,
				'hits'       => 1,
				'expires_at' => ( $bucket + 1 ) * $window,
			),
			array( '%s', '%d', '%d' )
		);
		$wpdb->suppress_errors( $suppressed );
		if ( 1 === $inserted ) {
			$this->purge_expired( $table, $now );
			return true;
		}

		// The row exists, because it is full or another request created it first.
		return $this->increment( $table, $key, $maximum );
	}

	/**
	 * Admit one request against an existing row.
	 *
	 * @param string $table   Allowlisted table name.
	 * @param string $key     Hashed window key.
	 * @param int    $maximum Maximum length in bytes.
	 * @return bool|null True when admitted, false when no row admitted it, null on a database error.
	 */
	private function increment( string $table, string $key, int $maximum ): ?bool {
		global $wpdb;
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic conditional counter update.
			$wpdb->prepare(
				"UPDATE {$table} SET hits = hits + 1 WHERE limit_key = %s AND hits < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
				$key,
				$maximum
			)
		);
		if ( false === $updated ) {
			return null;
		}

		return 1 === (int) $updated;
	}

	/**
	 * Remove windows that have ended; runs only when a new window opens.
	 *
	 * @param string $table Allowlisted table name.
	 * @param int    $now   Current UTC timestamp.
	 */
	private function purge_expired( string $table, int $now ): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded expiry cleanup by indexed column.
			$wpdb->prepare( "DELETE FROM {$table} WHERE expires_at <= %d", $now ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
		);
	}
}
