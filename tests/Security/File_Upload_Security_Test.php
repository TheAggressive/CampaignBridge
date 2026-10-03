<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,WordPress.WP.AlternativeFunctions
/**
 * File upload security tests.
 *
 * @package CampaignBridge\Tests\Security
 */

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Admin\Core\Forms\Form_File_Uploader;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Proves the field policy and wp_handle_upload() together refuse unsafe
 * uploads, and that a failed upload leaves no file or attachment behind.
 */
class File_Upload_Security_Test extends Test_Case {
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

	/** @var array<int, string> */
	private array $temp_files = array();

	/** @var array<int, string> */
	private array $stored_files = array();

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		add_filter( 'wp_handle_upload', array( $this, 'record_stored_file' ) );
	}

	public function tearDown(): void {
		remove_filter( 'wp_handle_upload', array( $this, 'record_stored_file' ) );
		remove_all_filters( 'wp_insert_post_empty_content' );
		foreach ( array_merge( $this->temp_files, $this->stored_files ) as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $upload Stored upload.
	 * @return array<string, mixed>
	 */
	public function record_stored_file( array $upload ): array {
		$this->stored_files[] = $upload['file'];

		return $upload;
	}

	/**
	 * Fixtures did not arrive over HTTP, so they are stored with
	 * wp_handle_sideload(): the same sanitization and checks as
	 * wp_handle_upload() without its is_uploaded_file() test.
	 */
	private static function uploader(): Form_File_Uploader {
		return new Form_File_Uploader( 'wp_handle_sideload' );
	}

	public function test_a_disallowed_type_is_refused_by_detected_content(): void {
		$text = $this->upload( 'notes.txt', 'text/plain', 'plain text' );
		$php  = $this->upload( 'evil.php', 'application/x-httpd-php', '<?php system( "id" ); ?>' );

		foreach ( array( $text, $php ) as $file ) {
			$result = ( new Form_Security( 'test' ) )->validate_file_upload( $file, $this->png_policy() );
			$this->assertWPError( $result );
			$this->assertSame( 'invalid_file_type', $result->get_error_code() );
		}
	}

	public function test_the_allowlist_is_required_and_the_claimed_type_must_match_the_content(): void {
		$security = new Form_Security( 'test' );
		$png      = $this->upload( 'pixel.png', 'image/png', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$this->assertSame( 'missing_allowed_file_types', $security->validate_file_upload( $png, array() )->get_error_code() );
		$this->assertTrue( $security->validate_file_upload( $png, $this->png_policy() ) );

		$renamed = array_merge( $png, array( 'name' => 'pixel.txt', 'type' => 'text/plain' ) );
		$this->assertSame( 'invalid_file_type', $security->validate_file_upload( $renamed, array( 'allowed_types' => array( 'text/plain' ) ) )->get_error_code() );
	}

	public function test_svg_is_refused_even_when_a_field_allows_it(): void {
		$svg    = $this->upload( 'image.svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' );
		$result = ( new Form_Security( 'test' ) )->validate_file_upload( $svg, array( 'allowed_types' => array( 'image/svg+xml', 'image/png' ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_file_type', $result->get_error_code() );
	}

	public function test_oversized_empty_and_failed_uploads_are_refused(): void {
		$security = new Form_Security( 'test' );
		$png      = $this->upload( 'pixel.png', 'image/png', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$this->assertSame( 'file_too_large', $security->validate_file_upload( $png, array_merge( $this->png_policy(), array( 'max_size' => 10 ) ) )->get_error_code() );
		$this->assertSame( 'empty_file', $security->validate_file_upload( array_merge( $png, array( 'size' => 0 ) ), $this->png_policy() )->get_error_code() );
		$this->assertSame( 'upload_error', $security->validate_file_upload( array_merge( $png, array( 'error' => UPLOAD_ERR_PARTIAL ) ), $this->png_policy() )->get_error_code() );
	}

	public function test_wordpress_stores_a_traversal_name_inside_the_uploads_directory(): void {
		$file   = $this->upload( '../../evil.png', 'image/png', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$result = self::uploader()->process_upload( $file, $this->png_policy() );

		$this->assertIsArray( $result );
		$uploads = wp_get_upload_dir();
		$this->assertStringStartsWith( wp_normalize_path( $uploads['basedir'] ), wp_normalize_path( dirname( $result['file'] ) ) );
		$this->assertStringNotContainsString( '..', $result['filename'] );
	}

	public function test_a_successful_upload_can_create_an_attachment(): void {
		$file   = $this->upload( 'pixel.png', 'image/png', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$result = self::uploader()->process_upload( $file, array_merge( $this->png_policy(), array( 'create_attachment' => true ) ) );

		$this->assertIsArray( $result );
		$this->assertIsInt( $result['attachment_id'] );
		$this->assertSame( 'attachment', get_post_type( $result['attachment_id'] ) );
	}

	public function test_a_refused_attachment_insert_returns_an_error_and_removes_the_file(): void {
		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		$attachments = $this->attachment_count();
		$file        = $this->upload( 'pixel.png', 'image/png', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$result = self::uploader()->process_upload( $file, array_merge( $this->png_policy(), array( 'create_attachment' => true ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'attachment_failed', $result->get_error_code() );
		$this->assertNotEmpty( $this->stored_files );
		$this->assertFileDoesNotExist( end( $this->stored_files ), 'The stored upload must not be orphaned.' );
		$this->assertSame( $attachments, $this->attachment_count() );
	}

	public function test_a_failed_multi_upload_removes_earlier_files_and_attachments(): void {
		$attachments = $this->attachment_count();
		$good        = $this->upload( 'first.png', 'image/png', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$bad         = $this->upload( 'second.txt', 'text/plain', 'not an image' );
		$files       = array();
		foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $key ) {
			$files[ $key ] = array( $good[ $key ], $bad[ $key ] );
		}

		$result = self::uploader()->process_multiple_uploads( $files, array_merge( $this->png_policy(), array( 'create_attachment' => true ) ) );

		$this->assertWPError( $result );
		$this->assertCount( 1, $this->stored_files, 'Only the first file reached storage.' );
		$this->assertFileDoesNotExist( $this->stored_files[0] );
		$this->assertSame( $attachments, $this->attachment_count() );
	}

	/** @return array<string, mixed> */
	private function png_policy(): array {
		return array(
			'allowed_types' => array( 'image/png' ),
			'max_size'      => 1000000,
		);
	}

	/** @return array<string, mixed> */
	private function upload( string $name, string $type, string $content ): array {
		$path               = (string) tempnam( sys_get_temp_dir(), 'cb_upload_' );
		$this->temp_files[] = $path;
		file_put_contents( $path, $content );

		return array(
			'name'     => $name,
			'type'     => $type,
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen( $content ),
		);
	}

	private function attachment_count(): int {
		return count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	}
}
