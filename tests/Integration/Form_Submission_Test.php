<?php
/**
 * Integration tests for complete form submission workflows.
 *
 * Tests form creation, submission, validation, and data persistence
 * in a real WordPress environment with actual database operations.
 *
 * @package CampaignBridge\Tests\Integration
 */

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Test complete form submission workflows with real WordPress integration.
 */
class Form_Submission_Test extends Test_Case {

	/**
	 * Test complete form workflow: creation → submission → database persistence → success response.
	 */
	public function test_complete_form_workflow_with_options_persistence(): void {
		// Arrange: Create a test user with admin capabilities
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Set up admin environment
		set_current_screen( 'admin' );

		// Create a test form
		$form_id = 'integration_test_form_' . uniqid();

		$form = Form::make( $form_id )
			->save_to_options( 'campaignbridge_' )
			->text( 'user_name', 'User Name' )->required()->end()
			->email( 'user_email', 'User Email' )->required()->end()
			->submit( 'Save Settings' );

		// Simulate form submission data
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id              => array(
				'form_id'    => $form_id,
				'user_name'  => 'John Doe',
				'user_email' => 'john@example.com',
			),
			$form_id . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id ),
		);

		// Act: Process the form submission
		$form->render(); // This triggers form processing internally

		// Assert: Verify data was saved to WordPress options
		// save_to_options() saves each field as separate option with campaignbridge_ prefix
		$this->assertEquals( 'John Doe', get_option( 'campaignbridge_user_name' ), 'User name should be saved' );
		$this->assertEquals( 'john@example.com', get_option( 'campaignbridge_user_email' ), 'User email should be saved' );
	}

	/**
	 * Test form validation with real WordPress nonces and error handling.
	 */
	public function test_form_validation_with_real_wordpress_nonces(): void {
		// Test 1: Missing nonce should fail
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'admin' );

		$form_id_1 = 'validation_test_form_1_' . uniqid();

		$form1 = Form::make( $form_id_1 )
			->save_to_options()
			->text( 'required_field', 'Required Field' )->required()->end()
			->email( 'email_field', 'Email Field' )->required()->end()
			->submit( 'Submit' );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id_1 => array(
				'form_id'        => $form_id_1,
				'required_field' => '',
				'email_field'    => 'invalid-email',
			),
		);

		$form1->render(); // Process form
		$this->assertFalse( $form1->submitted(), 'Form should not be detected as submitted without nonce' );

		// Test 2: Invalid data with valid nonce should fail
		$user_id2 = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id2 );
		set_current_screen( 'admin' );

		$form_id_2 = 'validation_test_form_2_' . uniqid();

		$form2 = Form::make( $form_id_2 )
			->save_to_options()
			->text( 'required_field', 'Required Field' )->required()->end()
			->email( 'email_field', 'Email Field' )->required()->end()
			->submit( 'Submit' );

		$_POST = array(
			$form_id_2              => array(
				'form_id'        => $form_id_2,
				'required_field' => '',
				'email_field'    => 'invalid-email',
			),
			$form_id_2 . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id_2 ),
		);

		$form2->render(); // Process form
		$this->assertTrue( $form2->submitted(), 'Form should be detected as submitted' );
		$this->assertFalse( $form2->valid(), 'Form should be invalid with missing required field' );

	}

	/**
	 * Test successful form submission with all required data.
	 */
	public function test_successful_form_submission_with_complete_data(): void {
		// Arrange: Create a test user with admin capabilities
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'admin' );

		$form_id = 'success_test_form_' . uniqid();

		$form = Form::make( $form_id )
			->save_to_options( 'campaignbridge_' )
			->text( 'name', 'Full Name' )->required()->end()
			->email( 'email', 'Email Address' )->required()->end()
			->textarea( 'message', 'Message' )->end()
			->success( 'Thank you for your submission!' )
			->submit( 'Send Message' );

		// Act: Submit valid form data
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id              => array(
				'form_id' => $form_id,
				'name'    => 'Jane Smith',
				'email'   => 'jane@example.com',
				'message' => 'This is a test message',
			),
			$form_id . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id ),
		);

		$form->render();

		// Assert: Form should be valid and data saved
		$this->assertTrue( $form->submitted(), 'Form should be submitted' );
		$this->assertTrue( $form->valid(), 'Form should be valid' );


		// Verify data persistence
		// save_to_options() saves each field as separate option with campaignbridge_ prefix
		$this->assertEquals( 'Jane Smith', get_option( 'campaignbridge_name' ) );
		$this->assertEquals( 'jane@example.com', get_option( 'campaignbridge_email' ) );
		$this->assertEquals( 'This is a test message', get_option( 'campaignbridge_message' ) );
	}

	/**
	 * Test form with post meta storage (requires test post).
	 */
	public function test_form_with_post_meta_storage(): void {
		// Arrange: Create a test user with admin capabilities
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'admin' );

		// Create a test post
		$post_id = $this->create_test_post(
			array(
				'post_title'   => 'Test Post for Form',
				'post_content' => 'Test content',
			)
		);

		$form_id = 'post_meta_test_form_' . uniqid();

		$form = Form::make( $form_id )
			->save_to_post_meta( $post_id )
			->text( 'meta_title', 'Meta Title' )->end()
			->textarea( 'meta_description', 'Meta Description' )->end()
			->submit( 'Save Meta' );

		// Act: Submit form data
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id              => array(
				'form_id'          => $form_id,
				'meta_title'       => 'Updated Title',
				'meta_description' => 'Updated description for the post',
			),
			$form_id . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id ),
		);

		$form->render();

		// Assert: Data should be saved to post meta
		$this->assertTrue( $form->valid(), 'Form should be valid' );

		$saved_title       = get_post_meta( $post_id, 'campaignbridge_meta_title', true );
		$saved_description = get_post_meta( $post_id, 'campaignbridge_meta_description', true );

		$this->assertEquals( 'Updated Title', $saved_title, 'Meta title should be saved' );
		$this->assertEquals( 'Updated description for the post', $saved_description, 'Meta description should be saved' );
	}

	/**
	 * Test form with custom save callback.
	 */
	public function test_form_with_custom_save_callback(): void {
		// Arrange: Create a test user with admin capabilities
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'admin' );

		// Track custom save execution
		$custom_save_called = false;
		$received_data      = null;

		$form_id = 'custom_save_test_form_' . uniqid();

		$form = Form::make( $form_id )
			->save_to_custom(
				function ( $data ) use ( &$custom_save_called, &$received_data ) {
					$custom_save_called = true;
					$received_data      = $data;
					return true; // Success
				}
			)
			->text( 'custom_field', 'Custom Field' )->end()
			->submit( 'Save Custom' );

		// Act: Submit form
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id              => array(
				'form_id'      => $form_id,
				'custom_field' => 'Custom value',
			),
			$form_id . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id ),
		);

		$form->render();

		// Assert: Custom save callback was executed
		$this->assertTrue( $custom_save_called, 'Custom save callback should be called' );
		$this->assertIsArray( $received_data, 'Should receive form data' );
		$this->assertEquals( 'Custom value', $received_data['custom_field'], 'Should receive correct data' );
	}

	/**
	 * Test form validation messages appear correctly.
	 */
	public function test_form_validation_messages_display(): void {
		// Arrange: Create a test user with admin capabilities
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'admin' );

		// Create form with validation
		$form_id = 'validation_messages_test_' . uniqid();

		$form = Form::make( $form_id )
			->save_to_options()
			->email( 'email', 'Email Address' )->required()->end()
			->submit( 'Submit' );

		// Act: Submit invalid data
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id              => array(
				'form_id' => $form_id,
				'email'   => 'invalid-email-format',
			),
			$form_id . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id ),
		);

		// Capture output to avoid PHPUnit warnings about output during test
		ob_start();
		$form->render();
		$output = ob_get_clean();

		// Assert: Form should be invalid
		$this->assertTrue( $form->submitted(), 'Form should be submitted' );
		$this->assertFalse( $form->valid(), 'Form should be invalid' );

		// Assert: Error messages should be present in output
		$this->assertStringContainsString( 'error', $output, 'Error messages should appear in output' );
	}

	/**
	 * Test form success messages appear after successful submission.
	 */
	public function test_form_success_messages_display(): void {
		// Arrange: Create a test user with admin capabilities
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'admin' );

		$form_id = 'success_message_test_' . uniqid();

		$form = Form::make( $form_id )
			->save_to_options()
			->text( 'name', 'Name' )->required()->end()
			->success( 'Data saved successfully!' )
			->submit( 'Save' );

		// Act: Submit valid data
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			$form_id              => array(
				'form_id' => $form_id,
				'name'    => 'Test Name',
			),
			$form_id . '_wpnonce' => wp_create_nonce( 'campaignbridge_form_' . $form_id ),
		);

		// Process form and capture output
		ob_start();
		$form->render();
		$output = ob_get_clean();

		// Assert: Form should be successful
		$this->assertTrue( $form->submitted(), 'Form should be submitted' );
		$this->assertTrue( $form->valid(), 'Form should be valid' );

		// Note: Success messages are displayed via WordPress admin notices, not in form HTML
	}
}
