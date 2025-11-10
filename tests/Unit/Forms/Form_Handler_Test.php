<?php
/**
 * Form Handler Test
 *
 * @package CampaignBridge\Tests\Unit\Forms
 */

namespace CampaignBridge\Tests\Unit\Forms;

use CampaignBridge\Admin\Core\Forms\Form_Handler;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Admin\Core\Forms\Form_Validator;
use CampaignBridge\Admin\Core\Forms\Form_Notice_Handler;
use CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager;

/**
 * Form Handler Test Class
 */
class Form_Handler_Test extends \WP_UnitTestCase {
	/**
	 * Form config instance
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

	/**
	 * Form security instance
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Form validator instance
	 *
	 * @var Form_Validator
	 */
	private Form_Validator $validator;

	/**
	 * Notice handler instance
	 *
	 * @var Form_Notice_Handler
	 */
	private Form_Notice_Handler $notice_handler;

	/**
	 * Test admin user ID for cleanup
	 *
	 * @var int
	 */
	private int $test_admin_user_id;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up admin user for testing (Form_Security requires admin privileges)
		$this->test_admin_user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($this->test_admin_user_id);

		$this->config         = new Form_Config();
		$this->security       = new Form_Security( 'test_form' );
		$this->validator      = new Form_Validator();
		$this->notice_handler = new Form_Notice_Handler();
	}

	/**
	 * Test conditional data filtering
	 */
	public function test_filter_conditional_field_data(): void {
		$conditional_manager = $this->createMock( Form_Conditional_Manager::class );
		$conditional_manager->method( 'should_show_field' )
			->willReturnCallback(
				function ( $field_id ) {
					return $field_id === 'visible_field'; // Only visible_field should be shown
				}
			);

		$handler = new Form_Handler(
			null, // form
			$this->config,
			array( // fields
				'visible_field' => array( 'type' => 'text' ),
				'hidden_field'  => array(
					'type'        => 'text',
					'conditional' => array( 'type' => 'show_when' ),
				),
			),
			$this->security,
			$this->validator,
			$this->notice_handler
		);

		$handler->set_conditional_manager( $conditional_manager );

		$form_data = array(
			'visible_field' => 'visible value',
			'hidden_field'  => 'hidden value',
			'regular_field' => 'regular value',
		);

		$filtered_data = $this->invoke_private_method( $handler, 'filter_conditional_field_data', array( $form_data ) );

		// Hidden field data should be filtered out
		$this->assertArrayHasKey( 'visible_field', $filtered_data );
		$this->assertArrayHasKey( 'regular_field', $filtered_data );
		$this->assertArrayNotHasKey( 'hidden_field', $filtered_data );
		$this->assertEquals( 'visible value', $filtered_data['visible_field'] );
		$this->assertEquals( 'regular value', $filtered_data['regular_field'] );
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up test admin user
		if (isset($this->test_admin_user_id)) {
			wp_delete_user($this->test_admin_user_id);
		}
	}

	/**
	 * Helper method to invoke private methods
	 */
	private function invoke_private_method( $object, $method_name, $args = array() ) {
		$reflection = new \ReflectionClass( $object );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $args );
	}
}
