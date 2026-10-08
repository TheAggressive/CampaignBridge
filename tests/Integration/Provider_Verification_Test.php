<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment
/**
 * Connection checks record what Mailchimp proved about the stored key.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Controllers\Settings_Controller;
use CampaignBridge\Admin\Onboarding_Checklist;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_Error;

/** Proves only a definite Mailchimp answer changes the stored verification state. */
final class Provider_Verification_Test extends Test_Case {
	/** @var int|WP_Error Scripted ping status, or a transport failure. */
	private int|WP_Error $reply = 200;

	private int $pings = 0;

	/** @var callable|null */
	private $filter = null;

	private static function api_key(): string {
		return str_repeat( 'feed', 8 ) . '-us7';
	}

	public function setUp(): void {
		parent::setUp();
		$this->filter = function ( mixed $preempt, array $args, string $url ): array|WP_Error {
			self::assertStringEndsWith( '/3.0/ping', $url );
			++$this->pings;

			return $this->reply instanceof WP_Error ? $this->reply : array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => $this->reply,
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->filter, 10, 3 );
		( new Provider_Connection_Repository() )->save( Provider_Connection::create( 'mailchimp', Encryption::encrypt( self::api_key() ), 'list-1' ) );
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', $this->filter );
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		$this->forget_check();
		parent::tearDown();
	}

	public function test_a_successful_check_marks_the_connection_verified(): void {
		$this->check();

		$connection = $this->stored();
		self::assertTrue( $connection->is_verified() );
		self::assertNotNull( $connection->last_verified_at() );
		self::assertNotFalse( strtotime( (string) $connection->last_verified_at() ) );
		self::assertSame( 'list-1', $connection->audience_id(), 'Verification keeps the chosen audience.' );
		self::assertTrue( array_column( Onboarding_Checklist::for_user( get_current_user_id() )['steps'], 'done', 'id' )['provider'] );
	}

	public function test_a_refused_credential_is_recorded_as_unverified(): void {
		$this->check();
		$this->forget_check();
		$this->reply = 401;

		$this->check();

		self::assertFalse( $this->stored()->is_verified() );
	}

	public function test_a_timeout_proves_nothing_and_keeps_the_previous_state(): void {
		$this->check();
		$verified_at = $this->stored()->last_verified_at();
		$this->forget_check();
		$this->reply = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );

		$this->check();

		self::assertSame( 2, $this->pings );
		self::assertTrue( $this->stored()->is_verified() );
		self::assertSame( $verified_at, $this->stored()->last_verified_at() );
	}

	public function test_a_cached_check_does_not_contact_mailchimp_again(): void {
		$this->check();
		$this->check();

		self::assertSame( 1, $this->pings );
	}

	private function check(): void {
		( new \ReflectionMethod( Settings_Controller::class, 'get_mailchimp_connection' ) )->invoke( new Settings_Controller() );
	}

	private function forget_check(): void {
		Storage::delete_transient( 'mailchimp_connection_' . hash( 'sha256', self::api_key() ) );
	}

	private function stored(): Provider_Connection {
		$connection = ( new Provider_Connection_Repository() )->get( 'mailchimp' );
		self::assertNotNull( $connection );

		return $connection;
	}
}
