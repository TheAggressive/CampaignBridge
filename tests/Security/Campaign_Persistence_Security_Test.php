<?php
/**
 * Security tests for the campaign custom-table boundary.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Email\Compiled_Artifact;
use CampaignBridge\Domain\Email\Review_Input;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Email\Template_Preview;

/** Proves untrusted values cannot escape or widen the persistence contract. */
final class Campaign_Persistence_Security_Test extends Test_Case {
	/** Prepare one known campaign in a clean schema. */
	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
		}
		self::assertTrue(
			( new Campaign_Repository() )->add(
				Campaign::create( 'secure-campaign', 7, 42, null, null, '2026-01-01T00:00:00Z' )
			)
		);
	}

	/** Detail lookups prepare opaque IDs instead of treating them as SQL. */
	public function test_campaign_lookup_resists_sql_injection(): void {
		$repository = new Campaign_Repository();

		self::assertNull( $repository->get( "secure-campaign' OR '1'='1" ) );
		self::assertSame( 'secure-campaign', $repository->get( 'secure-campaign' )?->id() );
	}

	/** Dynamic table suffixes are closed to the schema manager allowlist. */
	public function test_table_name_allowlist_rejects_injection(): void {
		$this->expectException( \InvalidArgumentException::class );
		Schema_Manager::table( 'campaigns; DROP TABLE posts' );
	}

	/** Frozen snapshot metadata cannot carry arbitrary secret/provider fields. */
	public function test_review_input_rejects_arbitrary_sensitive_metadata(): void {
		$preview = new Template_Preview( new Post_Snapshot_Repository() );

		$this->expectException( \InvalidArgumentException::class );
		$preview->capture( '<!-- wp:campaignbridge/container /-->', array( 'api_key' => 'never-store-me' ) );
	}

	/** Provider-resolved subscriber values remain tokens, never snapshot data. */
	public function test_review_input_rejects_subscriber_token_values(): void {
		$preview = new Template_Preview( new Post_Snapshot_Repository() );

		$this->expectException( \InvalidArgumentException::class );
		$preview->capture(
			'<!-- wp:campaignbridge/container /-->',
			array( 'token_values' => array( 'cb:subscriber.email' => 'person@example.org' ) )
		);
	}

	/** The complete nested review payload has a hard serialized-size limit. */
	public function test_review_input_rejects_oversized_nested_payload(): void {
		$preview        = new Template_Preview( new Post_Snapshot_Repository() );
		$data           = $preview->capture( '<!-- wp:campaignbridge/container /-->' )->to_array();
		$data['blocks'] = array(
			array(
				'blockName' => 'campaignbridge/container',
				'attrs'     => array( 'padding' => str_repeat( 'x', 2097152 ) ),
			),
		);

		$this->expectException( \InvalidArgumentException::class );
		Review_Input::from_array( $data );
	}

	/** Persisted artifacts accept only closed, credential-free image/font records. */
	public function test_compiled_artifact_rejects_unsafe_asset_records(): void {
		$base   = array(
			'schema_version'   => 1,
			'html'             => '<p>Safe</p>',
			'text'             => 'Safe',
			'fingerprint'      => 'sha256:' . str_repeat( 'a', 64 ),
			'compiler_version' => 'compiler@1',
			'profile_version'  => 'profile@1',
		);
		$unsafe = array(
			array(
				'type' => 'provider-payload',
				'url'  => 'https://example.org/raw',
			),
			array(
				'type' => 'font',
				'slug' => 'safe-font',
				'url'  => 'https://user:secret@example.org/font.css',
			),
			array(
				'type'   => 'image',
				'url'    => 'https://example.org/image.jpg',
				'width'  => 600,
				'height' => 400,
				'alt'    => '',
				'secret' => 'nope',
			),
		);

		foreach ( $unsafe as $asset ) {
			try {
				Compiled_Artifact::from_array( $base + array( 'assets' => array( $asset ) ) );
				self::fail( 'Unsafe compiled assets must be rejected.' );
			} catch ( \InvalidArgumentException ) {
				self::addToAssertionCount( 1 );
			}
		}
	}

	/** Raw bodies, provider payloads, stack traces, and credentials are redacted. */
	public function test_audit_context_redacts_raw_operational_data(): void {
		$context = Audit_Context::from_array(
			array(
				'raw_response'     => '{"email":"person@example.org"}',
				'provider_payload' => array( 'member' => 'person@example.org' ),
				'stack_trace'      => 'sensitive call stack',
				'api_secret'       => 'credential',
				'normalized_code'  => 'provider.timeout',
			)
		)->to_array();

		self::assertSame( '[redacted]', $context['raw_response'] );
		self::assertSame( '[redacted]', $context['provider_payload'] );
		self::assertSame( '[redacted]', $context['stack_trace'] );
		self::assertSame( '[redacted]', $context['api_secret'] );
		self::assertSame( 'provider.timeout', $context['normalized_code'] );
	}
}
