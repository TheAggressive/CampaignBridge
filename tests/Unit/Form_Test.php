<?php
/**
 * Unit tests for Form class.
 *
 * @package CampaignBridge\Tests\Unit
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Container;
use CampaignBridge\Admin\Core\Forms\Form_Notice_Handler;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Custom container for testing that allows overriding services
 */
class Test_Form_Container extends Form_Container {
	private array $overrides = array();

	public function set_override( string $key, $service ): void {
		$this->overrides[ $key ] = $service;
	}

	public function get( string $key ) {
		if ( isset( $this->overrides[ $key ] ) ) {
			return $this->overrides[ $key ];
		}
		return parent::get( $key );
	}
}

/**
 * Test Form functionality.
 */
class Form_Test extends Test_Case {

	/**
	 * Form container instance.
	 *
	 * @var Test_Form_Container
	 */
	private Test_Form_Container $container;

	/**
	 * Form config instance.
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->container = new Test_Form_Container();
		$this->config    = new Form_Config();
	}

	/**
	 * Test that custom save method without callback triggers warning
	 */
	public function test_custom_save_method_without_callback_triggers_warning(): void {
		// Create a mock notice handler to capture the warning call
		$notice_handler_mock = $this->createMock( Form_Notice_Handler::class );

		// Expect trigger_warning to be called with the correct message
		$notice_handler_mock->expects( $this->once() )
			->method( 'trigger_warning' )
			->with( $this->stringContains( 'configured to use custom saving but no save callback is provided' ) );

		// Override the notice handler in our test container
		$this->container->set_override( 'form_notice_handler', $notice_handler_mock );

		// Create form config with custom save method but no callback
		$this->config->set( 'form_id', 'test_form' );
		$this->config->set( 'save_method', 'custom' );
		$this->config->set( 'hooks', array() ); // No save_data hook

		// Get the config array from Form_Config using reflection
		$config_property = new \ReflectionProperty( Form_Config::class, 'config' );
		$config_property->setAccessible( true );
		$config_array = $config_property->getValue( $this->config );

		// Create a Form instance using Form::make() which creates with default container,
		// then manually set our test container
		$form = Form::make( 'test_form', $config_array );

		// Use reflection to set our test container
		$container_property = new \ReflectionProperty( Form::class, 'container' );
		$container_property->setAccessible( true );
		$container_property->setValue( $form, $this->container );

		// Force initialization to trigger validation by calling render
		// Capture output to prevent PHPUnit strict mode issues
		ob_start();
		$form->render();
		ob_end_clean();
	}

	/**
	 * Test that custom save method with callback does not trigger warning
	 */
	public function test_custom_save_method_with_callback_does_not_trigger_warning(): void {
		// Create a mock notice handler
		$notice_handler_mock = $this->createMock( Form_Notice_Handler::class );

		// Expect trigger_warning to NOT be called
		$notice_handler_mock->expects( $this->never() )
			->method( 'trigger_warning' );

		// Override the notice handler in our test container
		$this->container->set_override( 'form_notice_handler', $notice_handler_mock );

		// Create form config with custom save method AND callback
		$this->config->set( 'form_id', 'test_form' );
		$this->config->set( 'save_method', 'custom' );
		$this->config->add_hook(
			'save_data',
			function ( $data ) {
				return true;
			}
		);

		// Get the config array from Form_Config using reflection
		$config_property = new \ReflectionProperty( Form_Config::class, 'config' );
		$config_property->setAccessible( true );
		$config_array = $config_property->getValue( $this->config );

		// Create a Form instance using Form::make() which creates with default container,
		// then manually set our test container
		$form = Form::make( 'test_form', $config_array );

		// Use reflection to set our test container
		$container_property = new \ReflectionProperty( Form::class, 'container' );
		$container_property->setAccessible( true );
		$container_property->setValue( $form, $this->container );

		// Force initialization to trigger validation by calling render
		// Capture output to prevent PHPUnit strict mode issues
		ob_start();
		$form->render();
		ob_end_clean();
	}

	/**
	 * Test that form API works without ->end() calls
	 */
	public function test_form_api_works_without_end_calls(): void {
		// Create a test form using the new fluent API without ->end().
		$form = Form::make( 'fluent_test' )
			->text( 'name', 'Full Name' )->required()
			->email( 'email', 'Email Address' )->required()
			->save_to_options( 'test_' )
			->success( 'Data saved successfully!' )
			->submit( 'Save Data' );

		// Verify the form was created correctly.
		$this->assertInstanceOf( \CampaignBridge\Admin\Core\Form_Builder::class, $form );

		// Verify fields were added.
		$fields = $form->get_fields();
		$this->assertArrayHasKey( 'name', $fields );
		$this->assertArrayHasKey( 'email', $fields );

		// Verify field configurations.
		$this->assertEquals( 'text', $fields['name']['type'] );
		$this->assertEquals( 'Full Name', $fields['name']['label'] );
		$this->assertTrue( $fields['name']['required'] );

		$this->assertEquals( 'email', $fields['email']['type'] );
		$this->assertEquals( 'Email Address', $fields['email']['label'] );
		$this->assertTrue( $fields['email']['required'] );

		// Test encrypted field.
		$form2 = Form::make( 'encrypted_test' )
			->encrypted( 'api_key', 'API Key' )->context( 'api_key' )
			->save_to_options( 'test_' )
			->submit( 'Save' );

		$fields2 = $form2->get_fields();
		$this->assertArrayHasKey( 'api_key', $fields2 );
		$this->assertEquals( 'encrypted', $fields2['api_key']['type'] );
		$this->assertEquals( 'api_key', $fields2['api_key']['context'] );
	}

	/**
	 * Test that non-custom save methods do not trigger warning
	 */
	public function test_non_custom_save_methods_do_not_trigger_warning(): void {
		$save_methods = array( 'options', 'post_meta', 'settings' );

		foreach ( $save_methods as $save_method ) {
			// Create a mock notice handler
			$notice_handler_mock = $this->createMock( Form_Notice_Handler::class );

			// Expect trigger_warning to NOT be called
			$notice_handler_mock->expects( $this->never() )
				->method( 'trigger_warning' );

			// Override the notice handler in our test container
			$this->container->set_override( 'form_notice_handler', $notice_handler_mock );

			// Create form config with non-custom save method
			$this->config->set( 'form_id', 'test_form_' . $save_method );
			$this->config->set( 'save_method', $save_method );

			// Get the config array from Form_Config using reflection
			$config_property = new \ReflectionProperty( Form_Config::class, 'config' );
			$config_property->setAccessible( true );
			$config_array = $config_property->getValue( $this->config );

			// Create a Form instance using Form::make() which creates with default container,
			// then manually set our test container
			$form = Form::make( 'test_form_' . $save_method, $config_array );

			// Use reflection to set our test container
			$container_property = new \ReflectionProperty( Form::class, 'container' );
			$container_property->setAccessible( true );
			$container_property->setValue( $form, $this->container );

			// Force initialization to trigger validation by calling render
			// Capture output to prevent PHPUnit strict mode issues
			ob_start();
			$form->render();
			ob_end_clean();
		}
	}
}
