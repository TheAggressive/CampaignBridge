<?php // phpcs:disable CampaignBridge.Standard.Sniffs.Database,WordPress.DB.DirectDatabaseQuery -- Authorized transaction infrastructure boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess
/**
 * WordPress database transaction boundary for campaign workflows.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Campaign\Campaign_Transaction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Coordinates repository writes on the current transactional database engine. */
final class Database_Transaction implements Campaign_Transaction {
	/**
	 * {@inheritDoc}
	 */
	public function run( callable $operation ): bool {
		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed transaction statement.
			return false;
		}

		try {
			if ( ! $operation() ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed transaction statement.
				return false;
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed transaction statement.
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed transaction statement.
				return false;
			}
		} catch ( \Throwable ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed transaction statement.
			return false;
		}

		return true;
	}
}
