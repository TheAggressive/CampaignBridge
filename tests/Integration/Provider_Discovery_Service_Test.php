<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile
/**
 * Provider discovery cache and refresh integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Discovered_Audience;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Discovery;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Provider\Discovery_Lookup;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

/** A controllable clock for freshness assertions. */
final class Discovery_Test_Clock implements Campaign_Clock {
	public int $now = 1790000000;

	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->now );
	}
}

/** A provider double that counts remote calls and can be told to fail. */
final class Counting_Discovery implements Provider_Discovery {
	public int $calls = 0;

	public ?Provider_Error $failure = null;

	/** @var array<int, string> */
	public array $names = array( 'Customers' );

	/** @param array<string, bool> $flags Advertised operations. */
	public function __construct( private readonly array $flags ) {}

	public function slug(): string {
		return 'example';
	}

	public function capabilities(): Provider_Capabilities {
		return Provider_Capabilities::from_flags( 'example', $this->flags );
	}

	public function account_key( array $settings ): ?string {
		$key = $settings['api_key'] ?? null;

		return is_string( $key ) && '' !== $key ? hash( 'sha256', $key ) : null;
	}

	public function discover_audiences( array $settings ): Discovery_Batch|Provider_Error {
		++$this->calls;

		return $this->failure ?? Discovery_Batch::create(
			Discovery_Kind::AUDIENCES,
			array_map( static fn ( string $name ): Discovered_Audience => Discovered_Audience::create( 'id' . strlen( $name ), $name ), $this->names ),
			true
		);
	}

	public function discover_merge_fields( array $settings, string $audience_id ): Discovery_Batch|Provider_Error {
		++$this->calls;

		return $this->failure ?? Discovery_Batch::create( Discovery_Kind::MERGE_FIELDS, array( Discovered_Merge_Field::create( 'FNAME', 'First Name', 'text', false ) ), true );
	}

	public function discover_segments( array $settings, string $audience_id ): Discovery_Batch|Provider_Error {
		++$this->calls;

		return $this->failure ?? Discovery_Batch::create( Discovery_Kind::SEGMENTS, array(), true );
	}
}

/** Proves cache hit, explicit refresh, staleness, and failure semantics. */
final class Provider_Discovery_Service_Test extends Test_Case {
	private const SETTINGS = array( 'api_key' => 'account-one-secret' );

	private Counting_Discovery $provider;

	private Discovery_Test_Clock $clock;

	private Provider_Discovery_Service $service;

	public function setUp(): void {
		parent::setUp();
		$this->provider = new Counting_Discovery(
			array(
				'discover_audiences'    => true,
				'discover_merge_fields' => true,
			)
		);
		$this->clock    = new Discovery_Test_Clock();
		$this->service  = new Provider_Discovery_Service( $this->provider, new Provider_Discovery_Repository(), $this->clock );
	}

	public function test_reading_never_contacts_the_provider_until_an_explicit_refresh(): void {
		$miss = $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertSame( Discovery_Lookup::SOURCE_NONE, $miss->source() );
		self::assertNull( $miss->result() );
		self::assertNull( $miss->error() );
		self::assertSame( 0, $this->provider->calls );

		$fresh = $this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertSame( Discovery_Lookup::SOURCE_REMOTE, $fresh->source() );
		self::assertFalse( $fresh->is_stale() );
		self::assertSame( $this->clock->now(), $fresh->result()?->fetched_at() );
		self::assertSame( 1, $this->provider->calls );

		$hit = $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertSame( Discovery_Lookup::SOURCE_CACHE, $hit->source() );
		self::assertFalse( $hit->is_stale() );
		self::assertSame( $fresh->result()?->to_array(), $hit->result()?->to_array() );
		self::assertSame( 1, $this->provider->calls, 'A cache hit must not call the provider.' );
	}

	public function test_results_past_the_freshness_window_are_returned_but_stale(): void {
		$this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );

		$this->clock->now += Provider_Discovery_Service::FRESH_SECONDS - 1;
		self::assertFalse( $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS )->is_stale() );

		$this->clock->now += 1;
		$stale = $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertTrue( $stale->is_stale() );
		self::assertNotNull( $stale->result() );
		self::assertSame( 1, $this->provider->calls, 'Staleness never triggers an implicit refresh.' );

		$this->provider->names = array( 'Customers', 'Newsletter' );
		$refreshed             = $this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertCount( 2, $refreshed->result()?->items() ?? array() );
		self::assertFalse( $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS )->is_stale() );
	}

	public function test_failed_refresh_keeps_the_previous_list_but_reports_it_stale(): void {
		$this->provider->failure = Provider_Error::from_category( Provider_Error_Category::RATE_LIMITED, 'example_rate_limited', 'Slow down.', 'example' );
		$nothing                 = $this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertSame( Discovery_Lookup::SOURCE_NONE, $nothing->source() );
		self::assertNull( $nothing->result() );
		self::assertSame( Provider_Error_Category::RATE_LIMITED, $nothing->error()?->category() );

		$this->provider->failure = null;
		$good                    = $this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );

		$this->provider->failure = Provider_Error::from_category( Provider_Error_Category::AUTHENTICATION, 'example_auth', 'Rejected.', 'example' );
		$failed                  = $this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );
		self::assertSame( Discovery_Lookup::SOURCE_CACHE, $failed->source() );
		self::assertTrue( $failed->is_stale() );
		self::assertSame( Provider_Error_Category::AUTHENTICATION, $failed->error()?->category() );
		self::assertSame( $good->result()?->to_array(), $failed->result()?->to_array() );
		self::assertSame( $good->result()?->to_array(), $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS )->result()?->to_array(), 'A failure never overwrites the cached list.' );
	}

	public function test_unsupported_and_unconfigured_requests_are_reported_without_remote_calls(): void {
		$unsupported = $this->service->refresh( Discovery_Kind::SEGMENTS, self::SETTINGS, 'id9' );
		self::assertFalse( $unsupported->is_supported() );
		self::assertNull( $unsupported->result() );
		self::assertFalse( $this->service->cached( Discovery_Kind::SEGMENTS, self::SETTINGS, 'id9' )->is_supported() );

		$unconfigured = $this->service->refresh( Discovery_Kind::AUDIENCES, array() );
		self::assertSame( Provider_Error_Category::VALIDATION, $unconfigured->error()?->category() );
		self::assertSame( 'example_not_configured', $unconfigured->error()?->code() );
		self::assertSame( 0, $this->provider->calls );
	}

	public function test_cache_is_partitioned_by_account_and_audience_scope(): void {
		$this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );
		$this->service->refresh( Discovery_Kind::MERGE_FIELDS, self::SETTINGS, 'id9' );

		self::assertSame( Discovery_Lookup::SOURCE_NONE, $this->service->cached( Discovery_Kind::AUDIENCES, array( 'api_key' => 'account-two-secret' ) )->source() );
		self::assertSame( Discovery_Lookup::SOURCE_CACHE, $this->service->cached( Discovery_Kind::MERGE_FIELDS, self::SETTINGS, 'id9' )->source() );
		self::assertSame( Discovery_Lookup::SOURCE_NONE, $this->service->cached( Discovery_Kind::MERGE_FIELDS, self::SETTINGS, 'id8' )->source() );
	}

	public function test_scope_must_match_the_kind(): void {
		try {
			$this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS, 'id9' );
			self::fail( 'Audiences are account-wide.' );
		} catch ( \InvalidArgumentException ) {
			self::assertTrue( true );
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->service->refresh( Discovery_Kind::MERGE_FIELDS, self::SETTINGS );
	}

	public function test_stored_entries_hold_no_credentials_and_corrupt_entries_read_as_a_miss(): void {
		$this->service->refresh( Discovery_Kind::AUDIENCES, self::SETTINGS );

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%discovery_%'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Asserts what reached storage.
		self::assertNotEmpty( $rows );
		foreach ( $rows as $row ) {
			self::assertStringNotContainsString( 'account-one-secret', $row['option_name'] . $row['option_value'] );
		}

		$repository = new Provider_Discovery_Repository();
		$account    = $this->provider->account_key( self::SETTINGS );
		$key        = preg_replace( '/^.*?(discovery_[a-f0-9]{40})$/', '$1', (string) $rows[0]['option_name'] );
		Storage::set_transient( (string) $key, array( 'schema_version' => 1, 'items' => 'corrupt' ), 60 );
		self::assertNull( $repository->get( (string) $account, 'example', Discovery_Kind::AUDIENCES, '' ) );
		self::assertSame( Discovery_Lookup::SOURCE_NONE, $this->service->cached( Discovery_Kind::AUDIENCES, self::SETTINGS )->source() );
	}
}
