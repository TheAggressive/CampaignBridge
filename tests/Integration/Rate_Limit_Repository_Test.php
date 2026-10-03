<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Atomic request counter tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Storage;
use CampaignBridge\Repository\Rate_Limit_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Rate_Limiter;
use WP_UnitTestCase;

/**
 * Proves concurrent requests cannot be admitted beyond a limit.
 *
 * PHPUnit runs in one process, so concurrency is modelled by the worst
 * interleaving: a competing request runs to completion immediately before
 * each counter statement of the request under test executes, exactly as a
 * parallel request could between a read and a write.
 */
final class Rate_Limit_Repository_Test extends WP_UnitTestCase {
	private const NOW    = 1_800_000_000;
	private const WINDOW = 60;

	private Rate_Limit_Repository $counters;

	/** Competing requests still to inject. */
	private int $competitors = 0;

	/** @var array<int, bool|null> Outcomes of injected requests. */
	private array $competing = array();

	private ?\Closure $interleave = null;

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Schema_Manager::table( 'rate_limits' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Allowlisted isolated test table.
		$this->counters = new Rate_Limit_Repository();
	}

	public function tearDown(): void {
		if ( null !== $this->interleave ) {
			remove_filter( 'query', $this->interleave );
		}
		parent::tearDown();
	}

	public function test_admits_up_to_the_maximum_then_refuses(): void {
		for ( $i = 0; $i < 3; ++$i ) {
			self::assertTrue( $this->counters->claim( 'scope', 3, self::WINDOW, self::NOW ) );
		}
		self::assertFalse( $this->counters->claim( 'scope', 3, self::WINDOW, self::NOW ) );
		self::assertFalse( $this->counters->claim( 'scope', 3, self::WINDOW, self::NOW + 1 ) );
	}

	public function test_scopes_and_windows_are_independent(): void {
		self::assertTrue( $this->counters->claim( 'scope-a', 1, self::WINDOW, self::NOW ) );
		self::assertFalse( $this->counters->claim( 'scope-a', 1, self::WINDOW, self::NOW ) );
		self::assertTrue( $this->counters->claim( 'scope-b', 1, self::WINDOW, self::NOW ) );

		$next_window = ( intdiv( self::NOW, self::WINDOW ) + 1 ) * self::WINDOW;
		self::assertTrue( $this->counters->claim( 'scope-a', 1, self::WINDOW, $next_window ) );
	}

	public function test_ended_windows_are_purged_when_a_new_window_opens(): void {
		$this->counters->claim( 'old', 5, self::WINDOW, self::NOW );
		$this->counters->claim( 'new', 5, self::WINDOW, self::NOW + 10 * self::WINDOW );

		global $wpdb;
		$rows = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema_Manager::table( 'rate_limits' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Allowlisted isolated test table.
		self::assertSame( 1, $rows );
	}

	public function test_two_requests_opening_the_same_window_admit_only_one_at_a_limit_of_one(): void {
		$this->compete_before_each_statement( 1, 'scope', 1 );

		$outer = $this->counters->claim( 'scope', 1, self::WINDOW, self::NOW );

		self::assertSame( 1, $this->admitted( $outer ), 'Only one of two simultaneous first requests may pass.' );
	}

	public function test_a_request_racing_for_the_last_slot_cannot_also_be_admitted(): void {
		for ( $i = 0; $i < 4; ++$i ) {
			self::assertTrue( $this->counters->claim( 'scope', 5, self::WINDOW, self::NOW ) );
		}
		$this->compete_before_each_statement( 1, 'scope', 5 );

		$outer = $this->counters->claim( 'scope', 5, self::WINDOW, self::NOW );

		self::assertSame( 1, $this->admitted( $outer ), 'The last slot is granted exactly once.' );
	}

	public function test_a_burst_of_simultaneous_requests_admits_exactly_the_limit(): void {
		// Twenty requests, each overtaken by another at every statement.
		$this->compete_before_each_statement( 19, 'burst', 3 );

		$outer = $this->counters->claim( 'burst', 3, self::WINDOW, self::NOW );

		self::assertSame( 20, count( $this->competing ) + 1 );
		self::assertSame( 3, $this->admitted( $outer ) );
		self::assertNotContains( null, $this->competing, 'Contention must never be mistaken for an unavailable counter.' );
	}

	public function test_an_unavailable_schema_fails_closed(): void {
		Storage::update_option( Schema_Manager::OPTION, Schema_Manager::SCHEMA_VERSION - 1 );

		self::assertNull( $this->counters->claim( 'scope', 5, self::WINDOW, self::NOW ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$refusal = Rate_Limiter::check_rate_limit_authenticated( 'decrypt_field' );
		self::assertWPError( $refusal );
		self::assertSame( 'rate_limit_unavailable', $refusal->get_error_code() );
		self::assertSame( 503, $refusal->get_error_data()['status'] );
	}

	public function test_invalid_limits_fail_closed(): void {
		self::assertNull( $this->counters->claim( 'scope', 0, self::WINDOW, self::NOW ) );
		self::assertNull( $this->counters->claim( 'scope', 5, 0, self::NOW ) );
	}

	public function test_limiter_reports_time_until_the_window_resets(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		self::assertTrue( Rate_Limiter::check_rate_limit_authenticated( 'reset_probe', 'test_', 1, 3600 ) );

		$refusal = Rate_Limiter::check_rate_limit_authenticated( 'reset_probe', 'test_', 1, 3600 );
		self::assertWPError( $refusal );
		self::assertSame( 429, $refusal->get_error_data()['status'] );
		self::assertMatchesRegularExpression( '/Try again in ([1-9][0-9]{0,3}) seconds/', $refusal->get_error_message() );
	}

	/**
	 * Run a competing claim to completion before each counter statement.
	 *
	 * Competitors nest: each injected claim is itself overtaken, which
	 * produces every request reaching each statement before any writes.
	 */
	private function compete_before_each_statement( int $competitors, string $scope, int $maximum ): void {
		global $wpdb;
		$table             = Schema_Manager::table( 'rate_limits' );
		$this->competitors = $competitors;
		$this->competing   = array();
		$this->interleave  = function ( string $query ) use ( $table, $scope, $maximum ): string {
			$writes = str_starts_with( $query, 'UPDATE ' . $table ) || str_starts_with( $query, 'INSERT INTO `' . $table . '`' );
			if ( $writes && 0 < $this->competitors ) {
				--$this->competitors;
				$this->competing[] = $this->counters->claim( $scope, $maximum, self::WINDOW, self::NOW );
			}

			return $query;
		};
		add_filter( 'query', $this->interleave );
		unset( $wpdb );
	}

	private function admitted( ?bool $outer ): int {
		return count( array_filter( array_merge( $this->competing, array( $outer ) ), static fn ( ?bool $result ): bool => true === $result ) );
	}
}
