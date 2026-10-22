<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * WP-CLI commands through the real dispatcher and isolated WordPress storage.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\CLI\Commands;
use CampaignBridge\Core\Capabilities;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_State;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Lock_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Job\Job_Handler;
use CampaignBridge\Workflow\Job\Job_Recovery;
use CampaignBridge\Workflow\Operations\Operation_Refused;

/**
 * WP_CLI is a process-wide constant; keep the CLI runtime out of other suites.
 *
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 */
final class CLI_Commands_Test extends Test_Case {
	private int $admin;
	private array $requests = array();
	private \Closure $http;
	private string $reply = '{}';
	private int $status = 200;
	private bool $throw_http = false;

	public function setUp(): void {
		parent::setUp();
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
			define( 'WP_CLI_ROOT', dirname( ( new \ReflectionClass( \WP_CLI::class ) )->getFileName(), 2 ) );
			require_once WP_CLI_ROOT . '/php/utils.php';
			require_once WP_CLI_ROOT . '/php/dispatcher.php';
			$config = new \ReflectionProperty( \WP_CLI\Runner::class, 'config' );
			$config->setValue( \WP_CLI::get_runner(), \WP_CLI::get_configurator()->to_array()[0] );
		}
		self::assertTrue( Schema_Manager::migrate() );
		$this->clear_tables();
		Commands::register();
		$this->admin = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
		$this->http = function ( mixed $preempt, array $args, string $url ): array {
			$this->requests[] = array( 'method' => $args['method'] ?? 'GET', 'url' => $url );
			if ( $this->throw_http ) {
				throw new \RuntimeException( 'Bearer private-secret subscriber@example.com raw-response' );
			}

			return array( 'headers' => array(), 'body' => $this->reply, 'response' => array( 'code' => $this->status, 'message' => '' ), 'cookies' => array(), 'filename' => null );
		};
		add_filter( 'pre_http_request', $this->http, 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', $this->http );
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		$this->clear_tables();
		global $wpdb;
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	public function test_commands_require_a_named_user_with_the_normal_capability(): void {
		foreach ( array( 0, $this->create_test_user( array( 'role' => 'subscriber' ) ) ) as $user ) {
			wp_set_current_user( $user );
			foreach ( array( 'jobs list', 'jobs show job-one', 'jobs retry job-one', 'jobs cancel job-one --yes', 'diagnostics', 'connection check', 'campaign reconcile campaign-one' ) as $command ) {
				$result = $this->command( $command );
				self::assertSame( 1, $result->return_code, $command );
				self::assertSame( '', $result->stdout );
			}
		}
		self::assertSame( array(), $this->requests );
	}

	public function test_jobs_list_and_show_are_bounded_and_exclude_private_values(): void {
		$this->job( 'job-a' );
		$this->job( 'job-b' );
		$result = $this->command( 'jobs list --state=queued --limit=1 --offset=1' );
		self::assertSame( 0, $result->return_code, $result->stderr );
		$rows = json_decode( $result->stdout, true );
		self::assertCount( 1, $rows );
		self::assertSame( 'job-a', $rows[0]['id'] );
		$show = $this->command( 'jobs show job-b' );
		self::assertSame( 'job-b', json_decode( $show->stdout, true )['id'] );
		foreach ( array( 'payload', 'target_id', 'dedupe_key', 'lease_owner', 'subscriber@example.com', 'private-body' ) as $private ) {
			self::assertStringNotContainsString( $private, $show->stdout );
		}
		foreach ( array( '--limit=101', '--limit=0', '--offset=-1', '--limit=2oops', '--state=unknown' ) as $invalid ) {
			self::assertSame( 1, $this->command( 'jobs list ' . $invalid )->return_code );
		}
		self::assertSame( 1, $this->command( 'jobs show missing' )->return_code );
	}

	public function test_job_inspection_can_identify_a_local_campaign_without_exposing_arbitrary_target_values(): void {
		$job = Job::create( 'job-local', 'reconcile_campaign', 'campaign', 'campaign-one', array(), 5, $this->now(), $this->now() );
		self::assertTrue( ( new Job_Repository() )->add( $job ) );
		$result = $this->command( 'jobs show job-local' );
		self::assertSame( 0, $result->return_code );
		self::assertSame( 'campaign-one', json_decode( $result->stdout, true )['campaign_id'] );
	}

	public function test_management_does_not_grant_connection_or_delivery_authority(): void {
		$user = $this->create_test_user( array( 'role' => 'subscriber' ) );
		( new \WP_User( $user ) )->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $user );
		self::assertSame( 0, $this->command( 'diagnostics' )->return_code );
		self::assertSame( 1, $this->command( 'connection check' )->return_code );
		self::assertSame( 1, $this->command( 'campaign reconcile campaign-one' )->return_code );
		self::assertSame( array(), $this->requests );
	}

	public function test_retry_requeues_safe_failed_and_dead_jobs_without_running_them_and_audits_the_actor(): void {
		foreach ( array( Job_State::FAILED, Job_State::DEAD ) as $state ) {
			$id = 'job-' . $state;
			$this->job( $id, $state );
			$result = $this->command( 'jobs retry ' . $id );
			self::assertSame( 0, $result->return_code, $result->stderr );
			$job = ( new Job_Repository() )->get( $id );
			self::assertSame( Job_State::QUEUED, $job->state() );
			self::assertSame( 0, $job->attempts() );
			self::assertNotNull( $job->dedupe_key() );
			$event = ( new Audit_Event_Repository() )->for_target( 'job', $id )[0];
			self::assertSame( $this->admin, $event->actor_user_id() );
			self::assertSame( 'job_retry', $event->action() );
		}
		self::assertSame( array(), $this->requests, 'Retry queues work; it does not call a provider.' );
	}

	public function test_retry_refuses_unknown_handlers_active_and_succeeded_jobs(): void {
		$this->job( 'job-unknown', Job_State::FAILED, 'unregistered' );
		self::assertSame( 1, $this->command( 'jobs retry job-unknown' )->return_code );
		foreach ( array( Job_State::QUEUED, Job_State::SUCCEEDED ) as $state ) {
			$this->job( 'job-' . $state, $state );
			self::assertSame( 1, $this->command( 'jobs retry job-' . $state )->return_code );
		}
		$this->job( 'job-claimed' );
		self::assertNotEmpty( ( new Job_Repository() )->claim( 'other-worker', $this->now(), gmdate( 'Y-m-d\TH:i:s\Z', time() + 120 ), 100 ) );
		self::assertSame( 1, $this->command( 'jobs retry job-claimed' )->return_code );
		self::assertSame( 1, $this->command( 'jobs cancel job-claimed --yes' )->return_code );
		self::assertSame( 'other-worker', ( new Job_Repository() )->get( 'job-claimed' )->lease_owner() );
	}

	public function test_retry_refuses_a_handler_that_is_not_retry_safe(): void {
		$this->job( 'job-unsafe', Job_State::DEAD, 'unsafe' );
		$handler = $this->createMock( Job_Handler::class );
		$handler->method( 'type' )->willReturn( 'unsafe' );
		$handler->method( 'retry_safe' )->willReturn( false );
		$handler->expects( self::never() )->method( 'handle' );
		$recovery = new Job_Recovery( new Job_Repository(), new Audit_Event_Repository(), new Database_Transaction(), new Random_Id_Generator(), new System_Clock(), array( $handler ) );
		try {
			$recovery->retry( new Campaign_Actor( $this->admin, true, true, true ), 'job-unsafe' );
			self::fail( 'Unsafe work was retried.' );
		} catch ( Operation_Refused ) {
			self::assertSame( Job_State::DEAD, ( new Job_Repository() )->get( 'job-unsafe' )->state() );
		}
	}

	public function test_retry_cannot_duplicate_active_work_for_the_same_target(): void {
		$old = $this->job( 'job-old', Job_State::FAILED );
		$active = Job::create( 'job-active', $old->type(), $old->target_type(), $old->target_id(), array(), 5, $this->now(), $this->now(), $old->type() . ':campaign:' . $old->target_id() );
		self::assertTrue( ( new Job_Repository() )->add( $active ) );
		self::assertSame( 1, $this->command( 'jobs retry job-old' )->return_code );
		self::assertSame( Job_State::FAILED, ( new Job_Repository() )->get( 'job-old' )->state() );
		self::assertSame( array(), ( new Audit_Event_Repository() )->for_target( 'job', 'job-old' ) );
	}

	public function test_cancel_requires_confirmation_and_records_a_failed_job_and_actor(): void {
		$this->job( 'job-cancel' );
		self::assertSame( 1, $this->command( 'jobs cancel job-cancel' )->return_code );
		self::assertSame( Job_State::QUEUED, ( new Job_Repository() )->get( 'job-cancel' )->state() );
		$result = $this->command( 'jobs cancel job-cancel --yes' );
		self::assertSame( 0, $result->return_code, $result->stderr );
		$job = ( new Job_Repository() )->get( 'job-cancel' );
		self::assertSame( Job_State::FAILED, $job->state() );
		self::assertNull( $job->dedupe_key() );
		self::assertSame( 'cancelled_by_operator', $job->last_error() );
		$event = ( new Audit_Event_Repository() )->for_target( 'job', 'job-cancel' )[0];
		self::assertSame( 'job_cancel', $event->action() );
		self::assertSame( $this->admin, $event->actor_user_id() );
	}

	public function test_a_worker_claim_wins_over_a_stale_cancel_request(): void {
		$expected = $this->job( 'job-racing' );
		$jobs = new Job_Repository();
		self::assertCount( 1, $jobs->claim( 'worker', $this->now(), gmdate( 'Y-m-d\TH:i:s\Z', time() + 120 ), 1 ) );
		self::assertFalse( $jobs->cancel_queued( $expected, $this->now() ) );
		self::assertSame( Job_State::CLAIMED, $jobs->get( $expected->id() )->state() );
	}

	public function test_recovery_rolls_back_if_the_audit_cannot_be_written(): void {
		$this->job( 'job-audit', Job_State::FAILED );
		$audits = $this->createMock( \CampaignBridge\Domain\Campaign\Audit_Event_Source::class );
		$audits->method( 'add' )->willReturn( false );
		$recovery = new Job_Recovery( new Job_Repository(), $audits, new Database_Transaction(), new Random_Id_Generator(), new System_Clock(), \CampaignBridge\Services\Job\Job_Factory::handlers() );
		try {
			$recovery->retry( new Campaign_Actor( $this->admin, true, true, true ), 'job-audit' );
			self::fail( 'Recovery committed without an audit.' );
		} catch ( Operation_Refused ) {
			self::assertSame( Job_State::FAILED, ( new Job_Repository() )->get( 'job-audit' )->state() );
		}
	}

	public function test_diagnostics_uses_stored_aggregate_values_without_requests_or_private_data(): void {
		$this->connection();
		$this->job( 'job-diagnostics' );
		$result = $this->command( 'diagnostics' );
		self::assertSame( 0, $result->return_code, $result->stderr );
		$data = json_decode( $result->stdout, true );
		self::assertTrue( $data['schema_current'] );
		self::assertSame( 1, $data['job_counts']['queued'] );
		self::assertTrue( $data['mailchimp']['connected'] );
		foreach ( array( $this->api_key(), 'subscriber@example.com', 'private-body', 'Authorization', 'Bearer', 'payload', 'account_details', 'ciphertext' ) as $private ) {
			self::assertStringNotContainsString( $private, $result->stdout );
		}
		self::assertSame( array(), $this->requests );
	}

	public function test_connection_check_is_a_read_only_ping_with_an_actor_audit(): void {
		$this->connection();
		$before = ( new Provider_Connection_Repository() )->get( 'mailchimp' )->to_array();
		$result = $this->command( 'connection check' );
		self::assertSame( 0, $result->return_code, $result->stderr );
		self::assertTrue( json_decode( $result->stdout, true )['verified'] );
		self::assertCount( 1, $this->requests );
		self::assertSame( 'GET', $this->requests[0]['method'] );
		self::assertStringEndsWith( '/ping', $this->requests[0]['url'] );
		self::assertSame( $before, ( new Provider_Connection_Repository() )->get( 'mailchimp' )->to_array() );
		$event = ( new Audit_Event_Repository() )->for_target( 'provider', 'mailchimp' )[0];
		self::assertSame( 'connection_check', $event->action() );
		self::assertSame( $this->admin, $event->actor_user_id() );
	}

	public function test_failed_connection_and_unexpected_errors_have_nonzero_redacted_output(): void {
		$this->connection();
		$this->status = 401;
		$this->reply = '{"detail":"Bearer private-secret subscriber@example.com raw-response"}';
		$result = $this->command( 'connection check' );
		self::assertSame( 1, $result->return_code );
		$this->throw_http = true;
		$exception = $this->command( 'connection check' );
		self::assertSame( 1, $exception->return_code );
		foreach ( array( $result, $exception ) as $output ) {
			foreach ( array( 'private-secret', 'subscriber@example.com', 'raw-response', $this->api_key() ) as $private ) {
				self::assertStringNotContainsString( $private, $output->stdout . $output->stderr );
			}
		}
	}

	public function test_reconcile_uses_the_real_workflow_lock_and_actor_audit(): void {
		$this->connection();
		$this->campaign();
		$this->reply = '{"type":"regular","status":"sent","emails_sent":1,"recipients":{"list_id":"abc123","segment_opts":{}}}';
		self::assertTrue( ( new Lock_Repository() )->acquire( 'campaign:campaign-one', 'other-worker', 'reconcile', gmdate( 'Y-m-d\TH:i:s\Z', time() + 300 ), $this->now() )->acquired );
		self::assertSame( 1, $this->command( 'campaign reconcile campaign-one' )->return_code );
		self::assertSame( array(), $this->requests );
		self::assertTrue( ( new Lock_Repository() )->release( 'campaign:campaign-one', 'other-worker' ) );
		$result = $this->command( 'campaign reconcile campaign-one' );
		self::assertSame( 0, $result->return_code, $result->stderr );
		self::assertSame( 'sent', ( new Campaign_Repository() )->get( 'campaign-one' )->state() );
		self::assertCount( 1, $this->requests );
		self::assertSame( 'GET', $this->requests[0]['method'] );
		$event = ( new Audit_Event_Repository() )->for_target( 'campaign', 'campaign-one' )[0];
		self::assertSame( 'campaign_reconcile', $event->action() );
		self::assertSame( $this->admin, $event->actor_user_id() );
	}

	public function test_reconcile_refuses_other_owners_even_with_delivery_capability(): void {
		$this->connection();
		$this->campaign();
		$user = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user_object = new \WP_User( $user );
		$user_object->add_cap( Capabilities::CREATE_CAMPAIGNS );
		$user_object->add_cap( Capabilities::SEND_CAMPAIGNS );
		wp_set_current_user( $user );
		self::assertSame( 1, $this->command( 'campaign reconcile campaign-one' )->return_code );
		self::assertSame( array(), $this->requests );
	}

	public function test_command_registration_is_wired_to_plugin_initialization_without_provider_work(): void {
		\WP_CLI::get_root_command()->remove_subcommand( 'campaignbridge' );
		// Reinitialize the composition root without duplicating its existing blocks.
		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block ) {
			if ( str_starts_with( $name, 'campaignbridge/' ) ) {
				unregister_block_type( $name );
			}
		}
		new \CampaignBridge\Plugin();
		self::assertSame( 0, $this->command( 'diagnostics' )->return_code );
		self::assertSame( array(), $this->requests );
	}

	private function command( string $command ): object {
		return \WP_CLI::runcommand( 'campaignbridge ' . $command, array( 'launch' => false, 'return' => 'all', 'exit_error' => false ) );
	}

	private function job( string $id, string $state = Job_State::QUEUED, string $type = 'reconcile_campaign' ): Job {
		$job = Job::from_array( array_merge(
			Job::create( $id, $type, 'campaign', $id . '-subscriber@example.com', array( 'body' => 'private-body' ), 5, $this->now(), $this->now(), $type . ':campaign:' . $id )->to_array(),
			array( 'state' => $state, 'dedupe_key' => Job_State::is_active( $state ) ? $type . ':campaign:' . $id : null, 'attempts' => Job_State::is_active( $state ) ? 0 : 5 )
		) );
		self::assertTrue( ( new Job_Repository() )->add( $job ) );

		return $job;
	}

	private function campaign(): void {
		$campaign = Campaign::from_array( array_merge( Campaign::create( 'campaign-one', $this->admin, 1, 'mailchimp', 'abc123', $this->now() )->to_array(), array( 'state' => 'sending' ) ) );
		self::assertTrue( ( new Campaign_Repository() )->add( $campaign ) );
		self::assertTrue( ( new Remote_Campaign_Reference_Repository() )->add( Remote_Campaign_Reference::from_array( array( 'schema_version' => 1, 'campaign_id' => 'campaign-one', 'provider' => 'mailchimp', 'remote_id' => 'mc0042', 'observed_state' => 'sending', 'cursor' => null, 'observed_at' => $this->now(), 'reconciled_at' => null ) ) ) );
	}

	private function connection(): void {
		$connection = Provider_Connection::create( 'mailchimp', Encryption::encrypt( $this->api_key() ), 'subscriber@example.com' )
			->with_verification( true, $this->now(), array( 'account_email' => 'subscriber@example.com', 'account_name' => 'private-body' ) );
		self::assertTrue( ( new Provider_Connection_Repository() )->save( $connection ) );
	}

	private function api_key(): string {
		return str_repeat( 'a1', 16 ) . '-us20';
	}

	private function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}

	private function clear_tables(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" );
		}
	}
}
