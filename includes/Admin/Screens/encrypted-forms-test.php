<?php
/**
 * Encrypted Forms Test Screen
 *
 * Comprehensive test of encrypted form fields with different security contexts.
 * Demonstrates API key, sensitive data, personal data, and public data encryption.
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the form system.
require_once __DIR__ . '/../Core/Form.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Builder.php';
require_once __DIR__ . '/../Core/Forms/Form_Renderer.php';
require_once __DIR__ . '/../Core/Forms/Form_Handler.php';
require_once __DIR__ . '/../Core/Forms/Form_Data_Manager.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Interface.php';
require_once __DIR__ . '/../Core/Forms/Form_Security.php';
require_once __DIR__ . '/../Core/Forms/Form_Validator.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Factory.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Base.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Input.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Encrypted.php';

// ============================================================================
// ENCRYPTED FORMS TEST
// ============================================================================

$encrypted_test_form = \CampaignBridge\Admin\Core\Form::make( 'encrypted_forms_test' )
	->save_to_options( 'encrypted_test_' )
	->table()
	->success( 'Encrypted form data saved successfully!' )
	->error( 'Please correct the errors below.' )

	// Regular text field for comparison.
	->text( 'regular_field', 'Regular Text Field' )
		->description( 'This is a regular field for comparison.' )
		->default( 'Sample regular text' )

	// API Key field - admin-only viewing (highest security).
	->encrypted( 'api_key_field', 'API Key (Admin Only)' )
		->context( 'api_key' )
		->description( 'API key - only administrators can view decrypted values. Shows as masked dots to regular users.' )
		->placeholder( 'sk-...' )

	// Sensitive data field - admin-only viewing.
	->encrypted( 'sensitive_field', 'Sensitive Data' )
		->context( 'sensitive' )
		->description( 'Sensitive data - only administrators can view decrypted values.' )
		->placeholder( 'Enter sensitive information' )

	// Personal data field - logged-in users can view their own data.
	->encrypted( 'personal_field', 'Personal Data' )
		->context( 'personal' )
		->description( 'Personal data - logged-in users can view their own decrypted data.' )
		->placeholder( 'Your personal information' )

	// Public encrypted data - no restrictions.
	->encrypted( 'public_field', 'Public Encrypted Data' )
		->context( 'public' )
		->description( 'Public encrypted data - no permission restrictions for viewing.' )
		->placeholder( 'Publicly shareable data' )

	// Test field with reveal disabled.
	->encrypted( 'no_reveal_field', 'Field Without Reveal' )
		->context( 'sensitive' )
		->show_reveal( false )
		->description( 'This field has reveal functionality disabled.' )

	// Test field with edit disabled.
	->encrypted( 'read_only_field', 'Read-Only Encrypted Field' )
		->context( 'api_key' )
		->show_edit( false )
		->description( 'This field can only be viewed, not edited.' )

	->submit( 'Save Encrypted Form Data' );

?>
<div class="wrap">
	<h1>🔐 Encrypted Forms Test</h1>

	<div class="encrypted-test-intro">
		<p>🎯 This test demonstrates the <strong>encrypted form fields</strong> with different security contexts and permission levels.</p>

		<div class="security-contexts">
			<div class="context-item">
				<h4>🔑 API Key Context</h4>
				<p><strong>Permission:</strong> Only administrators</p>
				<p><strong>Use case:</strong> API keys, secrets, credentials</p>
			</div>

			<div class="context-item">
				<h4>🔒 Sensitive Context</h4>
				<p><strong>Permission:</strong> Only administrators</p>
				<p><strong>Use case:</strong> Private configuration, sensitive settings</p>
			</div>

			<div class="context-item">
				<h4>👤 Personal Context</h4>
				<p><strong>Permission:</strong> Logged-in users (own data)</p>
				<p><strong>Use case:</strong> User-specific encrypted data</p>
			</div>

			<div class="context-item">
				<h4>🌐 Public Context</h4>
				<p><strong>Permission:</strong> No restrictions</p>
				<p><strong>Use case:</strong> Publicly shareable encrypted data</p>
			</div>
		</div>
	</div>

	<div class="encrypted-test-features">
		<h3>✨ Features Demonstrated</h3>
		<ul class="features-list">
			<li><strong>Masked Display:</strong> Sensitive data shows as ••••••••abcd</li>
			<li><strong>Context-Aware Permissions:</strong> Different access levels per field</li>
			<li><strong>Reveal on Demand:</strong> Click to temporarily show full values</li>
			<li><strong>Secure Editing:</strong> Values never exposed in HTML, encrypted server-side</li>
			<li><strong>Permission-Based UI:</strong> Controls hide/show based on user capabilities</li>
			<li><strong>Audit Trail:</strong> All encryption operations are logged</li>
		</ul>
	</div>

	<div class="test-form-section">
		<h2>📝 Test Form</h2>
		<div class="form-instructions">
			<p><strong>Instructions:</strong></p>
			<ol>
				<li>Fill in the encrypted fields with test data</li>
				<li>Notice how fields show as masked dots</li>
				<li>Try clicking "Reveal" buttons (if you have permission)</li>
				<li>Try editing values and saving</li>
				<li>Check different user roles to see permission differences</li>
			</ol>
		</div>

		<?php

		$encrypted_test_form->render();
		?>
	</div>

	<div class="test-results-section">
		<h2>🔍 Current Stored Data (Admin Only)</h2>

		<?php
		// Show current stored data using form's data retrieval methods.
		$stored_data = $encrypted_test_form->data();
		?>

		<div class="stored-data-display">
			<?php
			$field_labels = array(
				'regular_field'   => 'Regular Field',
				'api_key_field'   => 'API Key Field',
				'sensitive_field' => 'Sensitive Field',
				'personal_field'  => 'Personal Field',
				'public_field'    => 'Public Field',
			);
			foreach ( $stored_data as $field => $value ) :
				if ( ! isset( $field_labels[ $field ] ) ) {
					continue; // Skip fields not in our display list.
				}
				$display_value = empty( $value ) ? 'Not set' : $value;
				?>
				<div class="data-item">
					<strong><?php echo esc_html( $field_labels[ $field ] ); ?>:</strong>
					<code><?php echo esc_html( $display_value ); ?></code>
					<?php if ( strpos( $display_value, 'encrypted:' ) === 0 ) : ?>
						<span class="encrypted-indicator">🔐 Encrypted</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="note">
			<strong>Note:</strong> This display shows the actual stored values retrieved through our form storage abstraction.
			Encrypted fields show their encrypted values with the "encrypted:" prefix indicating AES-256-GCM encryption.
		</p>
	</div>

	<div class="security-notes">
		<h2>🛡️ Security Notes</h2>
		<ul>
			<li><strong>Military-grade encryption:</strong> AES-256-GCM with authenticated encryption</li>
			<li><strong>Secure key management:</strong> Keys are managed securely and rotated regularly</li>
			<li><strong>Context-aware permissions:</strong> Access control based on security context</li>
			<li><strong>No plain text exposure:</strong> Values are never sent as plain text in HTML</li>
			<li><strong>Server-side encryption:</strong> All encryption/decryption happens server-side</li>
			<li><strong>Audit logging:</strong> All encryption operations are logged for security monitoring</li>
		</ul>
	</div>
</div>

<style>
/* ============================================================================
	ENCRYPTED FORMS TEST STYLES
	============================================================================ */

.encrypted-test-intro {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 30px;
	margin: 20px 0;
	border-radius: 12px;
	box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.encrypted-test-intro p {
	font-size: 18px;
	margin-bottom: 20px;
	text-align: center;
}

.security-contexts {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 30px;
}

.context-item {
	background: rgba(255,255,255,0.15);
	padding: 20px;
	border-radius: 8px;
	backdrop-filter: blur(10px);
	border: 1px solid rgba(255,255,255,0.2);
}

.context-item h4 {
	margin: 0 0 10px 0;
	font-size: 16px;
}

.context-item p {
	margin: 5px 0;
	font-size: 14px;
	opacity: 0.9;
}

.encrypted-test-features {
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	padding: 20px;
	margin: 30px 0;
}

.encrypted-test-features h3 {
	margin-top: 0;
	color: #007cba;
}

.features-list {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 10px;
	margin-top: 15px;
}

.features-list li {
	padding: 8px 0;
	border-bottom: 1px solid #f0f0f0;
}

.features-list li:last-child {
	border-bottom: none;
}

.test-form-section {
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	margin: 30px 0;
	overflow: hidden;
}

.test-form-section h2 {
	background: #f8f9fa;
	margin: 0;
	padding: 20px;
	border-bottom: 1px solid #e1e5e9;
	font-size: 20px;
}

.form-instructions {
	background: #e3f2fd;
	padding: 15px;
	margin: 0;
	border-bottom: 1px solid #bbdefb;
}

.form-instructions ol {
	margin: 10px 0 0 20px;
}

.form-instructions li {
	margin: 5px 0;
}

.test-results-section {
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	margin: 30px 0;
	overflow: hidden;
}

.test-results-section h2 {
	background: #f8f9fa;
	margin: 0;
	padding: 20px;
	border-bottom: 1px solid #e1e5e9;
	font-size: 20px;
}

.stored-data-display {
	padding: 20px;
	background: #f8f9fa;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 13px;
}

.data-item {
	display: flex;
	align-items: center;
	gap: 10px;
	margin: 8px 0;
	padding: 8px;
	background: white;
	border-radius: 4px;
	border: 1px solid #e1e5e9;
}

.data-item strong {
	min-width: 120px;
	color: #007cba;
}

.data-item code {
	flex: 1;
	background: #f8f9fa;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 12px;
	word-break: break-all;
}

.encrypted-indicator {
	background: #dc3545;
	color: white;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 10px;
	font-weight: bold;
	text-transform: uppercase;
}

.note {
	background: #fff3cd;
	border: 1px solid #ffeaa7;
	border-radius: 4px;
	padding: 15px;
	margin: 15px;
	color: #856404;
	font-size: 14px;
}

.security-notes {
	background: #d1ecf1;
	border: 1px solid #bee5eb;
	border-radius: 8px;
	padding: 20px;
	margin: 30px 0;
}

.security-notes h2 {
	margin-top: 0;
	color: #0c5460;
}

.security-notes ul {
	margin: 15px 0 0 20px;
}

.security-notes li {
	margin: 8px 0;
	line-height: 1.4;
}

/* Encrypted field specific styles */
.campaignbridge-encrypted-field {
	margin: 10px 0;
}

.campaignbridge-encrypted-display {
	background: #f8f9fa;
	border: 1px solid #ddd;
	padding: 8px 12px;
	border-radius: 4px;
	font-family: monospace;
	color: #666;
}

.campaignbridge-encrypted-controls {
	margin-top: 8px;
}

.campaignbridge-encrypted-controls .button {
	margin-right: 8px;
}

.campaignbridge-encrypted-edit {
	margin-top: 8px;
}

.campaignbridge-edit-controls {
	margin-top: 8px;
}

.campaignbridge-permission-denied {
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	padding: 15px;
	border-radius: 4px;
}

.campaignbridge-permission-denied .regular-text {
	background: #f8f9fa;
	border-color: #dc3545;
	color: #dc3545;
	font-weight: bold;
}

.campaignbridge-permission-notice {
	margin-top: 10px;
	color: #721c24;
	font-size: 13px;
}

.campaignbridge-permission-notice .dashicons {
	color: #dc3545;
	margin-right: 5px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
	.security-contexts {
		grid-template-columns: 1fr;
	}

	.features-list {
		grid-template-columns: 1fr;
	}

	.data-item {
		flex-direction: column;
		align-items: flex-start;
		gap: 5px;
	}

	.data-item strong {
		min-width: auto;
	}
}
</style>
