<?php
/**
 * Unit tests for Form_Conditional_Manager
 *
 * @package CampaignBridge\Tests\Unit\Forms
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Forms;

use CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager;
use WP_UnitTestCase;

/**
 * Test Form_Conditional_Manager functionality
 */
class Form_Conditional_Manager_Test extends WP_UnitTestCase {

	/**
	 * Test field visibility with show_when conditions
	 */
	public function test_should_show_field_with_show_when(): void {
		$fields = array(
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
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Field should be hidden when enable_api is not checked
		$this->assertFalse( $manager->should_show_field( 'api_provider' ) );

		// Field should be visible when enable_api is checked
		$manager->with_form_data( array( 'enable_api' => '1' ) );
		$this->assertTrue( $manager->should_show_field( 'api_provider' ) );
	}

	/**
	 * Test field visibility with hide_when conditions
	 */
	public function test_should_show_field_with_hide_when(): void {
		$fields = array(
			'enable_advanced' => array(
				'type' => 'checkbox',
			),
			'simple_option'   => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'hide_when',
					'conditions' => array(
						array(
							'field'    => 'enable_advanced',
							'operator' => 'is_checked',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Field should be visible when enable_advanced is not checked
		$this->assertTrue( $manager->should_show_field( 'simple_option' ) );

		// Field should be hidden when enable_advanced is checked
		$manager->with_form_data( array( 'enable_advanced' => '1' ) );
		$this->assertFalse( $manager->should_show_field( 'simple_option' ) );
	}

	/**
	 * Test conditional requirements with required_when
	 */
	public function test_should_require_field_with_required_when(): void {
		$fields = array(
			'api_provider' => array(
				'type' => 'select',
			),
			'api_key'      => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'required_when',
					'conditions' => array(
						array(
							'field'    => 'api_provider',
							'operator' => 'equals',
							'value'    => 'rest',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Field should not be required when condition is not met
		$this->assertFalse( $manager->should_require_field( 'api_key' ) );

		// Field should be required when condition is met
		$manager->with_form_data( array( 'api_provider' => 'rest' ) );
		$this->assertTrue( $manager->should_require_field( 'api_key' ) );
	}

	/**
	 * Test condition evaluation with different operators
	 */
	public function test_condition_evaluation_operators(): void {
		$fields = array(
			'test_field' => array(
				'type' => 'text',
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Test equals operator
		$result = $manager->with_form_data( array( 'test_field' => 'yes' ) )
			->should_show_field( 'test_field' ); // No conditions, should be visible
		$this->assertTrue( $result );

		// Test with actual condition
		$fields_with_condition = array(
			'test_field'      => array(
				'type' => 'text',
			),
			'dependent_field' => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'test_field',
							'operator' => 'equals',
							'value'    => 'yes',
						),
					),
				),
			),
		);

		$manager_with_condition = new Form_Conditional_Manager( $fields_with_condition );

		// Should be hidden when value doesn't match
		$this->assertFalse( $manager_with_condition->should_show_field( 'dependent_field' ) );

		// Should be visible when value matches
		$manager_with_condition->with_form_data( array( 'test_field' => 'yes' ) );
		$this->assertTrue( $manager_with_condition->should_show_field( 'dependent_field' ) );
	}

	/**
	 * Test multiple conditions (AND logic)
	 */
	public function test_multiple_conditions_and_logic(): void {
		$fields = array(
			'field1'       => array( 'type' => 'checkbox' ),
			'field2'       => array( 'type' => 'text' ),
			'target_field' => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'field1',
							'operator' => 'is_checked',
						),
						array(
							'field'    => 'field2',
							'operator' => 'equals',
							'value'    => 'test',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Should be hidden when only one condition is met
		$manager->with_form_data( array( 'field1' => '1' ) );
		$this->assertFalse( $manager->should_show_field( 'target_field' ) );

		// Should be hidden when only the other condition is met
		$manager->with_form_data( array( 'field2' => 'test' ) );
		$this->assertFalse( $manager->should_show_field( 'target_field' ) );

		// Should be visible when both conditions are met
		$manager->with_form_data(
			array(
				'field1' => '1',
				'field2' => 'test',
			)
		);
		$this->assertTrue( $manager->should_show_field( 'target_field' ) );
	}

	/**
	 * Test conditional validation
	 */
	public function test_validate_conditional_requirements(): void {
		$fields = array(
			'trigger_field'  => array( 'type' => 'checkbox' ),
			'required_field' => array(
				'type'        => 'text',
				'label'       => 'Required Field',
				'conditional' => array(
					'type'       => 'required_when',
					'conditions' => array(
						array(
							'field'    => 'trigger_field',
							'operator' => 'is_checked',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Should not require validation when condition is not met
		$result = $manager->validate_conditional_requirements( 'required_field', $fields['required_field'], '' );
		$this->assertTrue( $result );

		// Should require validation when condition is met but field is empty
		$manager->with_form_data( array( 'trigger_field' => '1' ) );
		$result = $manager->validate_conditional_requirements( 'required_field', $fields['required_field'], '' );
		$this->assertWPError( $result );
		$this->assertEquals( 'field_required', $result->get_error_code() );
	}

	/**
	 * Test getting conditional fields
	 */
	public function test_get_conditional_fields(): void {
		$fields = array(
			'regular_field'      => array( 'type' => 'text' ),
			'conditional_field1' => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array( array( 'field' => 'other' ) ),
				),
			),
			'conditional_field2' => array(
				'type'        => 'checkbox',
				'conditional' => array(
					'type'       => 'required_when',
					'conditions' => array( array( 'field' => 'another' ) ),
				),
			),
		);

		$manager            = new Form_Conditional_Manager( $fields );
		$conditional_fields = $manager->get_conditional_fields();

		$this->assertCount( 2, $conditional_fields );
		$this->assertArrayHasKey( 'conditional_field1', $conditional_fields );
		$this->assertArrayHasKey( 'conditional_field2', $conditional_fields );
		$this->assertArrayNotHasKey( 'regular_field', $conditional_fields );
	}

	/**
	 * Test has_conditional_fields
	 */
	public function test_has_conditional_fields(): void {
		$fields_without_conditions = array(
			'field1' => array( 'type' => 'text' ),
			'field2' => array( 'type' => 'checkbox' ),
		);

		$fields_with_conditions = array(
			'field1' => array( 'type' => 'text' ),
			'field2' => array(
				'type'        => 'checkbox',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(),
				),
			),
		);

		$manager1 = new Form_Conditional_Manager( $fields_without_conditions );
		$manager2 = new Form_Conditional_Manager( $fields_with_conditions );

		$this->assertFalse( $manager1->has_conditional_fields() );
		$this->assertTrue( $manager2->has_conditional_fields() );
	}

	/**
	 * Test validate_conditional_fields filters hidden fields
	 */
	public function test_validate_conditional_fields_filters_hidden_fields(): void {
		$fields = array(
			'enable_feature' => array(
				'type' => 'checkbox',
			),
			'hidden_field'   => array(
				'type'        => 'text',
				'required'    => true,
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'enable_feature',
							'operator' => 'is_checked',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields, array() ); // Feature not enabled

		// Validate with feature disabled - hidden field should not be validated
		$result = $manager->validate_conditional_fields(
			array(
				'hidden_field' => '', // This should not cause validation error since field is hidden
			)
		);

		$this->assertTrue( $result['valid'] ); // Should be valid since hidden field is not validated
		$this->assertEmpty( $result['errors'] ); // No errors should be present
	}

	public function test_cascading_conditional_logic(): void {
		// Test the scenario described by the user:
		// enable_advanced -> auth_method -> username/password
		// When enable_advanced is unchecked, all child fields should be hidden

		$fields = array(
			'enable_advanced' => array(
				'type' => 'checkbox',
			),
			'auth_method'     => array(
				'type'        => 'select',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'enable_advanced',
							'operator' => 'is_checked',
						),
					),
				),
			),
			'username'        => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'auth_method',
							'operator' => 'equals',
							'value'    => 'basic',
						),
					),
				),
			),
			'password'        => array(
				'type'        => 'password',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'auth_method',
							'operator' => 'equals',
							'value'    => 'basic',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Test 1: When enable_advanced is NOT checked, all fields should be hidden
		$this->assertTrue( $manager->should_show_field( 'enable_advanced' ) ); // No conditions
		$this->assertFalse( $manager->should_show_field( 'auth_method' ) ); // Depends on enable_advanced
		$this->assertFalse( $manager->should_show_field( 'username' ) ); // Depends on auth_method
		$this->assertFalse( $manager->should_show_field( 'password' ) ); // Depends on auth_method

		// Test 2: When enable_advanced IS checked but auth_method is not 'basic', username/password should be hidden
		$manager->with_form_data(
			array(
				'enable_advanced' => '1',
				'auth_method'     => 'bearer',
			)
		);
		$this->assertTrue( $manager->should_show_field( 'enable_advanced' ) );
		$this->assertTrue( $manager->should_show_field( 'auth_method' ) ); // enable_advanced is checked
		$this->assertFalse( $manager->should_show_field( 'username' ) ); // auth_method != 'basic'
		$this->assertFalse( $manager->should_show_field( 'password' ) ); // auth_method != 'basic'

		// Test 3: When enable_advanced IS checked AND auth_method is 'basic', username/password should be shown
		$manager->with_form_data(
			array(
				'enable_advanced' => '1',
				'auth_method'     => 'basic',
			)
		);
		$this->assertTrue( $manager->should_show_field( 'enable_advanced' ) );
		$this->assertTrue( $manager->should_show_field( 'auth_method' ) );
		$this->assertTrue( $manager->should_show_field( 'username' ) ); // All conditions met
		$this->assertTrue( $manager->should_show_field( 'password' ) ); // All conditions met

		// Test 4: When enable_advanced is unchecked again, everything should be hidden
		$manager->with_form_data(
			array(
				'enable_advanced' => '0',
				'auth_method'     => 'basic',
			)
		);
		$this->assertTrue( $manager->should_show_field( 'enable_advanced' ) );
		$this->assertFalse( $manager->should_show_field( 'auth_method' ) ); // Parent condition not met
		$this->assertFalse( $manager->should_show_field( 'username' ) ); // Parent condition not met
		$this->assertFalse( $manager->should_show_field( 'password' ) ); // Parent condition not met
	}

	public function test_caching_functionality(): void {
		$fields = array(
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
		);

		$manager = new Form_Conditional_Manager( $fields, array(), true ); // Caching enabled

		// First call should calculate and cache
		$this->assertFalse( $manager->should_show_field( 'api_provider' ) );

		// Second call should use cache
		$this->assertFalse( $manager->should_show_field( 'api_provider' ) );

		// Change form data - should invalidate cache
		$manager->with_form_data( array( 'enable_api' => '1' ) );

		// Should recalculate and return true
		$this->assertTrue( $manager->should_show_field( 'api_provider' ) );

		// Disable caching
		$manager->set_caching_enabled( false );

		// Should still work but not use cache
		$this->assertTrue( $manager->should_show_field( 'api_provider' ) );

		// Verify caching state
		$this->assertFalse( $manager->is_caching_enabled() );
	}

	/**
	 * Test validate_conditional_fields validates visible required fields
	 */
	public function test_validate_conditional_fields_validates_visible_required_fields(): void {
		$fields = array(
			'enable_feature'        => array(
				'type' => 'checkbox',
			),
			'required_when_visible' => array(
				'type'        => 'text',
				'required'    => false,
				'conditional' => array(
					'type'       => 'required_when',
					'conditions' => array(
						array(
							'field'    => 'enable_feature',
							'operator' => 'is_checked',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields, array( 'enable_feature' => '1' ) );

		// Feature enabled - field should be required.
		$result = $manager->validate_conditional_fields(
			array(
				'required_when_visible' => '', // Empty when required.
			)
		);

		$this->assertFalse( $result['valid'] );
		$this->assertArrayHasKey( 'required_when_visible', $result['errors'] );
	}

	/**
	 * Test checkbox conditional logic with proper checked/unchecked values
	 */
	public function test_checkbox_conditional_logic(): void {
		$fields = array(
			'enable_feature'      => array(
				'type' => 'checkbox',
			),
			'feature_setting'     => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'enable_feature',
							'operator' => 'is_checked',
						),
					),
				),
			),
			'hidden_when_checked' => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'hide_when',
					'conditions' => array(
						array(
							'field'    => 'enable_feature',
							'operator' => 'is_checked',
						),
					),
				),
			),
		);

		$manager = new Form_Conditional_Manager( $fields );

		// Feature disabled ('0') - feature_setting should be hidden, hidden_when_checked should be visible
		$this->assertFalse( $manager->should_show_field( 'feature_setting' ) );
		$this->assertTrue( $manager->should_show_field( 'hidden_when_checked' ) );

		// Feature enabled ('1') - feature_setting should be visible, hidden_when_checked should be hidden
		$manager->with_form_data( array( 'enable_feature' => '1' ) );
		$this->assertTrue( $manager->should_show_field( 'feature_setting' ) );
		$this->assertFalse( $manager->should_show_field( 'hidden_when_checked' ) );

		// Test not_checked operator
		$fields_not_checked = array(
			'disable_feature'  => array(
				'type' => 'checkbox',
			),
			'when_not_checked' => array(
				'type'        => 'text',
				'conditional' => array(
					'type'       => 'show_when',
					'conditions' => array(
						array(
							'field'    => 'disable_feature',
							'operator' => 'not_checked',
						),
					),
				),
			),
		);

		$manager_not_checked = new Form_Conditional_Manager( $fields_not_checked );

		// Feature enabled (disable_feature checked = '1') - when_not_checked should be hidden.
		$manager_not_checked->with_form_data( array( 'disable_feature' => '1' ) );
		$this->assertFalse( $manager_not_checked->should_show_field( 'when_not_checked' ) );

		// Feature disabled (disable_feature unchecked = '0') - when_not_checked should be visible.
		$manager_not_checked->with_form_data( array( 'disable_feature' => '0' ) );
		$this->assertTrue( $manager_not_checked->should_show_field( 'when_not_checked' ) );
	}
}
