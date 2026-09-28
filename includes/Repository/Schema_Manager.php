<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment.Missing,CampaignBridge.Standard.Sniffs.Database -- Migration methods are narrowly scoped; this class owns the authorized custom-table schema boundary.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag,CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Allowlist failures are explicit; schema inspection is repository-owned.
/**
 * Campaign lifecycle custom-table schema.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns restartable, forward-safe database migrations for campaign storage. */
final class Schema_Manager {
	public const SCHEMA_VERSION = 1;
	public const OPTION         = 'database_schema';

	private static ?bool $tables_ready = null;

	private const TABLES = array(
		'campaigns',
		'campaign_snapshots',
		'remote_campaigns',
		'delivery_attempts',
		'audit_events',
	);

	/** Run an upgrade only when the installed schema is older. */
	public static function maybe_migrate(): void {
		self::migrate();
	}

	/**
	 * Create or upgrade the site-local tables.
	 *
	 * A malformed or newer version fails closed and is never overwritten.
	 */
	public static function migrate(): bool {
		self::$tables_ready = null;
		$stored             = self::stored_version();
		if ( null === $stored || $stored > self::SCHEMA_VERSION ) {
			return false;
		}
		if ( self::SCHEMA_VERSION === $stored && self::tables_exist() ) {
			self::$tables_ready = true;
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( self::schema_statements() );
		if ( ! self::tables_exist() ) {
			return false;
		}
		self::$tables_ready = true;

		return Storage::update_option( self::OPTION, self::SCHEMA_VERSION )
			|| self::SCHEMA_VERSION === self::stored_version();
	}

	/** Whether repositories may safely interpret the installed schema. */
	public static function is_current(): bool {
		if ( self::SCHEMA_VERSION !== self::stored_version() ) {
			return false;
		}
		if ( null === self::$tables_ready ) {
			self::$tables_ready = self::tables_exist();
		}
		return self::$tables_ready;
	}

	/** Resolve one allowlisted site-local table name. */
	public static function table( string $suffix ): string {
		if ( ! in_array( $suffix, self::TABLES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown CampaignBridge table.' );
		}

		global $wpdb;
		return $wpdb->prefix . 'campaignbridge_' . $suffix;
	}

	/** @return array<int, string> */
	public static function table_names(): array {
		return array_map( array( self::class, 'table' ), self::TABLES );
	}

	/** Read an absent/legacy version as zero and reject malformed values. */
	private static function stored_version(): ?int {
		$value = Storage::get_option( self::OPTION, null );
		if ( null === $value || false === $value ) {
			return 0;
		}
		if ( is_int( $value ) && 0 <= $value ) {
			return $value;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}
		return null;
	}

	/** Verify every table before stamping a successful migration. */
	private static function tables_exist(): bool {
		global $wpdb;
		foreach ( self::table_names() as $table ) {
			$suppressed = $wpdb->suppress_errors();
			$columns    = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table comes from the Schema_Manager allowlist.
			$wpdb->suppress_errors( $suppressed );
			if ( ! is_array( $columns ) || array() === $columns ) {
				return false;
			}
		}
		return true;
	}

	/** @return array<int, string> */
	private static function schema_statements(): array {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();

		return array(
			'CREATE TABLE ' . self::table( 'campaigns' ) . " (
				id varchar(64) NOT NULL,
				data_version smallint unsigned NOT NULL DEFAULT 1,
				state varchar(32) NOT NULL,
				version bigint unsigned NOT NULL,
				owner_user_id bigint unsigned NOT NULL,
				template_id bigint unsigned NOT NULL,
				provider varchar(64) NULL,
				audience_reference varchar(191) NULL,
				active_snapshot_id varchar(64) NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY owner_updated (owner_user_id, updated_at)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'campaign_snapshots' ) . " (
				id varchar(64) NOT NULL,
				data_version smallint unsigned NOT NULL DEFAULT 1,
				campaign_id varchar(64) NOT NULL,
				revision bigint unsigned NOT NULL,
				review_input longtext NOT NULL,
				artifact_html longtext NOT NULL,
				artifact_text longtext NOT NULL,
				assets_json text NOT NULL,
				artifact_fingerprint varchar(71) NOT NULL,
				compiler_version varchar(32) NOT NULL,
				profile_version varchar(32) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign_revision (campaign_id, revision)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'remote_campaigns' ) . " (
				campaign_id varchar(64) NOT NULL,
				provider varchar(64) NOT NULL,
				data_version smallint unsigned NOT NULL DEFAULT 1,
				remote_id varchar(191) NOT NULL,
				observed_state varchar(32) NOT NULL,
				provider_cursor varchar(191) NULL,
				observed_at datetime NOT NULL,
				reconciled_at datetime NULL,
				PRIMARY KEY  (campaign_id, provider),
				UNIQUE KEY provider_remote (provider, remote_id)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'delivery_attempts' ) . " (
				id varchar(64) NOT NULL,
				data_version smallint unsigned NOT NULL DEFAULT 1,
				campaign_id varchar(64) NOT NULL,
				operation varchar(32) NOT NULL,
				idempotency_key varchar(191) NULL,
				status varchar(16) NOT NULL,
				retryability varchar(16) NOT NULL,
				remote_correlation varchar(191) NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign_idempotency (campaign_id, operation, idempotency_key),
				KEY campaign_created (campaign_id, created_at)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'audit_events' ) . " (
				id varchar(64) NOT NULL,
				data_version smallint unsigned NOT NULL DEFAULT 1,
				actor_user_id bigint unsigned NULL,
				action varchar(64) NOT NULL,
				target_type varchar(64) NOT NULL,
				target_id varchar(64) NOT NULL,
				result varchar(16) NOT NULL,
				context_json text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY target_created (target_type, target_id, created_at)
			) {$collate};",
		);
	}
}
