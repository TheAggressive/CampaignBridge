<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Form submission boundary tests.
 *
 * @package CampaignBridge\Tests\Security
 */

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Form_Builder;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Proves a form saves only for a POST with a valid nonce from a user who can
 * manage CampaignBridge, and that values are sanitized rather than discarded.
 */
final class Form_Request_Security_Test extends Test_Case {
	private const PREFIX = 'campaignbridge_form_security_';

	private string $form_id = '';

	public function setUp(): void {
		parent::setUp();
		$this->form_id = 'request_security_' . uniqid();
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		$_POST = array();
		unset( $_SERVER['HTTP_REFERER'] );
		foreach ( array( 'title', 'body', 'email' ) as $field ) {
			delete_option( self::PREFIX . $field );
		}
		parent::tearDown();
	}

	public function test_a_valid_request_saves_without_referer_or_admin_context(): void {
		$_SERVER['HTTP_REFERER'] = 'https://elsewhere.example/page';

		$form = $this->submit( array( 'title' => 'Spring sale' ), $this->nonce() );

		self::assertTrue( $form->valid() );
		self::assertSame( 'Spring sale', get_option( self::PREFIX . 'title' ) );
	}

	public function test_an_invalid_nonce_is_refused(): void {
		$form = $this->submit( array( 'title' => 'Forged' ), 'not-a-nonce' );

		self::assertTrue( $form->submitted() );
		self::assertFalse( $form->valid() );
		self::assertFalse( get_option( self::PREFIX . 'title' ) );
	}

	public function test_another_forms_nonce_is_refused(): void {
		$form = $this->submit( array( 'title' => 'Replayed' ), wp_create_nonce( 'campaignbridge_form_some_other_form' ) );

		self::assertFalse( $form->valid() );
		self::assertFalse( get_option( self::PREFIX . 'title' ) );
	}

	public function test_a_user_without_the_manage_capability_is_refused(): void {
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );

		$form = $this->submit( array( 'title' => 'Unauthorized' ), $this->nonce() );

		self::assertFalse( $form->valid() );
		self::assertFalse( get_option( self::PREFIX . 'title' ) );
	}

	public function test_a_get_request_is_never_processed(): void {
		$form = $this->submit( array( 'title' => 'Via GET' ), $this->nonce(), 'GET' );

		self::assertFalse( $form->valid() );
		self::assertFalse( get_option( self::PREFIX . 'title' ) );
	}

	public function test_rich_text_is_sanitized_by_kses_not_discarded(): void {
		$body = '<p>Hello <strong>team</strong></p><script>alert(1)</script><img src="x.png" onerror="alert(1)">'
			. str_repeat( '<p>Long content.</p>', 1000 );

		$this->submit(
			array(
				'title' => '<b>Plain</b> title',
				'body'  => $body,
			),
			$this->nonce()
		);

		$saved = (string) get_option( self::PREFIX . 'body' );
		self::assertStringContainsString( '<p>Hello <strong>team</strong></p>', $saved, 'Allowed markup is kept, even past 10 KB.' );
		self::assertStringNotContainsString( '<script', $saved );
		self::assertStringNotContainsString( 'onerror', $saved );
		self::assertSame( 'Plain title', get_option( self::PREFIX . 'title' ) );
	}

	public function test_ordinary_values_that_look_like_code_are_kept_as_text(): void {
		$this->submit( array( 'title' => 'Use "quotes" & <angle> brackets; select * from tips' ), $this->nonce() );

		self::assertSame( 'Use "quotes" & brackets; select * from tips', get_option( self::PREFIX . 'title' ) );
	}

	private function nonce(): string {
		return wp_create_nonce( 'campaignbridge_form_' . $this->form_id );
	}

	/** @param array<string, string> $values */
	private function submit( array $values, string $nonce, string $method = 'POST' ): Form_Builder {
		$form = Form::make( $this->form_id )
			->save_to_options( self::PREFIX )
			->text( 'title', 'Title' )->end()
			->wysiwyg( 'body', 'Body' )->end()
			->submit( 'Save' );

		$_SERVER['REQUEST_METHOD'] = $method;
		$_POST                     = array(
			$this->form_id               => $values,
			$this->form_id . '_wpnonce' => $nonce,
		);
		$form->render();

		return $form;
	}
}
