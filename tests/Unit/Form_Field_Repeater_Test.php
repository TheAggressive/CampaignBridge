<?php
/**
 * Unit tests for Form_Field_Repeater class.
 *
 * @package CampaignBridge\Tests\Unit
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Form_Builder;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Field_Repeater;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test Form_Field_Repeater functionality.
 */
class Form_Field_Repeater_Test extends Test_Case {

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
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$form          = Form::make( 'test_form' );
		$this->config  = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$this->builder = $this->get_reflection_property( $form, 'builder' )->getValue( $form );
	}

	/**
	 * Test repeater with 2 arguments creates unchecked fields (stateless mode).
	 */
	public function test_repeater_stateless_creates_unchecked_fields(): void {
		$form    = Form::make( 'test_form' );
		$choices = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
			'opt3' => 'Option 3',
		);

		$form->repeater( 'field', $choices )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert all three fields were created.
		$this->assertArrayHasKey( 'field___opt1', $fields );
		$this->assertArrayHasKey( 'field___opt2', $fields );
		$this->assertArrayHasKey( 'field___opt3', $fields );

		// Assert field types are switch.
		$this->assertEquals( 'switch', $fields['field___opt1']['type'] );
		$this->assertEquals( 'switch', $fields['field___opt2']['type'] );
		$this->assertEquals( 'switch', $fields['field___opt3']['type'] );

		// Assert all fields are unchecked (no default or default is false).
		$this->assertFalse( $fields['field___opt1']['default'] ?? false );
		$this->assertFalse( $fields['field___opt2']['default'] ?? false );
		$this->assertFalse( $fields['field___opt3']['default'] ?? false );
	}

	/**
	 * Test repeater with persistent data checks matching fields (state-based mode).
	 */
	public function test_repeater_with_persistent_data_checks_matching_fields(): void {
		$form       = Form::make( 'test_form' );
		$choices    = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
			'opt3' => 'Option 3',
		);
		$persistent = array( 'opt1', 'opt3' );

		$form->repeater( 'field', $choices, $persistent )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert opt1 is checked.
		$this->assertTrue( $fields['field___opt1']['default'] ?? false );

		// Assert opt2 is unchecked.
		$this->assertFalse( $fields['field___opt2']['default'] ?? false );

		// Assert opt3 is checked.
		$this->assertTrue( $fields['field___opt3']['default'] ?? false );
	}

	/**
	 * Test repeater default method sets specific choice as checked.
	 */
	public function test_repeater_default_method_sets_specific_choice(): void {
		$form    = Form::make( 'test_form' );
		$choices = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);

		$form->repeater( 'field', $choices )->default( 'opt2' )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert opt1 is unchecked.
		$this->assertFalse( $fields['field___opt1']['default'] ?? false );

		// Assert opt2 is checked.
		$this->assertTrue( $fields['field___opt2']['default'] ?? false );
	}

	/**
	 * Test repeater ignores stale persistent data.
	 */
	public function test_repeater_ignores_stale_persistent_data(): void {
		$form       = Form::make( 'test_form' );
		$choices    = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);
		$persistent = array( 'opt1', 'opt_removed', 'opt_old' ); // Contains removed options.

		$form->repeater( 'field', $choices, $persistent )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert only opt1 and opt2 fields created.
		$this->assertArrayHasKey( 'field___opt1', $fields );
		$this->assertArrayHasKey( 'field___opt2', $fields );

		// Assert no fields for opt_removed or opt_old.
		$this->assertArrayNotHasKey( 'field[opt_removed]', $fields );
		$this->assertArrayNotHasKey( 'field[opt_old]', $fields );

		// Assert opt1 is checked.
		$this->assertTrue( $fields['field___opt1']['default'] ?? false );

		// Assert opt2 is unchecked.
		$this->assertFalse( $fields['field___opt2']['default'] ?? false );
	}

	/**
	 * Test repeater creates switch fields.
	 */
	public function test_repeater_creates_switch_fields(): void {
		$form    = Form::make( 'test_form' );
		$choices = array( 'opt1' => 'Option 1' );

		$form->repeater( 'field', $choices )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		$this->assertEquals( 'switch', $fields['field___opt1']['type'] );
	}

	/**
	 * Test repeater creates checkbox fields.
	 */
	public function test_repeater_creates_checkbox_fields(): void {
		$form    = Form::make( 'test_form' );
		$choices = array( 'opt1' => 'Option 1' );

		$form->repeater( 'field', $choices )->checkbox();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		$this->assertEquals( 'checkbox', $fields['field___opt1']['type'] );
	}

	/**
	 * Test repeater creates radio fields.
	 */
	public function test_repeater_creates_radio_fields(): void {
		$form    = Form::make( 'test_form' );
		$choices = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);

		$form->repeater( 'field', $choices )->radio();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Radio creates ONE field with options (not multiple individual fields).
		$this->assertEquals( 'radio', $fields['field']['type'] );
		$this->assertEquals( $choices, $fields['field']['options'] );
	}

	/**
	 * Test repeater throws exception on empty field ID.
	 */
	public function test_repeater_throws_exception_on_empty_field_id(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Field ID cannot be empty' );

		$form    = Form::make( 'test_form' );
		$choices = array( 'opt1' => 'Option 1' );

		$form->repeater( '', $choices )->switch();
	}

	/**
	 * Test repeater throws exception on empty choices.
	 */
	public function test_repeater_throws_exception_on_empty_choices(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Choices array cannot be empty' );

		$form = Form::make( 'test_form' );

		$form->repeater( 'field', array() )->switch();
	}

	/**
	 * Test repeater normalizes string persistent data to array.
	 */
	public function test_repeater_normalizes_string_persistent_data(): void {
		$form    = Form::make( 'test_form' );
		$choices = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);

		$form->repeater( 'field', $choices, 'opt1' )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert opt1 is checked (string was normalized to ['opt1']).
		$this->assertTrue( $fields['field___opt1']['default'] ?? false );

		// Assert opt2 is unchecked.
		$this->assertFalse( $fields['field___opt2']['default'] ?? false );
	}

	/**
	 * Test repeater throws exception on invalid choice label.
	 */
	public function test_repeater_throws_exception_on_invalid_choice_label(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Choice label for key' );

		$form    = Form::make( 'test_form' );
		$choices = array( 'opt1' => array( 'invalid', 'array' ) );

		$form->repeater( 'field', $choices )->switch();
	}

	/**
	 * Test repeater fields appear in form config.
	 */
	public function test_repeater_fields_appear_in_form_config(): void {
		$form    = Form::make( 'test_form' );
		$choices = array( 'opt1' => 'Option 1' );

		$form->repeater( 'field', $choices )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert field exists in config.
		$this->assertArrayHasKey( 'field___opt1', $fields );

		// Assert field type is correct.
		$this->assertEquals( 'switch', $fields['field___opt1']['type'] );

		// Assert label is correct.
		$this->assertEquals( 'Option 1', $fields['field___opt1']['label'] );
	}

	/**
	 * Test repeater returns Form_Builder for chaining.
	 */
	public function test_repeater_returns_form_builder_for_chaining(): void {
		$form    = Form::make( 'test_form' );
		$choices = array( 'opt1' => 'Option 1' );

		$result = $form->repeater( 'field', $choices )->switch();

		// Assert result is Form_Builder instance.
		$this->assertInstanceOf( Form_Builder::class, $result );

		// Test chaining works.
		$result->text( 'other_field', 'Other Field' );

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert both fields exist.
		$this->assertArrayHasKey( 'field___opt1', $fields );
		$this->assertArrayHasKey( 'other_field', $fields );
	}

	/**
	 * Test repeater real-world post types use case.
	 */
	public function test_repeater_post_types_use_case(): void {
		// Simulate real post-types.php scenario.
		$all_post_types = array(
			'post'    => 'Posts',
			'page'    => 'Pages',
			'product' => 'Products',
		);
		$enabled_types  = array( 'post', 'page' );

		$form = Form::make( 'post_types' );
		$form->save_to_options( 'campaignbridge_' )
			->repeater( 'included_post_types', $all_post_types, $enabled_types )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert post and page are checked.
		$this->assertTrue( $fields['included_post_types___post']['default'] ?? false );
		$this->assertTrue( $fields['included_post_types___page']['default'] ?? false );

		// Assert product is unchecked.
		$this->assertFalse( $fields['included_post_types___product']['default'] ?? false );

		// Assert save method is options.
		$this->assertEquals( 'options', $config->get( 'save_method' ) );
	}

	/**
	 * Test persistent data priority over default.
	 */
	public function test_persistent_data_has_priority_over_default(): void {
		$form       = Form::make( 'test_form' );
		$choices    = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);
		$persistent = array( 'opt1' );

		// Set default to opt2, but persistent data has opt1.
		$form->repeater( 'field', $choices, $persistent )->default( 'opt2' )->switch();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Assert opt1 is checked (from persistent data, not default).
		$this->assertTrue( $fields['field___opt1']['default'] ?? false );

		// Assert opt2 is unchecked (persistent data overrides default).
		$this->assertFalse( $fields['field___opt2']['default'] ?? false );
	}

	/**
	 * Test repeater with select field type.
	 */
	public function test_repeater_creates_select_fields(): void {
		$form    = Form::make( 'test_form' );
		$choices = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);

		$form->repeater( 'field', $choices )->select();

		$config = $this->get_reflection_property( $form, 'config' )->getValue( $form );
		$fields = $config->get( 'fields', array() );

		// Select creates ONE field with options (not multiple individual fields).
		$this->assertEquals( 'select', $fields['field']['type'] );
		$this->assertEquals( $choices, $fields['field']['options'] );
	}

	/**
	 * Test form data merge logic for different field types.
	 */
	public function test_form_data_merge_logic(): void {
		$form = Form::make( 'merge_test' );
		$form->text( 'regular_field', 'Regular' )
			->encrypted( 'encrypted_field', 'Encrypted' )
			->checkbox( 'checkbox_field', 'Checkbox' )
			->switch( 'switch_field', 'Switch' );

		// Get the form handler to test merge logic
		$handler = $form->get_handler();

		// Test data scenarios
		$existing_data = array(
			'regular_field'   => 'old_value',
			'encrypted_field' => \CampaignBridge\Core\Encryption::encrypt( 'old_encrypted' ),
			'checkbox_field'  => true,
			'switch_field'    => true,
		);

		$field_config = array(
			'regular_field'   => array( 'type' => 'text' ),
			'encrypted_field' => array( 'type' => 'encrypted' ),
			'checkbox_field'  => array( 'type' => 'checkbox' ),
			'switch_field'    => array( 'type' => 'switch' ),
		);

		// Test 1: Regular field - submitted value takes priority
		$method = $this->get_reflection_method( $handler, 'merge_field_values' );
		$result = $method->invoke( $handler, 'new_value', $existing_data['regular_field'], $field_config['regular_field'] );
		$this->assertEquals( 'new_value', $result );

		// Test 2: Encrypted field - preserves encrypted value when empty submitted
		$result = $method->invoke( $handler, '', $existing_data['encrypted_field'], $field_config['encrypted_field'] );
		$this->assertEquals( $existing_data['encrypted_field'], $result );

		// Test 3: Encrypted field - uses submitted value when not empty
		$result = $method->invoke( $handler, 'new_encrypted', $existing_data['encrypted_field'], $field_config['encrypted_field'] );
		$this->assertEquals( 'new_encrypted', $result );

		// Test 4: Checkbox/Switch - returns false when not submitted
		$result = $method->invoke( $handler, null, $existing_data['checkbox_field'], $field_config['checkbox_field'] );
		$this->assertFalse( $result );

		// Test 5: Checkbox/Switch - uses submitted value when submitted
		$result = $method->invoke( $handler, '1', $existing_data['switch_field'], $field_config['switch_field'] );
		$this->assertEquals( '1', $result );
	}

	/**
	 * Test field-specific merge logic for different field types.
	 */
	public function test_field_specific_merge_logic(): void {
		$validator = new \CampaignBridge\Admin\Core\Forms\Form_Validator();

		// Test switch field merge logic
		$switch_field = new \CampaignBridge\Admin\Core\Forms\Form_Field_Switch( array( 'type' => 'switch' ), $validator );

		// Switch not submitted (unchecked) should return false
		$result = $switch_field->merge_values( null, true );
		$this->assertFalse( $result );

		// Switch submitted as checked should return submitted value
		$result = $switch_field->merge_values( '1', false );
		$this->assertEquals( '1', $result );

		// Test checkbox field merge logic
		$checkbox_field = new \CampaignBridge\Admin\Core\Forms\Form_Field_Checkbox( array( 'type' => 'checkbox' ), $validator );

		// Checkbox not submitted (unchecked) should return false
		$result = $checkbox_field->merge_values( null, true );
		$this->assertFalse( $result );

		// Checkbox submitted as checked should return submitted value
		$result = $checkbox_field->merge_values( '1', false );
		$this->assertEquals( '1', $result );

		// Test encrypted field merge logic
		$encrypted_field = new \CampaignBridge\Admin\Core\Forms\Form_Field_Encrypted( array( 'type' => 'encrypted' ), $validator );
		$encrypted_value = \CampaignBridge\Core\Encryption::encrypt( 'test_value' );

		// Encrypted field with empty submission should preserve existing value
		$result = $encrypted_field->merge_values( '', $encrypted_value );
		$this->assertEquals( $encrypted_value, $result );

		// Encrypted field with new value should use new value
		$result = $encrypted_field->merge_values( 'new_value', $encrypted_value );
		$this->assertEquals( 'new_value', $result );
	}
}
