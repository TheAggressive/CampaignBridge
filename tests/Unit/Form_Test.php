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
use CampaignBridge\Admin\Core\Forms\Form_Handler;
use CampaignBridge\Admin\Core\Forms\Form_Notice_Handler;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Admin\Core\Forms\Form_Validator;
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

	/**
	 * Test that encrypted form fields properly encrypt submitted values.
	 */
	public function test_encrypted_field_encryption(): void {
		// Skip if encryption is not available
		if ( ! class_exists( '\CampaignBridge\Core\Encryption' ) ) {
			$this->markTestSkipped( 'Encryption class not available' );
		}

		// Create a Form_Handler instance using our test container
		$form           = $this->createMock( Form::class );
		$security       = $this->createMock( Form_Security::class );
		$validator      = $this->createMock( Form_Validator::class );
		$notice_handler = $this->createMock( Form_Notice_Handler::class );

		$handler = new Form_Handler(
			$form,
			$this->config,
			array(),
			$security,
			$validator,
			$notice_handler
		);

		// Test data
		$plain_text_value = 'super_secret_api_key_12345';
		$field_config     = array(
			'type'    => 'encrypted',
			'context' => 'api_key',
		);

		// Test that plain text gets encrypted
		$sanitized_value = $this->invoke_private_method( $handler, 'sanitize_field_value', array( $plain_text_value, $field_config ) );

		// Verify the value is encrypted
		$this->assertNotEquals( $plain_text_value, $sanitized_value );
		$this->assertTrue( \CampaignBridge\Core\Encryption::is_encrypted_value( $sanitized_value ) );

		// Verify we can decrypt it back
		$decrypted_value = \CampaignBridge\Core\Encryption::decrypt( $sanitized_value );
		$this->assertEquals( $plain_text_value, $decrypted_value );

		// Test that already encrypted values are not double-encrypted
		$already_encrypted = \CampaignBridge\Core\Encryption::encrypt( 'another_secret' );
		$sanitized_again   = $this->invoke_private_method( $handler, 'sanitize_field_value', array( $already_encrypted, $field_config ) );

		// Should return the same encrypted value
		$this->assertEquals( $already_encrypted, $sanitized_again );

		// Test empty values are handled correctly
		$empty_sanitized = $this->invoke_private_method( $handler, 'sanitize_field_value', array( '', $field_config ) );
		$this->assertEquals( '', $empty_sanitized );

		// Test masked values (containing •) get encrypted since they're not already encrypted
		$masked_value     = '••••••••••••abcd';
		$masked_sanitized = $this->invoke_private_method( $handler, 'sanitize_field_value', array( $masked_value, $field_config ) );

		// Masked values should be encrypted because they're plain text
		$this->assertNotEquals( $masked_value, $masked_sanitized );
		$this->assertTrue( \CampaignBridge\Core\Encryption::is_encrypted_value( $masked_sanitized ) );

		// Should be able to decrypt back
		$decrypted_masked = \CampaignBridge\Core\Encryption::decrypt( $masked_sanitized );
		$this->assertEquals( $masked_value, $decrypted_masked );
	}

	/**
	 * Helper method to invoke private methods for testing.
	 *
	 * @param object $object     The object instance.
	 * @param string $method_name The private method name.
	 * @param array  $args       Arguments to pass to the method.
	 * @return mixed The method result.
	 */
	private function invoke_private_method( object $object, string $method_name, array $args = array() ): mixed {
		$reflection = new \ReflectionClass( $object );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $args );
	}
}
