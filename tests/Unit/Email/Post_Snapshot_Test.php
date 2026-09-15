<?php
/**
 * Post snapshot contract tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Invalid_Post_Snapshot;
use CampaignBridge\Domain\Email\Post_Snapshot;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the canonical immutable post snapshot value object.
 */
final class Post_Snapshot_Test extends TestCase {
	/**
	 * Valid required-field values.
	 *
	 * @return array<string, mixed>
	 */
	private function valid_values(): array {
		return array(
			'title'   => 'Hello World',
			'excerpt' => 'A brief excerpt.',
			'url'     => 'https://example.com/hello-world',
		);
	}

	/**
	 * Valid values including all optional fields.
	 *
	 * @return array<string, mixed>
	 */
	private function full_values(): array {
		return array(
			'title'              => 'Hello World',
			'excerpt'            => 'A brief excerpt.',
			'url'                => 'https://example.com/hello-world',
			'image'              => array(
				'url'    => 'https://example.com/image.jpg',
				'alt'    => 'A descriptive alt text',
				'width'  => 800,
				'height' => 600,
			),
			'postParentUrl'      => 'https://example.com/parent',
			'postTypeArchiveUrl' => 'https://example.com/category/news',
		);
	}

	/**
	 * Required binding fields are accepted and readable.
	 */
	public function test_required_fields_are_accepted(): void {
		$snapshot = Post_Snapshot::create( 42, 'post', $this->valid_values() );

		self::assertSame( 'Hello World', $snapshot->get( 'title' ) );
		self::assertSame( 'A brief excerpt.', $snapshot->get( 'excerpt' ) );
		self::assertSame( 'https://example.com/hello-world', $snapshot->get( 'url' ) );
	}

	/**
	 * Optional binding fields are accepted and readable.
	 */
	public function test_optional_fields_are_accepted(): void {
		$snapshot = Post_Snapshot::create( 42, 'post', $this->full_values() );

		self::assertIsArray( $snapshot->get( 'image' ) );
		self::assertSame( 'https://example.com/parent', $snapshot->get( 'postParentUrl' ) );
		self::assertSame( 'https://example.com/category/news', $snapshot->get( 'postTypeArchiveUrl' ) );
	}

	/**
	 * The supported fields list returns the canonical vocabulary in order.
	 */
	public function test_supported_fields_returns_canonical_vocabulary(): void {
		self::assertSame(
			array( 'title', 'excerpt', 'url', 'image', 'postParentUrl', 'postTypeArchiveUrl' ),
			Post_Snapshot::supported_fields()
		);
	}

	/**
	 * Unknown binding fields are rejected at creation time.
	 */
	public function test_unknown_binding_field_is_rejected_on_creation(): void {
		$values               = $this->valid_values();
		$values['customMeta'] = 'arbitrary';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown binding field "customMeta"' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Unknown binding fields are rejected at access time.
	 */
	public function test_unknown_binding_field_is_rejected_on_access(): void {
		$snapshot = Post_Snapshot::create( 42, 'post', $this->valid_values() );

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown binding field "nonexistent"' );

		$snapshot->get( 'nonexistent' );
	}

	/**
	 * Unknown image sub-fields are rejected.
	 */
	public function test_unknown_image_subfield_is_rejected(): void {
		$values                    = $this->full_values();
		$values['image']['source'] = 'some-source';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown image sub-field "source"' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * The schema version is set to the current version.
	 */
	public function test_schema_version_is_set_to_current(): void {
		$snapshot = Post_Snapshot::create( 42, 'post', $this->valid_values() );

		self::assertSame( Post_Snapshot::SCHEMA_VERSION, $snapshot->schema_version() );
	}

	/**
	 * Missing required title is rejected.
	 */
	public function test_missing_required_title_is_rejected(): void {
		$values = $this->valid_values();
		unset( $values['title'] );

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Required binding field "title" is missing.' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Empty title is accepted (WordPress posts may have empty titles).
	 */
	public function test_empty_title_is_accepted(): void {
		$values          = $this->valid_values();
		$values['title'] = '';

		$snapshot = Post_Snapshot::create( 42, 'post', $values );

		self::assertSame( '', $snapshot->get( 'title' ) );
	}

	/**
	 * Empty excerpt is accepted (WordPress posts may have empty excerpts).
	 */
	public function test_empty_excerpt_is_accepted(): void {
		$values            = $this->valid_values();
		$values['excerpt'] = '';

		$snapshot = Post_Snapshot::create( 42, 'post', $values );

		self::assertSame( '', $snapshot->get( 'excerpt' ) );
	}

	/**
	 * Non-string title is rejected.
	 */
	public function test_non_string_title_is_rejected(): void {
		$values          = $this->valid_values();
		$values['title'] = 123;

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Binding "title" must be a string.' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Non-string excerpt is rejected.
	 */
	public function test_non_string_excerpt_is_rejected(): void {
		$values            = $this->valid_values();
		$values['excerpt'] = array( 'not', 'a', 'string' );

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Binding "excerpt" must be a string.' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Invalid URL is rejected.
	 */
	public function test_invalid_url_is_rejected(): void {
		$values        = $this->valid_values();
		$values['url'] = 'not-a-url';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Binding URL must be a valid absolute URL.' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Non-HTTP URL scheme is rejected.
	 */
	public function test_non_http_url_is_rejected(): void {
		$values        = $this->valid_values();
		$values['url'] = 'ftp://example.com/file';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Binding URL must use the HTTP or HTTPS scheme.' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Invalid image dimensions are rejected.
	 */
	public function test_invalid_image_dimensions_are_rejected(): void {
		$values                   = $this->full_values();
		$values['image']['width'] = 0;

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Image "width" must be a positive integer.' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Non-positive source ID is rejected.
	 */
	public function test_non_positive_source_id_is_rejected(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Source ID must be a positive integer.' );

		Post_Snapshot::create( 0, 'post', $this->valid_values() );
	}

	/**
	 * Hyphenated post type is accepted.
	 */
	public function test_hyphenated_post_type_is_accepted(): void {
		$snapshot = Post_Snapshot::create( 42, 'my-post-type', $this->valid_values() );

		self::assertSame( 'my-post-type', $snapshot->source_post_type() );
	}

	/**
	 * 20-character post type is accepted (maximum length).
	 */
	public function test_twenty_char_post_type_is_accepted(): void {
		$post_type = 'abcdefghijklmnopqrst'; // exactly 20 chars.

		$snapshot = Post_Snapshot::create( 42, $post_type, $this->valid_values() );

		self::assertSame( $post_type, $snapshot->source_post_type() );
	}

	/**
	 * Post type over 20 characters is rejected.
	 */
	public function test_post_type_over_twenty_chars_is_rejected(): void {
		$post_type = 'abcdefghijklmnopqrstu'; // 21 chars.

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Source post type must be a valid WordPress post-type slug.' );

		Post_Snapshot::create( 42, $post_type, $this->valid_values() );
	}

	/**
	 * Invalid post type characters are rejected.
	 */
	public function test_invalid_post_type_characters_are_rejected(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Source post type must be a valid WordPress post-type slug.' );

		Post_Snapshot::create( 42, 'Invalid-Type', $this->valid_values() );
	}

	/**
	 * Post type with special characters is rejected.
	 */
	public function test_post_type_with_special_characters_is_rejected(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Source post type must be a valid WordPress post-type slug.' );

		Post_Snapshot::create( 42, 'post type', $this->valid_values() );
	}

	/**
	 * Future schema versions are rejected.
	 */
	public function test_future_schema_version_is_rejected(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unsupported snapshot schema version 2. Only version 1 is supported.' );

		Post_Snapshot::create_with_version( 2, 42, 'post', $this->valid_values() );
	}

	/**
	 * Legacy schema versions are rejected.
	 */
	public function test_legacy_schema_version_is_rejected(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unsupported snapshot schema version 0. Only version 1 is supported.' );

		Post_Snapshot::create_with_version( 0, 42, 'post', $this->valid_values() );
	}

	/**
	 * The to_array() output includes schema version, source identity, and values.
	 */
	public function test_to_array_returns_full_serialized_shape(): void {
		$snapshot = Post_Snapshot::create( 42, 'post', $this->valid_values() );
		$array    = $snapshot->to_array();

		self::assertArrayHasKey( 'schema_version', $array );
		self::assertArrayHasKey( 'source_id', $array );
		self::assertArrayHasKey( 'source_post_type', $array );
		self::assertArrayHasKey( 'values', $array );

		self::assertSame( 1, $array['schema_version'] );
		self::assertSame( 42, $array['source_id'] );
		self::assertSame( 'post', $array['source_post_type'] );
		self::assertSame( $this->valid_values(), $array['values'] );
	}

	/**
	 * Round-trips through from_array() to an identical snapshot.
	 */
	public function test_from_array_round_trip(): void {
		$original = Post_Snapshot::create( 42, 'post', $this->full_values() );
		$restored = Post_Snapshot::from_array( $original->to_array() );

		self::assertSame( $original->to_array(), $restored->to_array() );
		self::assertSame( $original->fingerprint_payload(), $restored->fingerprint_payload() );
	}

	/**
	 * Rejects malformed stored data missing the values key.
	 */
	public function test_from_array_rejects_missing_values(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Values must be an array.' );

		Post_Snapshot::from_array(
			array(
				'schema_version'   => 1,
				'source_id'        => 42,
				'source_post_type' => 'post',
			)
		);
	}

	/**
	 * Rejects future schema versions during reconstitution.
	 */
	public function test_from_array_rejects_future_version(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unsupported snapshot schema version 2. Only version 1 is supported.' );

		Post_Snapshot::from_array(
			array(
				'schema_version'   => 2,
				'source_id'        => 42,
				'source_post_type' => 'post',
				'values'           => $this->valid_values(),
			)
		);
	}

	/**
	 * Rejects unknown top-level fields during reconstitution.
	 */
	public function test_from_array_rejects_unknown_fields(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown snapshot field "arbitrary" is not part of the serialization contract.' );

		Post_Snapshot::from_array(
			array(
				'schema_version'   => 1,
				'source_id'        => 42,
				'source_post_type' => 'post',
				'values'           => $this->valid_values(),
				'arbitrary'        => 'data',
			)
		);
	}

	/**
	 * Rejects non-integer source_id during reconstitution.
	 */
	public function test_from_array_rejects_invalid_source_id(): void {
		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Source ID must be a positive integer.' );

		Post_Snapshot::from_array(
			array(
				'schema_version'   => 1,
				'source_id'        => 'not-an-int',
				'source_post_type' => 'post',
				'values'           => $this->valid_values(),
			)
		);
	}

	/**
	 * Mutating the source array after creation does not affect the snapshot.
	 */
	public function test_input_mutation_does_not_mutate_snapshot(): void {
		$values   = $this->full_values();
		$snapshot = Post_Snapshot::create( 42, 'post', $values );

		$values['title']        = 'Mutated Title';
		$values['image']['url'] = 'https://example.com/mutated.jpg';

		self::assertSame( 'Hello World', $snapshot->get( 'title' ) );
		self::assertSame( 'https://example.com/image.jpg', $snapshot->get( 'image' )['url'] );
	}

	/**
	 * Fields are reordered to canonical order regardless of input order.
	 */
	public function test_fields_are_ordered_canonically_regardless_of_input_order(): void {
		$reversed = array(
			'postTypeArchiveUrl' => 'https://example.com/category/news',
			'postParentUrl'      => 'https://example.com/parent',
			'image'              => array(
				'url'    => 'https://example.com/image.jpg',
				'alt'    => 'A descriptive alt text',
				'width'  => 800,
				'height' => 600,
			),
			'url'                => 'https://example.com/hello-world',
			'excerpt'            => 'A brief excerpt.',
			'title'              => 'Hello World',
		);

		$snapshot  = Post_Snapshot::create( 42, 'post', $reversed );
		$canonical = Post_Snapshot::create( 42, 'post', $this->full_values() );

		self::assertSame( $canonical->to_array(), $snapshot->to_array() );
		self::assertSame( $canonical->fingerprint_payload(), $snapshot->fingerprint_payload() );
	}

	/**
	 * Provider-specific fields are rejected as unknown bindings.
	 */
	public function test_provider_fields_are_rejected(): void {
		$values             = $this->valid_values();
		$values['provider'] = 'mailchimp';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown binding field "provider"' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Audience-specific fields are rejected as unknown bindings.
	 */
	public function test_audience_fields_are_rejected(): void {
		$values               = $this->valid_values();
		$values['audienceId'] = 'abc123';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown binding field "audienceId"' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Credential fields are rejected as unknown bindings.
	 */
	public function test_credential_fields_are_rejected(): void {
		$values             = $this->valid_values();
		$values['apiToken'] = 'secret-token';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown binding field "apiToken"' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * Arbitrary post meta fields are rejected as unknown bindings.
	 */
	public function test_arbitrary_post_meta_is_rejected(): void {
		$values               = $this->valid_values();
		$values['meta_field'] = 'some-meta-value';

		$this->expectException( Invalid_Post_Snapshot::class );
		$this->expectExceptionMessage( 'Unknown binding field "meta_field"' );

		Post_Snapshot::create( 42, 'post', $values );
	}

	/**
	 * The fingerprint payload is deterministic across identical inputs.
	 */
	public function test_fingerprint_payload_is_deterministic(): void {
		$snapshot_a = Post_Snapshot::create( 42, 'post', $this->full_values() );
		$snapshot_b = Post_Snapshot::create( 42, 'post', $this->full_values() );

		$hash_a = hash( 'sha256', json_encode( $snapshot_a->fingerprint_payload() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain contract test, intentionally WordPress-independent.
		$hash_b = hash( 'sha256', json_encode( $snapshot_b->fingerprint_payload() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain contract test, intentionally WordPress-independent.

		self::assertSame( $hash_a, $hash_b );
	}

	/**
	 * Repeated access returns identical values (immutability).
	 */
	public function test_snapshot_is_immutable(): void {
		$snapshot = Post_Snapshot::create( 42, 'post', $this->full_values() );
		$before   = $snapshot->to_array();

		for ( $i = 0; $i < 5; $i++ ) {
			self::assertSame( $before, $snapshot->to_array() );
		}
	}
}
