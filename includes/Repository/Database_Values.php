<?php // phpcs:disable CampaignBridge.Standard.Sniffs.Database -- Focused conversion methods are explicit; this helper remains inside the authorized custom-table boundary.
// phpcs:disable CampaignBridge.Standard.Sniffs.Database.DatabaseOperation.DirectWpdbManipulation,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectWpdbPropertyAccess,CampaignBridge.Standard.Sniffs.Database.DirectDatabaseQuery.DirectDatabaseMethod -- Conversion failures are fail-closed; direct queries remain inside the repository boundary.
/**
 * Campaign repository database value conversion.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Converts only the bounded scalar/JSON shapes used by #73 repositories. */
final class Database_Values {
	/**
	 * Verify a logical campaign relationship without exposing SQL to callers.
	 *
	 * @param string $campaign_id Campaign ID.
	 */
	public static function campaign_exists( string $campaign_id ): bool {
		global $wpdb;
		$table = Schema_Manager::table( 'campaigns' );
		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository integrity check against the authoritative table.
			$wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s LIMIT 1", $campaign_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table comes from the Schema_Manager allowlist.
		);
		return $campaign_id === $value;
	}

	/**
	 * Verify that an immutable snapshot belongs to the referenced campaign.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param string $campaign_id Campaign ID.
	 */
	public static function snapshot_belongs_to_campaign( string $snapshot_id, string $campaign_id ): bool {
		global $wpdb;
		$table = Schema_Manager::table( 'campaign_snapshots' );
		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository integrity check against the authoritative table.
			$wpdb->prepare(
				"SELECT campaign_id FROM {$table} WHERE id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table comes from the Schema_Manager allowlist.
				$snapshot_id
			)
		);
		return $campaign_id === $value;
	}

	/**
	 * A canonical UTC timestamp in MySQL datetime form.
	 *
	 * @param string $timestamp UTC timestamp.
	 */
	public static function to_database_time( string $timestamp ): string {
		return str_replace( array( 'T', 'Z' ), array( ' ', '' ), $timestamp );
	}

	/**
	 * A MySQL datetime as a canonical UTC timestamp.
	 *
	 * @param mixed $value Stored datetime.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_database_time( mixed $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			throw new \InvalidArgumentException( 'Stored database timestamp is malformed.' );
		}
		return str_replace( ' ', 'T', $value ) . 'Z';
	}

	/**
	 * Deterministic JSON for a stored value.
	 *
	 * @param array<string, mixed>|array<int, mixed> $value Value to store.
	 * @throws \JsonException When the value cannot be encoded as JSON.
	 */
	public static function encode_json( array $value ): string {
		return json_encode( $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Repository serialization requires throwing, deterministic JSON.
	}

	/**
	 * Decode stored JSON into an array.
	 *
	 * @param mixed $value Stored JSON text.
	 * @return array<mixed>
	 * @throws \InvalidArgumentException When the stored value is not valid JSON or not a JSON array.
	 */
	public static function decode_json( mixed $value ): array {
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( 'Stored JSON is not a string.' );
		}
		try {
			$decoded = json_decode( $value, true, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $malformed ) {
			throw new \InvalidArgumentException( 'Stored JSON is malformed.', 0, $malformed );
		}
		if ( ! is_array( $decoded ) ) {
			throw new \InvalidArgumentException( 'Stored JSON is not an array.' );
		}
		return $decoded;
	}

	/**
	 * A stored integer, which the database may return as a string.
	 *
	 * @param mixed  $value    Stored value.
	 * @param string $label    Field name for error messages.
	 * @param bool   $nullable Whether null is valid.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function integer( mixed $value, string $label, bool $nullable = false ): ?int {
		if ( null === $value && $nullable ) {
			return null;
		}
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}
		throw new \InvalidArgumentException( $label . ' is not an integer.' );
	}
}
