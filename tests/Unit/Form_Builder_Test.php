<?php
/**
 * Unit tests for Form_Builder class.
 *
 * @package CampaignBridge\Tests\Unit
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Admin\Core\Form_Builder;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_Field_Builder;
use CampaignBridge\Admin\Core\Forms\Form_Container;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test Form_Builder functionality.
 */
class Form_Builder_Test extends Test_Case {

	/**
	 * Form builder instance.
	 *
	 * @var Form_Builder
	 */
	private Form_Builder $builder;

	/**
	 * Form config instance.
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

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

		// Set up admin user for testing (some form operations may require admin privileges)
		$this->test_admin_user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($this->test_admin_user_id);

		$form          = Form::make( 'test_form' );
		$this->config  = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$this->builder = $this->get_reflection_property( $form, 'builder' )->getValue( $form );
	}

	/**
	 * Test that email method exists and returns Form_Field_Builder.
	 */
	public function test_email_method_exists(): void {
		$result = $this->builder->email( 'test_email', 'Test Email' );

		$this->assertInstanceOf( Form_Field_Builder::class, $result );
	}

	/**
	 * Test that email field is properly configured.
	 */
	public function test_email_field_configuration(): void {
		$this->builder->email( 'test_email', 'Test Email' )
			->required()
			->placeholder( 'test@example.com' )
			->end();

		$field_config = $this->config->get_field( 'test_email' );

		$this->assertEquals( 'email', $field_config['type'] );
		$this->assertEquals( 'Test Email', $field_config['label'] );
		$this->assertTrue( $field_config['required'] );
		$this->assertEquals( 'test@example.com', $field_config['placeholder'] );
	}

	/**
	 * Test method chaining with end().
	 */
	public function test_method_chaining_with_end(): void {
		$result = $this->builder->text( 'field1', 'Field 1' )
			->required()
			->end()
			->email( 'field2', 'Field 2' )
			->placeholder( 'test@example.com' )
			->end();

		$this->assertInstanceOf( Form_Builder::class, $result );

		// Verify both fields were created
		$this->assertNotNull( $this->config->get_field( 'field1' ) );
		$this->assertNotNull( $this->config->get_field( 'field2' ) );
	}

	/**
	 * Test that all field types return Form_Field_Builder.
	 */
	public function test_all_field_types_return_form_field_builder(): void {
		$field_types = array( 'text', 'email', 'number', 'textarea', 'select', 'radio', 'checkbox', 'switch' );

		foreach ( $field_types as $type ) {
			$result = $this->builder->{$type}( 'test_' . $type, 'Test ' . ucfirst( $type ) );
			$this->assertInstanceOf( Form_Field_Builder::class, $result, "Field type '{$type}' should return Form_Field_Builder" );
		}
	}

	/**
	 * Test save_to_custom method sets custom save method and hook
	 */
	public function test_save_to_custom_sets_custom_save_method_and_hook(): void {
		$callback = function ( $data ) {
			return true;
		};

		$result = $this->builder->save_to_custom( $callback );

		$this->assertInstanceOf( Form_Builder::class, $result );

		// Verify save method was set
		$this->assertEquals( 'custom', $this->config->get( 'save_method' ) );

		// Verify hook was set
		$hooks = $this->config->get( 'hooks', array() );
		$this->assertArrayHasKey( 'save_data', $hooks );
		$this->assertSame( $callback, $hooks['save_data'] );
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
}
