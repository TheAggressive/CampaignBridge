<?php
/**
 * Form REST Controller Test
 *
 * @package CampaignBridge\Tests\Unit\REST
 */

namespace CampaignBridge\Tests\Unit\REST;

use CampaignBridge\Admin\REST\Form_Rest_Controller;
use CampaignBridge\Admin\Core\Form_Registry;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Container;
use WP_REST_Request;
use WP_Error;

class Form_Rest_Controller_Test extends \WP_UnitTestCase {
	/**
	 * Form REST controller instance.
	 *
	 * @var Form_Rest_Controller
	 */
	private Form_Rest_Controller $controller;

	/**
	 * Form container mock.
	 *
	 * @var Form_Container|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $container_mock;

	/**
	 * Test admin user ID for cleanup
	 *
	 * @var int
	 */
	private int $test_admin_user_id;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up admin user for testing (Form_Rest_Controller requires admin privileges)
		$this->test_admin_user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($this->test_admin_user_id);

		// Create mock container
		$this->container_mock = $this->createMock( Form_Container::class );
		$this->controller     = new Form_Rest_Controller();
	}

	/**
	 * Test evaluate_conditions with valid form and data.
	 */
	public function _test_evaluate_conditions_success(): void {
		// Create mock form config
		$form_config = new Form_Config(
			array(
				'fields' => array(
					'enable_api'   => array(
						'type' => 'checkbox',
					),
					'api_provider' => array(
						'type'        => 'select',
						'conditional' => array(
							'type'       => 'show_when',
							'conditions' => array(
								array(
									'field'    => 'enable_api',
									'operator' => 'is_checked',
								),
							),
						),
					),
				),
			)
		);

		// Register form
		Form_Registry::register( 'test_form', $form_config );

		// Create controller
		$controller = new Form_Rest_Controller();

		// Create request
		$request = new WP_REST_Request( 'POST', '/campaignbridge/v1/forms/test_form/evaluate' );
		$request->set_param( 'form_id', 'test_form' );
		$request->set_param(
			'data',
			array(
				'enable_api' => '1',
			)
		);

		// Call the method (we need to use reflection since it's private)
		$result = $this->invoke_private_method( $controller, 'evaluate_conditions', array( $request ) );

		// Assert response is WP_REST_Response
		$this->assertInstanceOf( 'WP_REST_Response', $result );
		$data = $result->get_data();

		$this->assertArrayHasKey( 'fields', $data );
		$this->assertArrayHasKey( 'api_provider', $data['fields'] );
		$this->assertTrue( $data['fields']['api_provider']['visible'] );
		$this->assertFalse( $data['fields']['api_provider']['required'] );
	}

	/**
	 * Test evaluate_conditions with non-existent form.
	 */
	public function _test_evaluate_conditions_form_not_found(): void {
		$controller = new Form_Rest_Controller();

		$request = new WP_REST_Request( 'POST', '/campaignbridge/v1/forms/nonexistent/evaluate' );
		$request->set_param( 'form_id', 'nonexistent' );
		$request->set_param( 'data', array() );

		$result = $this->invoke_private_method( $controller, 'evaluate_conditions', array( $request ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'form_not_found', $result->get_error_code() );
		$this->assertEquals( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Test get_form_config method.
	 */
	public function _test_get_form_config_success(): void {
		// Clear registry first
		Form_Registry::clear();

		$form_config = new Form_Config(
			array(
				'form_id' => 'test_form',
				'fields'  => array(
					'test_field' => array(
						'type'  => 'text',
						'label' => 'Test Field',
					),
				),
			)
		);

		Form_Registry::register( 'test_form', $form_config );

		// Debug: Check if form is registered
		$this->assertTrue( Form_Registry::has( 'test_form' ), 'Form should be registered' );

		$controller = new Form_Rest_Controller();

		$result = $this->invoke_private_method( $controller, 'get_form_config', array( 'test_form' ) );

		$this->assertInstanceOf( Form_Config::class, $result, 'Should return Form_Config instance' );
		$this->assertEquals( 'test_form', $result->get( 'form_id' ) );
	}

	/**
	 * Test form ID validation.
	 */
	public function _test_validate_form_id(): void {
		$controller = new Form_Rest_Controller();

		$this->assertTrue( $this->invoke_private_method( $controller, 'validate_form_id', array( 'valid_form_123' ) ) );
		$this->assertTrue( $this->invoke_private_method( $controller, 'validate_form_id', array( 'another-valid-form' ) ) );
		$this->assertFalse( $this->invoke_private_method( $controller, 'validate_form_id', array( 'invalid form' ) ) );
		$this->assertFalse( $this->invoke_private_method( $controller, 'validate_form_id', array( '' ) ) );
	}

	/**
	 * Test form data validation.
	 */
	public function _test_validate_form_data(): void {
		$controller = new Form_Rest_Controller();

		$this->assertTrue( $this->invoke_private_method( $controller, 'validate_form_data', array( array( 'field' => 'value' ) ) ) );
		$this->assertFalse( $this->invoke_private_method( $controller, 'validate_form_data', array( 'not an array' ) ) );
		$this->assertFalse( $this->invoke_private_method( $controller, 'validate_form_data', array( null ) ) );
	}

	/**
	 * Test form data sanitization.
	 */
	public function test_sanitize_form_data(): void {
		$controller = new Form_Rest_Controller();

		$input = array(
			'normal_field' => 'normal value',
			'script_field' => '<script>alert("xss")</script>',
			'nested'       => array(
				'inner' => 'inner value',
			),
		);

		$result = $this->invoke_private_method( $controller, 'sanitize_form_data', array( $input ) );

		$this->assertEquals( 'normal value', $result['normal_field'] );
		$this->assertEquals( '', $result['script_field'] ); // Should be sanitized
		$this->assertEquals( array( 'inner' => 'inner value' ), $result['nested'] );
	}

	/**
	 * Test permission callback.
	 */
	public function test_can_access_form(): void {
		$controller = new Form_Rest_Controller();

		// Test with admin user (should have access)
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( $this->invoke_private_method( $controller, 'can_access_form', array() ) );

		// Test with subscriber user (should not have access)
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( $this->invoke_private_method( $controller, 'can_access_form', array() ) );

		// Test with logged-out user (should not have access)
		wp_set_current_user( 0 );
		$this->assertFalse( is_user_logged_in() );
		$this->assertFalse( $this->invoke_private_method( $controller, 'can_access_form', array() ) );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Form_Registry::clear();

		// Clean up test admin user
		if (isset($this->test_admin_user_id)) {
			wp_delete_user($this->test_admin_user_id);
		}
	}

	/**
	 * Helper method to invoke private methods.
	 */
	private function invoke_private_method( $object, $method_name, $args = array() ) {
		$reflection = new \ReflectionClass( $object );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $args );
	}
}
