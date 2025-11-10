<?php
/**
 * File Upload Integration Tests
 *
 * Tests complete file upload workflow from form submission to database storage.
 *
 * @package CampaignBridge\Tests\Integration
 */

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_File_Uploader;
use CampaignBridge\Admin\Core\Forms\Form_Validator;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * File Upload Integration Test Class
 */
class File_Upload_Integration_Test extends Test_Case {

	public function setUp(): void {
		parent::setUp();

		// Create test admin user
		$this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		parent::tearDown();

		// Clean up uploaded files
		$this->cleanup_test_uploads();

		// Clean up test options
		delete_option( 'test_upload_file' );
		delete_option( 'test_upload_attachment' );
		delete_option( 'test_upload_multiple' );
	}

	/**
	 * Test complete file upload workflow with options storage
	 */
	public function test_form_config_handles_file_fields(): void {
		$form = Form::make( 'config_test' )
			->file( 'upload_file', 'Upload File' )
			->text( 'other_field', 'Other Field' );

		$config = $form->get_config();
		$fields = $config->get( 'fields' );

		$this->assertArrayHasKey( 'upload_file', $fields );
		$this->assertArrayHasKey( 'other_field', $fields );
		$this->assertEquals( 'file', $fields['upload_file']['type'] );
		$this->assertEquals( 'text', $fields['other_field']['type'] );
	}

	public function test_form_builder_file_method_sets_multipart(): void {
		$form = Form::make( 'multipart_test' )
			->file( 'file_field', 'File Field' );

		$config = $form->get_config();
		$this->assertEquals( 'multipart/form-data', $config->get( 'enctype' ) );
	}

	public function test_form_validator_handles_file_validation(): void {
		$form = Form::make( 'validation_test' )
			->file( 'required_file', 'Required File' )->required()->end()
			->file( 'optional_file', 'Optional File' )->end();

		$validator = new Form_Validator();
		$fields    = $form->get_config()->get( 'fields' );

		// Debug: check field config
		$this->assertTrue( isset( $fields['required_file']['required'] ) );
		$this->assertTrue( $fields['required_file']['required'] );

		// Debug: check what the field instance looks like
		$validator      = new \CampaignBridge\Admin\Core\Forms\Form_Validator();
		$field_factory  = new \CampaignBridge\Admin\Core\Forms\Form_Field_Factory( $validator );
		$field_instance = $field_factory->create_field( 'required_file', $fields['required_file'], null );
		$this->assertTrue( $field_instance->is_required() );

		// Test missing required file - should fail validation
		$data = array(
			'required_file' => null,
			'optional_file' => null,
		);

		$result = $validator->validate_form( $data, $fields );
		$this->assertFalse( $result['valid'] ); // Should return false for validation failure
		$this->assertArrayHasKey( 'required_file', $result['errors'] );

		// Test valid file data - should pass validation
		// Create a temporary file for testing
		$temp_file = tempnam( sys_get_temp_dir(), 'test_file' );
		file_put_contents( $temp_file, 'test content' );

		$data = array(
			'required_file' => array(
				'file'     => $temp_file,
				'url'      => 'http://example.com/file.txt',
				'filename' => 'file.txt',
			),
			'optional_file' => null,
		);

		$result = $validator->validate_form( $data, $fields );
		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );

		// Clean up
		unlink( $temp_file );
	}

	/**
	 * Test file upload form automatically sets multipart encoding
	 */
	public function test_form_automatically_sets_multipart_encoding(): void {
		$form = Form::make( 'multipart_test' )
			->file( 'upload_file', 'Upload File' )
			->text( 'regular_field', 'Regular Field' );

		// Verify form config has multipart encoding
		$config = $form->get_config();
		$this->assertEquals( 'multipart/form-data', $config->get( 'enctype' ) );
	}

	/**
	 * Test form without file fields doesn't set multipart encoding
	 */
	public function test_form_without_files_no_multipart_encoding(): void {
		$form = Form::make( 'no_multipart_test' )
			->text( 'field1', 'Field 1' )
			->email( 'field2', 'Field 2' );

		// Verify form config doesn't have multipart encoding
		$config = $form->get_config();
		$this->assertEquals( 'application/x-www-form-urlencoded', $config->get( 'enctype' ) );
	}

	/**
	 * Helper method to create temporary file with content
	 */
	private function create_temp_file_with_content( string $content ): string {
		$temp_file = tempnam( sys_get_temp_dir(), 'test_upload_' );
		file_put_contents( $temp_file, $content );
		return $temp_file;
	}

	/**
	 * Clean up uploaded test files
	 */
	private function cleanup_test_uploads(): void {
		$upload_dir         = wp_upload_dir();
		$campaignbridge_dir = $upload_dir['basedir'] . '/campaignbridge-forms';

		if ( is_dir( $campaignbridge_dir ) ) {
			$this->remove_directory( $campaignbridge_dir );
		}
	}

	/**
	 * Recursively remove directory
	 */
	private function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$file_path = $dir . DIRECTORY_SEPARATOR . $file;
			is_dir( $file_path ) ? $this->remove_directory( $file_path ) : unlink( $file_path );
		}

		rmdir( $dir );
	}
}
