<?php // phpcs:disable CampaignBridge.Standard.Sniffs.Database -- Port methods are documented by Webhook_Receipt_Source; this class is the authorized custom-table boundary for webhook receipts.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Repository-owned persistence boundary.
/**
 * Webhook delivery receipt storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Webhook\Webhook_Receipt_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records each accepted delivery's one-way key in a site-local table.
 *
 * The primary key decides which of two identical deliveries arrived first,
 * so a replay racing the original cannot also be accepted. Receipts are
 * deleted once they expire, because the delivery's signature is stale by then.
 */
final class Webhook_Receipt_Repository implements Webhook_Receipt_Source {
	/**
	 * {@inheritDoc}
	 */
	public function record( string $key, int $expires_at, int $now ): ?bool {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}
		global $wpdb;
		$table = Schema_Manager::table( 'webhook_receipts' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded expiry cleanup by indexed column.
			$wpdb->prepare( "DELETE FROM {$table} WHERE expires_at <= %d", $now ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
		);

		// Stored as SHA-256 so any caller's key fits the fixed-width primary key.
		$hashed     = hash( 'sha256', $key );
		$suppressed = $wpdb->suppress_errors();
		$inserted   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository write; the primary key arbitrates repeats.
			$table,
			array(
				'receipt_key' => $hashed,
				'expires_at'  => $expires_at,
			),
			array( '%s', '%d' )
		);
		$wpdb->suppress_errors( $suppressed );
		if ( 1 === $inserted ) {
			return true;
		}

		// Refused because the receipt exists, or because the table could not be written.
		$seen = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Primary-key lookup after a refused insert.
			$wpdb->prepare( "SELECT 1 FROM {$table} WHERE receipt_key = %s", $hashed ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted table.
		);

		return null === $seen ? null : false;
	}
}
