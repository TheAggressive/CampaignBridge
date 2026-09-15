<?php
/**
 * Canonical immutable post snapshot.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable, versioned post snapshot for email compilation.
 *
 * A snapshot is the frozen, resolved content of a WordPress post at the
 * moment it was captured. It is the sole source of truth for post-related
 * blocks during compilation, ensuring deterministic output regardless of
 * later changes to the source post.
 *
 * The snapshot contract is explicit and bounded: only the fields listed in
 * {@see self::FIELDS} are accepted. Unknown fields, arbitrary post meta,
 * provider/audience/credential data, and unsupported schema versions all
 * fail closed.
 */
final class Post_Snapshot {
	/** Current and only supported schema version. */
	public const SCHEMA_VERSION = 1;

	/** Canonical binding field vocabulary, in deterministic order. */
	private const FIELDS = array(
		'title',
		'excerpt',
		'url',
		'image',
		'postParentUrl',
		'postTypeArchiveUrl',
	);

	/** Fields that must be present in every valid snapshot. */
	private const REQUIRED_FIELDS = array(
		'title',
		'excerpt',
		'url',
	);

	/**
	 * Private constructor. Use {@see self::create()} or
	 * {@see self::create_with_version()}.
	 *
	 * @param int                  $schema_version   Schema version.
	 * @param int                  $source_id        WordPress post ID.
	 * @param string               $source_post_type WordPress post type slug.
	 * @param array<string, mixed> $values           Resolved binding values in canonical order.
	 */
	private function __construct(
		private readonly int $schema_version,
		private readonly int $source_id,
		private readonly string $source_post_type,
		private readonly array $values,
	) {}

	/**
	 * Create a snapshot at the current schema version.
	 *
	 * @param int                  $source_id        WordPress post ID.
	 * @param string               $source_post_type WordPress post type slug.
	 * @param array<string, mixed> $values           Resolved binding values.
	 *
	 * @throws Invalid_Post_Snapshot When the data is malformed.
	 */
	public static function create( int $source_id, string $source_post_type, array $values ): self {
		return self::create_with_version( self::SCHEMA_VERSION, $source_id, $source_post_type, $values );
	}

	/**
	 * Create a snapshot with an explicit schema version.
	 *
	 * Both legacy and future versions are rejected explicitly.
	 *
	 * @param int                  $schema_version   Schema version to apply.
	 * @param int                  $source_id        WordPress post ID.
	 * @param string               $source_post_type WordPress post type slug.
	 * @param array<string, mixed> $values           Resolved binding values.
	 *
	 * @throws Invalid_Post_Snapshot When the version is unsupported or data is malformed.
	 */
	public static function create_with_version( int $schema_version, int $source_id, string $source_post_type, array $values ): self {
		if ( self::SCHEMA_VERSION !== $schema_version ) {
			throw new Invalid_Post_Snapshot(
				sprintf( 'Unsupported snapshot schema version %d. Only version %d is supported.', $schema_version, self::SCHEMA_VERSION )
			);
		}

		if ( 1 > $source_id ) {
			throw new Invalid_Post_Snapshot( 'Source ID must be a positive integer.' );
		}

		if ( '' === $source_post_type || ! preg_match( '/^[a-z0-9_-]{1,20}$/', $source_post_type ) ) {
			throw new Invalid_Post_Snapshot( 'Source post type must be a valid WordPress post-type slug.' );
		}

		foreach ( array_keys( $values ) as $field ) {
			if ( ! in_array( $field, self::FIELDS, true ) ) {
				throw new Invalid_Post_Snapshot(
					sprintf( 'Unknown binding field "%s" is not part of the snapshot contract.', $field )
				);
			}
		}

		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $values ) ) {
				throw new Invalid_Post_Snapshot(
					sprintf( 'Required binding field "%s" is missing.', $field )
				);
			}
		}

		self::validate_title( $values['title'] );
		self::validate_excerpt( $values['excerpt'] );
		self::validate_url( $values['url'] );

		if ( array_key_exists( 'image', $values ) ) {
			self::validate_image( $values['image'] );
		}

		if ( array_key_exists( 'postParentUrl', $values ) ) {
			self::validate_url( $values['postParentUrl'] );
		}

		if ( array_key_exists( 'postTypeArchiveUrl', $values ) ) {
			self::validate_url( $values['postTypeArchiveUrl'] );
		}

		$canonical = array();
		foreach ( self::FIELDS as $field ) {
			if ( array_key_exists( $field, $values ) ) {
				$canonical[ $field ] = $values[ $field ];
			}
		}

		return new self( $schema_version, $source_id, $source_post_type, $canonical );
	}

	/** Get the schema version. */
	public function schema_version(): int {
		return $this->schema_version;
	}

	/** Get the WordPress post ID this snapshot was captured from. */
	public function source_id(): int {
		return $this->source_id;
	}

	/** Get the WordPress post type slug. */
	public function source_post_type(): string {
		return $this->source_post_type;
	}

	/**
	 * Get a resolved binding value.
	 *
	 * @param string $field Binding field name from the canonical vocabulary.
	 *
	 * @return mixed The resolved value, or null if the optional field is absent.
	 *
	 * @throws Invalid_Post_Snapshot When the field is not part of the contract.
	 */
	public function get( string $field ): mixed {
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			throw new Invalid_Post_Snapshot(
				sprintf( 'Unknown binding field "%s" is not part of the snapshot contract.', $field )
			);
		}

		return $this->values[ $field ] ?? null;
	}

	/**
	 * Check whether a binding field is present in this snapshot.
	 *
	 * @param string $field Binding field name from the canonical vocabulary.
	 *
	 * @throws Invalid_Post_Snapshot When the field is not part of the contract.
	 */
	public function has( string $field ): bool {
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			throw new Invalid_Post_Snapshot(
				sprintf( 'Unknown binding field "%s" is not part of the snapshot contract.', $field )
			);
		}

		return array_key_exists( $field, $this->values );
	}

	/**
	 * Return the canonical serialized representation.
	 *
	 * This is the single canonical shape for persistence and reconstitution.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version'   => $this->schema_version,
			'source_id'        => $this->source_id,
			'source_post_type' => $this->source_post_type,
			'values'           => $this->values,
		);
	}

	/**
	 * Reconstitute a snapshot from its canonical serialized form.
	 *
	 * Strictly validates the schema version, source identity, and values.
	 * Rejects unknown top-level fields (fail-closed).
	 *
	 * @param array<string, mixed> $data Stored snapshot data.
	 *
	 * @throws Invalid_Post_Snapshot When the record is malformed or unsupported.
	 */
	public static function from_array( array $data ): self {
		$allowed_keys = array( 'schema_version', 'source_id', 'source_post_type', 'values' );
		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				throw new Invalid_Post_Snapshot(
					sprintf( 'Unknown snapshot field "%s" is not part of the serialization contract.', $key )
				);
			}
		}

		$version = $data['schema_version'] ?? null;
		if ( ! is_int( $version ) || self::SCHEMA_VERSION !== $version ) {
			throw new Invalid_Post_Snapshot(
				sprintf( 'Unsupported snapshot schema version %s. Only version %d is supported.', is_scalar( $version ) ? (string) $version : gettype( $version ), self::SCHEMA_VERSION )
			);
		}

		$source_id = $data['source_id'] ?? null;
		if ( ! is_int( $source_id ) || 1 > $source_id ) {
			throw new Invalid_Post_Snapshot( 'Source ID must be a positive integer.' );
		}

		$source_post_type = $data['source_post_type'] ?? null;
		if ( ! is_string( $source_post_type ) || '' === $source_post_type || ! preg_match( '/^[a-z0-9_-]{1,20}$/', $source_post_type ) ) {
			throw new Invalid_Post_Snapshot( 'Source post type must be a valid WordPress post-type slug.' );
		}

		$values = $data['values'] ?? null;
		if ( ! is_array( $values ) ) {
			throw new Invalid_Post_Snapshot( 'Values must be an array.' );
		}

		return self::create( $source_id, $source_post_type, $values );
	}

	/**
	 * Return the canonical fingerprint payload.
	 *
	 * Reuses the serialized representation as the single source of truth
	 * for deterministic hashing.
	 *
	 * @return array<string, mixed>
	 */
	public function fingerprint_payload(): array {
		return $this->to_array();
	}

	/**
	 * Return the list of supported binding field names in canonical order.
	 *
	 * @return array<int, string>
	 */
	public static function supported_fields(): array {
		return self::FIELDS;
	}

	/**
	 * Validate the title binding value.
	 *
	 * @param mixed $value Candidate title.
	 *
	 * @throws Invalid_Post_Snapshot When the value is not a string.
	 */
	private static function validate_title( mixed $value ): void {
		if ( ! is_string( $value ) ) {
			throw new Invalid_Post_Snapshot( 'Binding "title" must be a string.' );
		}
	}

	/**
	 * Validate the excerpt binding value.
	 *
	 * @param mixed $value Candidate excerpt.
	 *
	 * @throws Invalid_Post_Snapshot When the value is not a string.
	 */
	private static function validate_excerpt( mixed $value ): void {
		if ( ! is_string( $value ) ) {
			throw new Invalid_Post_Snapshot( 'Binding "excerpt" must be a string.' );
		}
	}

	/**
	 * Validate a URL binding value.
	 *
	 * @param mixed $value Candidate URL.
	 *
	 * @throws Invalid_Post_Snapshot When the value is not a valid HTTP(S) URL.
	 */
	private static function validate_url( mixed $value ): void {
		if ( ! is_string( $value ) || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			throw new Invalid_Post_Snapshot( 'Binding URL must be a valid absolute URL.' );
		}

		$scheme = parse_url( $value, PHP_URL_SCHEME ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure domain validation, intentionally WordPress-independent.
		if ( ! in_array( strtolower( is_string( $scheme ) ? $scheme : '' ), array( 'http', 'https' ), true ) ) {
			throw new Invalid_Post_Snapshot( 'Binding URL must use the HTTP or HTTPS scheme.' );
		}
	}

	/**
	 * Validate the image binding value.
	 *
	 * @param mixed $value Candidate image record.
	 *
	 * @throws Invalid_Post_Snapshot When the image record is malformed.
	 */
	private static function validate_image( mixed $value ): void {
		if ( ! is_array( $value ) ) {
			throw new Invalid_Post_Snapshot( 'Binding "image" must be an object with url, alt, width, and height.' );
		}

		$allowed = array( 'url', 'alt', 'width', 'height' );
		foreach ( array_keys( $value ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new Invalid_Post_Snapshot(
					sprintf( 'Unknown image sub-field "%s" is not part of the snapshot contract.', $key )
				);
			}
		}

		self::validate_url( $value['url'] ?? null );

		if ( ! array_key_exists( 'alt', $value ) || ! is_string( $value['alt'] ) ) {
			throw new Invalid_Post_Snapshot( 'Image "alt" must be a string.' );
		}

		if ( ! array_key_exists( 'width', $value ) || ! is_int( $value['width'] ) || 1 > $value['width'] ) {
			throw new Invalid_Post_Snapshot( 'Image "width" must be a positive integer.' );
		}

		if ( ! array_key_exists( 'height', $value ) || ! is_int( $value['height'] ) || 1 > $value['height'] ) {
			throw new Invalid_Post_Snapshot( 'Image "height" must be a positive integer.' );
		}
	}
}
