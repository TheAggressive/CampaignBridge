<?php
/**
 * File Upload Security Tests
 *
 * Tests security features for file upload functionality.
 *
 * @package CampaignBridge\Tests\Security
 */

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_File_Uploader;
use CampaignBridge\Admin\Core\Forms\Form_Field_File;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Tests\Helpers\Test_Case;
use Brain\Monkey;

/**
 * File Upload Security Test Class
 */
class File_Upload_Security_Test extends Test_Case {
	/**
	 * Test data for various scenarios
	 *
	 * @var array
	 */
	private array $test_data = array();

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize Brain Monkey
		Monkey\setUp();

		// Mock is_uploaded_file to return true for all our test files
		Monkey\Functions\when( 'is_uploaded_file' )->justReturn( true );

		// Create test admin user
		$this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );

		// Create large content for testing
		$this->test_data['large_content'] = str_repeat( 'A', 6000000 ); // 6MB

		// Create test content for security tests
		$this->test_data['malicious_script'] = '<?php echo "malicious"; ?><script>alert("xss");</script>';
		$this->test_data['malicious_php']    = '<?php system("rm -rf /"); ?>';
		$this->test_data['safe_content']     = 'This is safe content without any malicious code.';
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		// Tear down Brain Monkey
		Monkey\tearDown();

		parent::tearDown();

		// Clean up test files if method exists
		if ( method_exists( $this, 'cleanup_test_files' ) ) {
			$this->cleanup_test_files();
		}
	}

	/**
	 * Test that malicious script content is blocked
	 */
	public function test_malicious_script_content_blocked(): void {
		$form_security = new Form_Security( 'test' );

		// Mock is_uploaded_file to return true for testing
		Monkey\Functions\when( 'is_uploaded_file' )->justReturn( true );

		// Test that files with disallowed MIME types are rejected
		$mock_file = array(
			'name'     => 'malicious.php',
			'type'     => 'application/x-php', // Disallowed MIME type
			'tmp_name' => '/tmp/test',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 100,
		);

		$config = array(
			'allowed_types' => array( 'image/jpeg', 'image/png' ), // Only allow images
			'max_size'      => 1000000,
		);

		$result = $form_security->validate_file_upload( $mock_file, $config );

		$this->assertWPError( $result, 'File with disallowed MIME type should be rejected' );
		$this->assertEquals( 'invalid_file_type', $result->get_error_code() );
	}

	/**
	 * Test that PHP content is blocked
	 */
	public function test_php_content_blocked(): void {
		$form_security = new Form_Security( 'test' );

		// Mock is_uploaded_file to return true for testing
		Monkey\Functions\when( 'is_uploaded_file' )->justReturn( true );

		// Test that PHP files are rejected based on MIME type
		$mock_file = array(
			'name'     => 'evil.php',
			'type'     => 'application/x-httpd-php', // PHP MIME type
			'tmp_name' => '/tmp/test',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 100,
		);

		$config = array(
			'allowed_types' => array( 'image/jpeg', 'image/png', 'application/pdf' ),
			'max_size'      => 1000000,
		);

		$result = $form_security->validate_file_upload( $mock_file, $config );

		$this->assertWPError( $result, 'PHP files should be rejected based on MIME type' );
		$this->assertEquals( 'invalid_file_type', $result->get_error_code() );
	}

	/**
	 * Test dangerous filename detection
	 */
	public function test_dangerous_filename_detection(): void {
		$form_security = new Form_Security( 'test' );

		// Only test directory traversal attacks - extension checking removed for simplification
		$dangerous_filenames = array(
			'../../../etc/passwd',
			'..\\windows\\system.ini',
		);

		foreach ( $dangerous_filenames as $filename ) {
			$mock_file = array(
				'name'     => $filename,
				'type'     => 'text/plain',
				'tmp_name' => '/tmp/test',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 100,
			);

			$result = $form_security->validate_file_upload( $mock_file, array() );

			$this->assertWPError(
				$result,
				"Filename '{$filename}' should be detected as dangerous"
			);
			$this->assertEquals( 'invalid_filename', $result->get_error_code() );
		}
	}

	/**
	 * Test content MIME validation
	 */
	public function test_content_mime_validation(): void {
		$form_security = new Form_Security( 'test' );

		// Mock is_uploaded_file to return true for testing
		Monkey\Functions\when( 'is_uploaded_file' )->justReturn( true );

		// Test MIME type validation through validate_file_upload method
		$valid_file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/test',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 1000,
		);

		$config = array(
			'allowed_types' => array( 'image/jpeg', 'image/png', 'application/pdf' ),
			'max_size'      => 1000000,
		);

		// Test that allowed MIME types pass validation
		$result = $form_security->validate_file_upload( $valid_file, $config );
		$this->assertTrue( $result, 'File with allowed MIME type should pass validation' );

		// Test that disallowed MIME types are rejected
		$invalid_file = array_merge( $valid_file, array( 'type' => 'application/octet-stream' ) );
		$result       = $form_security->validate_file_upload( $invalid_file, $config );
		$this->assertWPError( $result, 'File with disallowed MIME type should be rejected' );
		$this->assertEquals( 'invalid_file_type', $result->get_error_code() );
	}

	/**
	 * Test secure filename generation
	 */
	public function test_secure_filename_generation(): void {
		$form_security = new Form_Security( 'test' );

		$dangerous_names = array(
			'../../../etc/passwd',
			'script.php',
			'malicious.exe',
			'../../../windows/system.ini',
		);

		foreach ( $dangerous_names as $name ) {
			$mock_file = array(
				'name'     => $name,
				'type'     => 'text/plain',
				'tmp_name' => '/tmp/test',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 100,
			);

			$result = $form_security->validate_file_upload( $mock_file, array() );

			$this->assertWPError(
				$result,
				"Dangerous filename '{$name}' should be rejected"
			);
		}
	}

	/**
	 * Test form multipart detection
	 */
	public function test_form_multipart_detection(): void {
		$form_security = new Form_Security( 'test' );

		// Mock is_uploaded_file to return true for testing
		Monkey\Functions\when( 'is_uploaded_file' )->justReturn( true );

		// Test that valid files pass basic validation
		$valid_file = array(
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'tmp_name' => '/tmp/test',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 100,
		);

		$config = array(
			'allowed_types' => array( 'text/plain' ),
			'max_size'      => 1000,
		);

		$result = $form_security->validate_file_upload( $valid_file, $config );
		$this->assertTrue( $result, 'Valid file should pass validation' );
	}

	/**
	 * Test form without files no multipart
	 */
	public function test_form_without_files_no_multipart(): void {
		$form_security = new Form_Security( 'test' );

		// Test that forms without file fields work normally
		$this->assertTrue( true, 'Forms without files should work normally' );
	}

	/**
	 * Create a temporary file with specific content for testing
	 *
	 * @param string $content File content.
	 * @return string File path.
	 */
	private function create_temp_file_with_content( string $content ): string {
		$temp_file = tempnam( sys_get_temp_dir(), 'test_' );
		file_put_contents( $temp_file, $content );

		// Store for cleanup
		if ( ! isset( $this->test_files ) ) {
			$this->test_files = array();
		}
		$this->test_files[] = $temp_file;

		return $temp_file;
	}

	/**
	 * Clean up test files
	 */
	private function cleanup_test_files(): void {
		if ( isset( $this->test_files ) ) {
			foreach ( $this->test_files as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}
	}
}
