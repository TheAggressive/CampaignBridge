<?php
/**
 * File Upload Demo - Test File Upload Functionality
 *
 * This screen demonstrates the secure file upload functionality
 * including single file uploads, multiple file uploads, and
 * various validation scenarios.
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the Form API.
use CampaignBridge\Admin\Core\Form;

?>

<div class="wrap">
	<h1>File Upload Demo - Test Secure Uploads</h1>

	<div class="notice notice-info">
		<p>This page demonstrates the secure file upload functionality. Upload files to test validation, security checks, and WordPress integration.</p>
	</div>

	<div class="upload-demo-section">
		<h2>🖼️ Single Image Upload</h2>
		<p>Upload a single image file. Only JPEG, PNG, GIF, and WebP formats are allowed.</p>
		<p><small><em>Both syntaxes work: ->file(name, label, accept) OR ->file(name, label)->accept(accept)</em></small></p>

		<?php
		Form::make( 'single_image_upload' )
				->file( 'profile_image', 'Profile Image', 'image/*' ) // Convenient: accept parameter built-in.
				->description( 'Upload a profile image (max 2MB)' )
				->max_size( 2097152 ) // 2MB
				->save_to_options( 'file_upload_demo_' )
				->success( 'Image uploaded successfully!' )
				->render();
		?>
	</div>

	<div class="upload-demo-section">
		<h2>📄 Document Upload with Validation</h2>
		<p>'Upload a document. Only PDF, DOC, DOCX, and TXT files are allowed with size limits.</p>

		<?php
		Form::make( 'document_upload' )
			->file( 'document', 'Document File', '.pdf,.doc,.docx,.txt' ) // Convenient: accept parameter built-in.
			->description( 'Upload a document (max 5MB, PDF/DOC/TXT only)' )
			->max_size( 5242880 ) // 5MB
			->required()
			->save_to_options( 'file_upload_demo_' )
			->success( 'Document uploaded successfully!' )
			->render();
		?>
	</div>

	<div class="upload-demo-section">
		<h2>📎 Multiple File Upload</h2>
		<p>Upload multiple files at once. Maximum 3 files, various formats allowed.</p>

		<?php
		Form::make( 'multiple_files_upload' )
			->file( 'files', 'Upload Files' )
			->accept( 'image/*,.pdf,.txt' )
			->description( 'Upload multiple files (max 3 files, 1MB each)' )
			->max_size( 1048576 ) // 1MB per file
			->multiple_files() // Clearer naming than multiple().
			->save_to_options( 'file_upload_demo_' )
			->success( 'Files uploaded successfully!' )
			->render();
		?>
	</div>

	<div class="upload-demo-section">
		<h2>🛡️ Security Test - Dangerous Files</h2>
		<p>Test security by trying to upload potentially dangerous files (PHP, executable files, etc.). These should be rejected.</p>

		<?php
		Form::make( 'security_test_upload' )
			->file( 'test_file', 'Test File Upload' )
			->description( 'Try uploading .php, .exe, or other dangerous file types' )
			->save_to_options( 'file_upload_demo_' )
			->render();
		?>
	</div>

	<div class="upload-demo-section">
		<h2>📊 Upload Statistics</h2>
		<div class="upload-stats">
			<?php
			$uploaded_files = get_option( 'file_upload_demo_profile_image', array() );
			$document_files = get_option( 'file_upload_demo_document', array() );
			$multiple_files = get_option( 'file_upload_demo_files', array() );

			?>

			<div class="stat-card">
				<h4>Profile Images</h4>
				<?php if ( ! empty( $uploaded_files ) && isset( $uploaded_files['url'] ) ) : ?>
					<p><strong>File:</strong> <?php echo esc_html( $uploaded_files['filename'] ?? 'Unknown' ); ?></p>
					<p><strong>URL:</strong> <a href="<?php echo esc_url( $uploaded_files['url'] ); ?>" target="_blank"><?php echo esc_html( $uploaded_files['url'] ); ?></a></p>
					<p><strong>Size:</strong>
					<?php
						$file_path = $uploaded_files['file'] ?? '';
					if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
						echo esc_html( size_format( filesize( $file_path ) ) );
					} else {
						'File not found';
					}
					?>
					</p>
				<?php else : ?>
					<p>No profile image uploaded yet.</p>
				<?php endif; ?>
			</div>

			<div class="stat-card">
				<h4>Documents</h4>
				<?php if ( ! empty( $document_files ) && isset( $document_files['url'] ) ) : ?>
					<p><strong>File:</strong> <?php echo esc_html( $document_files['filename'] ?? 'Unknown' ); ?></p>
					<p><strong>URL:</strong> <a href="<?php echo esc_url( $document_files['url'] ); ?>" target="_blank"><?php echo esc_html( $document_files['url'] ); ?></a></p>
					<p><strong>Size:</strong>
					<?php
						$file_path = $document_files['file'] ?? '';
					if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
						echo esc_html( size_format( filesize( $file_path ) ) );
					} else {
						_e( 'File not found', 'campaignbridge' );
					}
					?>
					</p>
				<?php else : ?>
					<p><?php _e( 'No document uploaded yet.', 'campaignbridge' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="stat-card">
				<h4><?php _e( 'Multiple Files', 'campaignbridge' ); ?></h4>
				<?php if ( ! empty( $multiple_files ) && is_array( $multiple_files ) ) : ?>
					<p><strong><?php _e( 'Files Uploaded:', 'campaignbridge' ); ?></strong> <?php echo count( $multiple_files ); ?></p>
					<ul>
					<?php foreach ( $multiple_files as $file ) : ?>
						<?php if ( isset( $file['url'] ) ) : ?>
							<li><a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank"><?php echo esc_html( $file['filename'] ?? 'Unknown' ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php _e( 'No files uploaded yet.', 'campaignbridge' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="upload-demo-section">
		<h2><?php _e( '🧹 Clear Test Data', 'campaignbridge' ); ?></h2>
		<p><?php _e( 'Remove all uploaded test files and reset the demo.', 'campaignbridge' ); ?></p>

		<?php
		Form::make( 'clear_demo_data' )
			->hidden( 'confirm_clear', '1' )
			->submit( 'Clear All Test Data', 'secondary' )
			->before_save(
				function ( $data ) {
					if ( isset( $data['confirm_clear'] ) && $data['confirm_clear'] === '1' ) {
							// Delete uploaded files
							$options_to_delete = array(
								'file_upload_demo_profile_image',
								'file_upload_demo_document',
								'file_upload_demo_files',
							);

						foreach ( $options_to_delete as $option_name ) {
							$option_value = get_option( $option_name );
							if ( $option_value ) {
								// Delete single files
								if ( isset( $option_value['file'] ) && file_exists( $option_value['file'] ) ) {
											unlink( $option_value['file'] );
								}
								// Delete multiple files
								elseif ( is_array( $option_value ) ) {
									foreach ( $option_value as $file_data ) {
										if ( isset( $file_data['file'] ) && file_exists( $file_data['file'] ) ) {
											unlink( $file_data['file'] );
										}
									}
								}
								delete_option( $option_name );
							}
						}
					}
				}
			)
			->success( 'Test data cleared successfully!' )
			->render();
		?>
	</div>
</div>

<style>
.upload-demo-section {
	margin: 40px 0;
	padding: 20px;
	background: #fff;
	border: 1px solid #e5e5e5;
	border-radius: 4px;
}

.upload-demo-section h2 {
	margin-top: 0;
	color: #23282d;
	border-bottom: 1px solid #e5e5e5;
	padding-bottom: 10px;
}

.upload-demo-section p {
	color: #666;
	margin-bottom: 20px;
}

.upload-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.stat-card {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 4px;
	border: 1px solid #dee2e6;
}

.stat-card h4 {
	margin: 0 0 15px 0;
	color: #495057;
	font-size: 16px;
}

.stat-card p {
	margin: 8px 0;
	font-size: 14px;
}

.stat-card a {
	color: #007cba;
	text-decoration: none;
}

.stat-card a:hover {
	text-decoration: underline;
}

.stat-card ul {
	margin: 10px 0 0 20px;
	padding: 0;
}

.stat-card li {
	margin-bottom: 5px;
}

/* Form styling */
.campaignbridge-form {
	margin: 20px 0;
}

.campaignbridge-form .form-table th {
	font-weight: 600;
}

/* File input styling */
.campaignbridge-form input[type="file"] {
	margin: 5px 0;
}

/* Success/error message styling */
.campaignbridge-form .notice {
	margin: 10px 0;
}

/* Description styling */
.campaignbridge-form .description {
	font-style: italic;
	color: #666;
	margin-top: 5px;
	font-size: 13px;
}

/* File requirements styling */
.file-requirements {
	margin-top: 8px;
	font-size: 12px;
	color: #666;
}

.file-requirements p {
	margin: 0;
}
</style>

<script>
// Add some client-side enhancements
document.addEventListener('DOMContentLoaded', function() {
	// Add visual feedback for file selection
	document.querySelectorAll('input[type="file"]').forEach(function(input) {
		input.addEventListener('change', function(e) {
			const file = e.target.files[0];
			if (file) {
				console.log('File selected:', file.name, 'Size:', (file.size / 1024 / 1024).toFixed(2) + 'MB');
			}
		});
	});

	// Add confirmation for clearing data
	const clearForm = document.querySelector('form[name="clear_demo_data"]');
	if (clearForm) {
		clearForm.addEventListener('submit', function(e) {
			const confirmed = confirm('<?php _e( 'Are you sure you want to delete all uploaded test files? This action cannot be undone.', 'campaignbridge' ); ?>');
			if (!confirmed) {
				e.preventDefault();
			}
		});
	}
});
</script>
