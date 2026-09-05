<?php
/**
 * Form Handler Test
 *
 * @package CampaignBridge\Tests\Unit\Forms
 */

namespace CampaignBridge\Tests\Unit\Forms;

use CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager;
use CampaignBridge\Admin\Core\Forms\Form_Conditional_Submission_Guard;

/**
 * Form Handler Test Class
 */
class Form_Handler_Test extends \WP_UnitTestCase {
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

		$fields = array(
			'visible_field' => array( 'type' => 'text' ),
			'hidden_field'  => array(
				'type'        => 'text',
				'conditional' => array( 'type' => 'show_when' ),
			),
		);

		$form_data = array(
			'visible_field' => 'visible value',
			'hidden_field'  => 'hidden value',
			'regular_field' => 'regular value',
		);

		$filtered_data = Form_Conditional_Submission_Guard::filter( $conditional_manager, $fields, $form_data );

		// Hidden field data should be filtered out
		$this->assertArrayHasKey( 'visible_field', $filtered_data );
		$this->assertArrayHasKey( 'regular_field', $filtered_data );
		$this->assertArrayNotHasKey( 'hidden_field', $filtered_data );
		$this->assertEquals( 'visible value', $filtered_data['visible_field'] );
		$this->assertEquals( 'regular value', $filtered_data['regular_field'] );
	}
}
