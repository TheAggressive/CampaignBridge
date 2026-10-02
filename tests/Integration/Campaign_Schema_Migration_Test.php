<?php
/**
 * Campaign custom-table migration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Storage;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;

/** Proves schema upgrades are restartable and forward-safe. */
final class Campaign_Schema_Migration_Test extends Test_Case {
	/** Start each test with the pre-custom-table schema state. */
	public function set_up(): void {
		parent::set_up();
		$this->drop_tables();
		Storage::delete_option( Schema_Manager::OPTION );
	}

	/** Restore the installed plugin schema for tests that follow. */
	public function tear_down(): void {
		$this->drop_tables();
		Storage::delete_option( Schema_Manager::OPTION );
		Schema_Manager::migrate();
		parent::tear_down();
	}

	/** Fresh installs create every site-local table and stamp the version. */
	public function test_fresh_install_creates_expected_schema(): void {
		self::assertTrue( Schema_Manager::migrate() );
		self::assertSame( Schema_Manager::SCHEMA_VERSION, Storage::get_option( Schema_Manager::OPTION ) );

		foreach ( Schema_Manager::table_names() as $table ) {
			self::assertTrue( $this->table_exists( $table ), $table );
		}
	}

	/** Version zero is the previous state and upgrades without data loss. */
	public function test_upgrade_from_previous_schema_state(): void {
		Storage::update_option( Schema_Manager::OPTION, 0 );

		self::assertTrue( Schema_Manager::migrate() );
		self::assertSame( Schema_Manager::SCHEMA_VERSION, Storage::get_option( Schema_Manager::OPTION ) );
	}

	/** Version 1 gains the snapshot envelope column without losing snapshot rows. */
	public function test_upgrade_from_version_one_adds_the_envelope_column(): void {
		global $wpdb;
		self::assertTrue( Schema_Manager::migrate() );
		$table = Schema_Manager::table( 'campaign_snapshots' );
		$wpdb->query( "ALTER TABLE {$table} DROP COLUMN envelope" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Recreates the version-1 shape.
		$wpdb->insert(
			$table,
			array(
				'id'                   => 'legacy-snapshot',
				'data_version'         => 1,
				'campaign_id'          => 'legacy-campaign',
				'revision'             => 1,
				'review_input'         => '{}',
				'artifact_html'        => '<p>Legacy</p>',
				'artifact_text'        => 'Legacy',
				'assets_json'          => '[]',
				'artifact_fingerprint' => 'sha256:' . str_repeat( 'a', 64 ),
				'compiler_version'     => '1',
				'profile_version'      => 'universal@1',
				'created_at'           => '2026-01-01 00:00:00',
			)
		);
		Storage::update_option( Schema_Manager::OPTION, 1 );
		self::assertFalse( Schema_Manager::is_current() );

		self::assertTrue( Schema_Manager::migrate() );
		self::assertSame( Schema_Manager::SCHEMA_VERSION, Storage::get_option( Schema_Manager::OPTION ) );
		self::assertContains( 'envelope', $wpdb->get_col( "DESCRIBE {$table}", 0 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
		self::assertSame( 'legacy-snapshot', $wpdb->get_var( "SELECT id FROM {$table} WHERE envelope IS NULL" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
	}

	/** Product read paths and identity rules have their documented indexes. */
	public function test_expected_query_indexes_exist(): void {
		global $wpdb;
		self::assertTrue( Schema_Manager::migrate() );
		$expected = array(
			'campaigns'          => array( 'PRIMARY', 'owner_updated' ),
			'campaign_snapshots' => array( 'PRIMARY', 'campaign_revision' ),
			'remote_campaigns'   => array( 'PRIMARY', 'provider_remote' ),
			'delivery_attempts'  => array( 'PRIMARY', 'campaign_idempotency', 'campaign_created' ),
			'audit_events'       => array( 'PRIMARY', 'target_created' ),
		);

		foreach ( $expected as $suffix => $indexes ) {
			$table = Schema_Manager::table( $suffix );
			$names = $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
			foreach ( $indexes as $index ) {
				self::assertContains( $index, $names, $table . ':' . $index );
			}
		}
	}

	/** Running the current migration repeatedly preserves existing rows. */
	public function test_repeated_migration_is_idempotent(): void {
		global $wpdb;
		self::assertTrue( Schema_Manager::migrate() );
		$table = Schema_Manager::table( 'campaigns' );
		$wpdb->insert(
			$table,
			array(
				'id'                 => 'migration-fixture',
				'data_version'       => 1,
				'state'              => 'draft',
				'version'            => 1,
				'owner_user_id'      => 1,
				'template_id'        => 1,
				'provider'           => null,
				'audience_reference' => null,
				'active_snapshot_id' => null,
				'created_at'         => '2026-01-01 00:00:00',
				'updated_at'         => '2026-01-01 00:00:00',
			)
		);

		self::assertTrue( Schema_Manager::migrate() );
		self::assertSame( 'migration-fixture', $wpdb->get_var( "SELECT id FROM {$table} WHERE id = 'migration-fixture'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table and literal fixture.
	}

	/** A current version with one missing table is treated as interrupted work. */
	public function test_partial_migration_recreates_missing_table(): void {
		global $wpdb;
		self::assertTrue( Schema_Manager::migrate() );
		$missing = Schema_Manager::table( 'campaign_snapshots' );
		$wpdb->query( "DROP TABLE {$missing}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
		self::assertFalse( $this->table_exists( $missing ) );

		self::assertTrue( Schema_Manager::migrate() );
		self::assertTrue( $this->table_exists( $missing ) );
	}

	/** Newer schemas are not downgraded or destructively rewritten. */
	public function test_unknown_newer_schema_is_preserved(): void {
		Storage::update_option( Schema_Manager::OPTION, 99 );

		self::assertFalse( Schema_Manager::migrate() );
		self::assertSame( 99, Storage::get_option( Schema_Manager::OPTION ) );
		foreach ( Schema_Manager::table_names() as $table ) {
			self::assertFalse( $this->table_exists( $table ) );
		}
	}

	/** Malformed versions fail closed and remain available for operator repair. */
	public function test_malformed_schema_version_is_preserved(): void {
		Storage::update_option( Schema_Manager::OPTION, 'not-a-version' );

		self::assertFalse( Schema_Manager::migrate() );
		self::assertSame( 'not-a-version', Storage::get_option( Schema_Manager::OPTION ) );
	}

	/** Drop only the five allowlisted test tables. */
	private function drop_tables(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test tables.
		}
	}

	/** Check the WordPress catalog for one exact table name. */
	private function table_exists( string $table ): bool {
		global $wpdb;
		$suppressed = $wpdb->suppress_errors();
		$columns    = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
		$wpdb->suppress_errors( $suppressed );
		return is_array( $columns ) && array() !== $columns;
	}
}
