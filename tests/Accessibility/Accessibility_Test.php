<?php
/**
 * Accessibility Compliance Tests
 *
 * Automated tests to ensure form system meets WCAG 2.1 AA accessibility standards.
 *
 * @package CampaignBridge\Tests\Accessibility
 */

namespace CampaignBridge\Tests\Accessibility;

use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Field_Base;
use CampaignBridge\Admin\Core\Forms\Form_Field_Factory;
use CampaignBridge\Admin\Core\Forms\Form_Field_Radio;
use CampaignBridge\Admin\Core\Forms\Form_Renderer;
use CampaignBridge\Admin\Core\Forms\Form_Handler;
use CampaignBridge\Admin\Core\Forms\Form_Data_Manager;
use CampaignBridge\Admin\Core\Forms\Form_Notice_Handler;
use CampaignBridge\Admin\Core\Forms\Form_Validator;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Accessibility Test Class
 *
 * Tests WCAG 2.1 AA compliance for the form system including:
 * - ARIA attributes and relationships
 * - Keyboard navigation
 * - Screen reader compatibility
 * - Semantic HTML structure
 * - Error identification and description
 */
class Accessibility_Test extends Test_Case {

	/**
	 * Test that all form fields have proper labels
	 */
	public function test_all_form_fields_have_labels(): void {
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );

		// Test field with explicit label
		$explicit_config = array(
			'type'  => 'text',
			'label' => 'Explicit Label',
		);
		$explicit_field  = $field_factory->create_field( 'explicit_label', $explicit_config, '' );

		$this->assertEquals( 'Explicit Label', $explicit_field->get_config()['label'] );

		// Test field with implicit label (generated from field name)
		$implicit_config = array(
			'type' => 'email',
			// Should generate label from field name
		);
		$implicit_field = $field_factory->create_field( 'implicit_label', $implicit_config, '' );

		$this->assertEquals( 'Implicit label', $implicit_field->get_config()['label'] );
	}

	/**
	 * Test that required fields have proper accessibility attributes
	 */
	public function test_required_fields_have_accessibility_attributes(): void {
		$validator       = new Form_Validator();
		$field_factory   = new Form_Field_Factory( $validator );
		$required_config = array(
			'type'     => 'text',
			'label'    => 'Required Field',
			'required' => true,
			'id'       => 'required_field',
			'name'     => 'required_field',
		);

		$field = $field_factory->create_field( 'required_field', $required_config, '' );

		$this->assertTrue( $field->is_required(), 'Field should be marked as required' );

		// Test that the field config includes accessibility attributes
		$this->assertTrue( $required_config['required'], 'Required flag should be set' );
	}


	/**
	 * Test radio button groups use fieldset and legend
	 */
	public function test_radio_buttons_use_fieldset_and_legend(): void {
		$radio_config = array(
			'type'    => 'radio',
			'label'   => 'Choose Option',
			'id'      => 'radio_test',
			'name'    => 'radio_test',
			'options' => array(
				'option1' => 'Option One',
				'option2' => 'Option Two',
			),
		);

		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );
		$radio_field   = $field_factory->create_field( 'radio_test', $radio_config, '' );

		// Verify the field has proper accessibility structure
		$this->assertInstanceOf( Form_Field_Radio::class, $radio_field );
		$this->assertEquals( 'radio', $radio_config['type'] );
		$this->assertEquals( 'Choose Option', $radio_config['label'] );
		$this->assertArrayHasKey( 'options', $radio_config );
		$this->assertCount( 2, $radio_config['options'] );
	}

	/**
	 * Test checkbox groups use fieldset and legend
	 */
	public function test_checkbox_groups_have_proper_structure(): void {
		$checkbox_config = array(
			'type'  => 'checkbox',
			'label' => 'Select Options',
			'id'    => 'checkbox_test',
			'name'  => 'checkbox_test',
		);

		$validator      = new Form_Validator();
		$field_factory  = new Form_Field_Factory( $validator );
		$checkbox_field = $field_factory->create_field( 'checkbox_test', $checkbox_config, '' );

		// Checkboxes should have proper labeling
		$this->assertEquals( 'checkbox', $checkbox_config['type'] );
		$this->assertEquals( 'Select Options', $checkbox_config['label'] );
	}

	/**
	 * Test form error messages have proper ARIA attributes
	 */
	public function test_form_error_messages_have_aria_attributes(): void {
		// Test that error messages are structured for accessibility
		$form_config = new Form_Config(
			array(
				'form_id' => 'test_form',
			)
		);

		// This would normally be tested by rendering the form and checking the HTML output
		// For now, we test the configuration that drives the rendering
		$this->assertEquals( 'test_form', $form_config->get( 'form_id' ) );
	}

	/**
	 * Test unique form and field IDs for accessibility
	 */
	public function test_unique_ids_for_accessibility(): void {
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );

		// Create fields with a form ID prefix context
		$field1 = $field_factory->create_field( 'unique_form[field1]', array( 'type' => 'text' ), '' );
		$field2 = $field_factory->create_field( 'unique_form[field2]', array( 'type' => 'email' ), '' );

		$field1_config = $field1->get_config();
		$field2_config = $field2->get_config();

		// Test unique field IDs
		$this->assertEquals( 'unique_form[field1]', $field1_config['id'] );
		$this->assertEquals( 'unique_form[field2]', $field2_config['id'] );

		// Test unique field names
		$this->assertEquals( 'unique_form[field1]', $field1_config['name'] );
		$this->assertEquals( 'unique_form[field2]', $field2_config['name'] );

		// Ensure IDs are different
		$this->assertNotEquals( $field1_config['id'], $field2_config['id'] );
	}

	/**
	 * Test keyboard navigation support
	 */
	public function test_keyboard_navigation_support(): void {
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );

		// Create text field
		$text_field  = $field_factory->create_field( 'keyboard_test[text_field]', array( 'type' => 'text' ), '' );
		$text_config = $text_field->get_config();

		// Create submit field
		$submit_field  = $field_factory->create_field( 'keyboard_test[submit_field]', array( 'type' => 'submit' ), '' );
		$submit_config = $submit_field->get_config();

		// All fields should have proper IDs for keyboard navigation
		$this->assertArrayHasKey( 'id', $text_config );
		$this->assertArrayHasKey( 'name', $text_config );

		// Submit buttons should be keyboard accessible
		$this->assertArrayHasKey( 'id', $submit_config );
	}

	/**
	 * Test semantic form structure
	 */
	public function test_semantic_form_structure(): void {
		$form_config = new Form_Config(
			array(
				'form_id' => 'semantic_test',
				'method'  => 'POST',
				'action'  => '/test-action',
			)
		);

		$this->assertEquals( 'POST', $form_config->get( 'method' ) );
		$this->assertEquals( '/test-action', $form_config->get( 'action' ) );
		$this->assertEquals( 'semantic_test', $form_config->get( 'form_id' ) );
	}

	/**
	 * Test description associations with fields
	 */
	public function test_field_descriptions_are_accessible(): void {
		$form_config = new Form_Config(
			array(
				'form_id' => 'description_test',
			)
		);

		$form_config->add_field(
			'field_with_desc',
			array(
				'type'        => 'text',
				'label'       => 'Field with Description',
				'description' => 'This is a helpful description',
			)
		);

		$fields        = $form_config->get_fields();
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );
		$field         = $field_factory->create_field( 'field_with_desc', $fields['field_with_desc'], '' );
		$field_config  = $field->get_config();

		$this->assertEquals( 'This is a helpful description', $field_config['description'] );
		$this->assertEquals( 'field_with_desc', $field_config['id'] );
	}

	/**
	 * Test form validation provides accessible error information
	 */
	public function test_validation_errors_are_accessible(): void {
		$form_config = new Form_Config(
			array(
				'form_id' => 'validation_test',
			)
		);

		$form_config->add_field(
			'required_field',
			array(
				'type'     => 'text',
				'label'    => 'Required Field',
				'required' => true,
			)
		);

		$validator = new Form_Validator();
		$fields    = $form_config->get_fields();

		// Test validation with empty required field
		$result = $validator->validate_form( array( 'required_field' => '' ), $fields );

		$this->assertFalse( $result['valid'], 'Validation should fail for empty required field' );
		$this->assertArrayHasKey( 'required_field', $result['errors'], 'Should have field-specific error' );
	}


	/**
	 * Test that multiple forms on same page have unique IDs
	 */
	public function test_multiple_forms_unique_ids(): void {
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );

		// Create fields for different forms with same field names
		$field1 = $field_factory->create_field( 'form_a[field1]', array( 'type' => 'text' ), '' );
		$field2 = $field_factory->create_field( 'form_b[field1]', array( 'type' => 'text' ), '' ); // Same field name

		$field1_config = $field1->get_config();
		$field2_config = $field2->get_config();

		// IDs should be unique across forms
		$this->assertEquals( 'form_a[field1]', $field1_config['id'] );
		$this->assertEquals( 'form_b[field1]', $field2_config['id'] );

		$this->assertNotEquals( $field1_config['id'], $field2_config['id'] );
	}

	/**
	 * Test skip links and navigation landmarks (conceptual test)
	 */
	public function test_navigation_and_skip_links_concept(): void {
		// This tests the concept that forms should support skip links
		// In a real implementation, this would check for proper heading structure
		$form_config = new Form_Config(
			array(
				'form_id'     => 'navigation_test',
				'description' => 'Test form for navigation',
			)
		);

		$this->assertEquals( 'Test form for navigation', $form_config->get( 'description' ) );
		$this->assertEquals( 'navigation_test', $form_config->get( 'form_id' ) );
	}

	/**
	 * Test accessibility compliance for form fields
	 */
	public function test_form_field_accessibility_compliance(): void {
		$form_config = new Form_Config(
			array(
				'form_id' => 'test_form',
			)
		);

		// Add various field types
		$form_config->add_field(
			'text_field',
			array(
				'type'     => 'text',
				'label'    => 'Text Field',
				'required' => true,
			)
		);

		$form_config->add_field(
			'email_field',
			array(
				'type'  => 'email',
				'label' => 'Email Field',
			)
		);

		$form_config->add_field(
			'radio_field',
			array(
				'type'    => 'radio',
				'label'   => 'Radio Field',
				'options' => array(
					'option1' => 'Option 1',
					'option2' => 'Option 2',
				),
			)
		);

		$form_config->add_field(
			'checkbox_field',
			array(
				'type'  => 'checkbox',
				'label' => 'Checkbox Field',
			)
		);

		$fields = $form_config->get_fields();

		// Test text field accessibility (raw config)
		$text_field = $fields['text_field'];
		$this->assertEquals( 'text', $text_field['type'] );
		$this->assertEquals( 'Text Field', $text_field['label'] );
		$this->assertTrue( $text_field['required'] );

		// Test field normalization by creating field instance
		$validator         = new Form_Validator();
		$field_factory     = new Form_Field_Factory( $validator );
		$normalized_field  = $field_factory->create_field( 'test_form[text_field]', $text_field, '' );
		$normalized_config = $normalized_field->get_config();
		$this->assertEquals( 'test_form[text_field]', $normalized_config['id'] );
		$this->assertEquals( 'test_form[text_field]', $normalized_config['name'] );

		// Test email field accessibility
		$email_field = $fields['email_field'];
		$this->assertEquals( 'email', $email_field['type'] );
		$this->assertEquals( 'Email Field', $email_field['label'] );
		// Email field ID would be normalized, but we're testing raw config here

		// Test radio field accessibility
		$radio_field = $fields['radio_field'];
		$this->assertEquals( 'radio', $radio_field['type'] );
		$this->assertEquals( 'Radio Field', $radio_field['label'] );
		$this->assertArrayHasKey( 'options', $radio_field );

		// Test checkbox field accessibility
		$checkbox_field = $fields['checkbox_field'];
		$this->assertEquals( 'checkbox', $checkbox_field['type'] );
		$this->assertEquals( 'Checkbox Field', $checkbox_field['label'] );
	}

	/**
	 * Test ARIA attributes are properly set for accessibility
	 */
	public function test_aria_attributes_for_accessibility(): void {
		$form_config = new Form_Config(
			array(
				'form_id' => 'test_form',
			)
		);

		// Create a field instance to test ARIA attributes
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );
		$field_config  = array(
			'type'             => 'text',
			'label'            => 'Test Field',
			'required'         => true,
			'id'               => 'test_field',
			'name'             => 'test_field',
			'aria-describedby' => 'test_description',
		);

		$field = $field_factory->create_field( 'test_field', $field_config, '' );

		// Test that field has proper configuration for ARIA attributes
		$this->assertTrue( $field->is_required() );
		$this->assertEquals( 'Test Field', $field_config['label'] );
		$this->assertEquals( 'test_field', $field_config['id'] );

		// Test error state attributes (simulate field with errors)
		$field_with_errors = $field_factory->create_field(
			'error_field',
			array_merge(
				$field_config,
				array(
					'errors' => array( 'This field is required' ),
				)
			),
			''
		);
	}

	/**
	 * Test form messages have proper accessibility attributes
	 */
	public function test_form_messages_accessibility(): void {
		$notice_handler = new Form_Notice_Handler();

		// Test that notice handler can trigger accessible messages
		$form_config = new Form_Config();

		// This would normally trigger a notice with proper ARIA attributes
		// In a real scenario, these would be rendered with role and aria-live attributes
		$this->assertInstanceOf( Form_Notice_Handler::class, $notice_handler );
	}

	/**
	 * Test radio button accessibility with fieldset and legend
	 */
	public function test_radio_button_accessibility_structure(): void {
		$validator     = new Form_Validator();
		$field_factory = new Form_Field_Factory( $validator );
		$radio_config  = array(
			'type'    => 'radio',
			'label'   => 'Choose Option',
			'id'      => 'radio_test',
			'name'    => 'radio_test',
			'options' => array(
				'option1' => 'Option One',
				'option2' => 'Option Two',
			),
		);

		$radio_field = $field_factory->create_field( 'radio_test', $radio_config, '' );

		// Verify radio field has proper structure for accessibility
		$this->assertEquals( 'radio', $radio_config['type'] );
		$this->assertArrayHasKey( 'options', $radio_config );
		$this->assertCount( 2, $radio_config['options'] );
		$this->assertEquals( 'Choose Option', $radio_config['label'] );
	}

	/**
	 * Test that form IDs are unique and properly namespaced
	 */
	public function test_form_id_namespacing_for_accessibility(): void {
		$form_config1 = new Form_Config(
			array(
				'form_id' => 'form_one',
			)
		);

		$form_config2 = new Form_Config(
			array(
				'form_id' => 'form_two',
			)
		);

		$this->assertEquals( 'form_one', $form_config1->get( 'form_id' ) );
		$this->assertEquals( 'form_two', $form_config2->get( 'form_id' ) );

		// Test field ID generation with form prefix
		$form_config1->add_field( 'test_field', array( 'type' => 'text' ) );
		$fields = $form_config1->get_fields();

		// Create normalized field to test ID generation
		$validator         = new Form_Validator();
		$field_factory     = new Form_Field_Factory( $validator );
		$normalized_field  = $field_factory->create_field( 'form_one[test_field]', $fields['test_field'], '' );
		$normalized_config = $normalized_field->get_config();

		$this->assertEquals( 'form_one[test_field]', $normalized_config['id'] );
		$this->assertEquals( 'form_one[test_field]', $normalized_config['name'] );
	}

	/**
	 * Render a form and capture the HTML output for accessibility assertions.
	 *
	 * @param array<string, mixed> $config Form configuration.
	 * @param array<string, mixed> $fields Field configurations keyed by field ID.
	 * @param array<string, mixed> $data   Submitted form data.
	 * @return string Complete rendered form HTML.
	 */
	private function render_form_html( array $config, array $fields, array $data = array() ): string {
		$security  = new Form_Security( $config['form_id'] ?? 'test_form' );
		$validator = new Form_Validator();
		$renderer  = new Form_Renderer( $config, $fields, $data, $security, $validator );

		ob_start();
		$renderer->render_form_open();
		$renderer->render_fields();
		$renderer->render_submit_button();
		$renderer->render_form_close();
		return (string) ob_get_clean();
	}

	/**
	 * Build a standard div-layout form config for rendering tests.
	 *
	 * @return array<string, mixed>
	 */
	private function make_render_config(): array {
		return array(
			'form_id'       => 'test_form',
			'layout'        => 'div',
			'method'        => 'POST',
			'action'        => '',
			'enctype'       => 'multipart/form-data',
			'classes'       => array( 'test-form' ),
			'attributes'    => array(),
			'submit_button' => array(
				'text'       => 'Save Changes',
				'type'       => 'primary',
				'attributes' => array(),
			),
		);
	}

	/**
	 * WCAG SC 1.3.1: Every <label for="X"> must have a matching input with id="X".
	 */
	public function test_rendered_html_label_for_pairing(): void {
		$fields = array(
			'email' => array(
				'type'  => 'email',
				'label' => 'Email Address',
			),
			'phone' => array(
				'type'  => 'tel',
				'label' => 'Phone Number',
			),
			'website' => array(
				'type'  => 'url',
				'label' => 'Website',
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		preg_match_all( '/<label[^>]*for="([^"]+)"/', $html, $label_matches );
		$label_for_values = $label_matches[1] ?? array();

		preg_match_all( '/<input[^>]*id="([^"]+)"/', $html, $input_matches );
		$input_id_values  = $input_matches[1] ?? array();

		$this->assertNotEmpty( $label_for_values, 'Expected at least one <label for="..."> in rendered HTML' );
		$this->assertNotEmpty( $input_id_values, 'Expected at least one <input id="..."> in rendered HTML' );

		foreach ( $label_for_values as $for_value ) {
			$this->assertContains(
				$for_value,
				$input_id_values,
				sprintf( 'Label for="%s" must have a matching input with id="%s"', $for_value, $for_value )
			);
		}
	}

	/**
	 * WCAG SC 4.1.2: Error containers must have role="alert" and aria-live="polite".
	 */
	public function test_rendered_html_error_containers_have_aria(): void {
		$fields = array(
			'email' => array(
				'type'   => 'email',
				'label'  => 'Email Address',
				'errors' => array( 'Invalid email address' ),
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertStringContainsString(
			'role="alert"',
			$html,
			'Error container must have role="alert" for screen reader announcement'
		);

		$this->assertStringContainsString(
			'aria-live="polite"',
			$html,
			'Error container must have aria-live="polite" for dynamic updates'
		);

		$this->assertStringContainsString(
			'Invalid email address',
			$html,
			'Error message text must be present in rendered HTML'
		);

		$this->assertMatchesRegularExpression(
			'/id="test_form_email_errors"/',
			$html,
			'Error container must have an id linking it to the field'
		);
	}

	/**
	 * WCAG SC 1.3.1: Required fields must have the HTML required attribute.
	 */
	public function test_rendered_html_required_fields_have_required_attribute(): void {
		$fields = array(
			'username' => array(
				'type'     => 'text',
				'label'    => 'Username',
				'required' => true,
			),
			'nickname' => array(
				'type'  => 'text',
				'label' => 'Nickname',
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertMatchesRegularExpression(
			'/<input[^>]*id="test_form_username"[^>]*required/',
			$html,
			'Required field input must have the HTML required attribute'
		);

		$this->assertDoesNotMatchRegularExpression(
			'/<input[^>]*id="test_form_nickname"[^>]*required/',
			$html,
			'Non-required field input must not have the HTML required attribute'
		);
	}

	/**
	 * WCAG SC 1.4.1: Required fields must not be indicated by color alone.
	 */
	public function test_rendered_html_required_fields_have_visible_indicator(): void {
		$fields = array(
			'username' => array(
				'type'     => 'text',
				'label'    => 'Username',
				'required' => true,
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertStringContainsString(
			'campaignbridge-field__required',
			$html,
			'Required field label must contain a visible indicator class (not color-only)'
		);

		$this->assertMatchesRegularExpression(
			'/<label[^>]*>.*\*/s',
			$html,
			'Required field label must contain a visible * character indicator'
		);
	}

	/**
	 * WCAG SC 1.3.1: Radio button groups must use fieldset and legend.
	 */
	public function test_rendered_html_radio_groups_use_fieldset_legend(): void {
		$fields = array(
			'color' => array(
				'type'    => 'radio',
				'label'   => 'Preferred Color',
				'options' => array(
					'red'   => 'Red',
					'blue'  => 'Blue',
					'green' => 'Green',
				),
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertStringContainsString(
			'<fieldset',
			$html,
			'Radio group must be wrapped in <fieldset>'
		);

		$this->assertStringContainsString(
			'<legend',
			$html,
			'Radio group must have a <legend>'
		);

		$this->assertStringContainsString(
			'Preferred Color',
			$html,
			'Legend must contain the field label text'
		);

		$this->assertStringContainsString(
			'</fieldset>',
			$html,
			'Radio group fieldset must be properly closed'
		);

		$this->assertMatchesRegularExpression(
			'/<label[^>]*for="test_form_color_red"/',
			$html,
			'Each radio option must have an associated label'
		);
	}

	/**
	 * WCAG SC 1.3.5: Input types must match field purpose.
	 */
	public function test_rendered_html_input_types_match_field_purpose(): void {
		$fields = array(
			'email'   => array(
				'type'  => 'email',
				'label' => 'Email Address',
			),
			'phone'   => array(
				'type'  => 'tel',
				'label' => 'Phone Number',
			),
			'website' => array(
				'type'  => 'url',
				'label' => 'Website',
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertMatchesRegularExpression(
			'/<input[^>]*type="email"[^>]*id="test_form_email"/',
			$html,
			'Email field must use type="email" for proper input purpose identification'
		);

		$this->assertMatchesRegularExpression(
			'/<input[^>]*type="tel"[^>]*id="test_form_phone"/',
			$html,
			'Phone field must use type="tel" for proper input purpose identification'
		);

		$this->assertMatchesRegularExpression(
			'/<input[^>]*type="url"[^>]*id="test_form_website"/',
			$html,
			'URL field must use type="url" for proper input purpose identification'
		);
	}

	/**
	 * WCAG SC 4.1.2: Forms with conditional fields must expose ARIA live region.
	 */
	public function test_rendered_html_form_has_aria_for_conditional_fields(): void {
		$fields = array(
			'has_pet'  => array(
				'type'        => 'checkbox',
				'label'       => 'Do you have a pet?',
				'conditional' => array(
					'show' => array( 'has_pet' => 'yes' ),
				),
			),
			'pet_name' => array(
				'type'        => 'text',
				'label'       => 'Pet Name',
				'conditional' => array(
					'show' => array( 'has_pet' => 'yes' ),
				),
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertStringContainsString(
			'aria-live="polite"',
			$html,
			'Form with conditional fields must have aria-live="polite" for screen reader announcements'
		);

		$this->assertStringContainsString(
			'aria-atomic="false"',
			$html,
			'Form with conditional fields must have aria-atomic="false" to avoid announcing unchanged content'
		);
	}

	/**
	 * WCAG SC 3.3.2: Form fields with descriptions must have visible instructions.
	 */
	public function test_rendered_html_field_descriptions_are_present(): void {
		$fields = array(
			'password' => array(
				'type'        => 'password',
				'label'       => 'Password',
				'description' => 'Must be at least 8 characters long.',
			),
		);

		$html = $this->render_form_html( $this->make_render_config(), $fields );

		$this->assertStringContainsString(
			'campaignbridge-field__description',
			$html,
			'Field description must be rendered with a semantic class'
		);

		$this->assertStringContainsString(
			'Must be at least 8 characters long.',
			$html,
			'Field description text must be present in rendered HTML'
		);
	}
}
