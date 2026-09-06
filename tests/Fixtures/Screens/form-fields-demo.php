<?php
/**
 * Form Fields Demo Screen
 *
 * Auto-discovered admin screen to demonstrate all available form fields and conditional logic.
 *
 * Available variables:
 * - $screen: Screen_Context object with helper methods
 * - $controller: Controller instance (if configured)
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the form system.
use CampaignBridge\Admin\Core\Form;

?>

<div class="wrap">
	<h1><?php esc_html_e( 'Form Fields & Conditional Logic Demo', 'campaignbridge' ); ?></h1>

	<p><?php esc_html_e( 'This comprehensive demo showcases all available form fields and their conditional logic capabilities. Each section demonstrates different field types and how they interact with conditional rules.', 'campaignbridge' ); ?></p>

	<div class="campaignbridge-form-container">
		<?php
		$form = Form::make( 'form_fields_demo' )
			->auto_layout() // Layout for admin screens.
			->save_to_options() // Uses default: campaignbridge_form_fields_demo_.
			->success() // Auto-generated: "Configuration saved successfully!".
			->submit(); // Auto-generated: "Save Configuration".

		// ========================================
		// BASIC FIELD TYPES DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Basic Field Types', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Demonstrates all basic form field types without conditions.', 'campaignbridge' ) . '</p>';

		$form->text( 'basic_text' )
			->label( 'Text Input' )
			->placeholder( 'Enter some text...' )
			->description( 'A standard text input field' );

		$form->number( 'basic_number' )
			->label( 'Number Input' )
			->default( 42 )
			->min( 0 )
			->max( 100 )
			->description( 'A number input with min/max validation' );

		$form->textarea( 'basic_textarea' )
			->label( 'Textarea' )
			->placeholder( 'Enter longer text here...' )
			->rows( 3 )
			->description( 'A multi-line text input field' );

		$form->checkbox( 'basic_checkbox' )
			->label( 'Single Checkbox' )
			->description( 'A single checkbox field' );

		$form->select( 'basic_select' )
			->label( 'Select Dropdown' )
			->options(
				array(
					'option1' => 'Option 1',
					'option2' => 'Option 2',
					'option3' => 'Option 3',
				)
			)
			->default( 'option2' )
			->description( 'A dropdown select field' );

		$form->radio( 'basic_radio' )
			->label( 'Radio Buttons' )
			->options(
				array(
					'radio1' => 'Radio Option 1',
					'radio2' => 'Radio Option 2',
					'radio3' => 'Radio Option 3',
				)
			)
			->default( 'radio1' )
			->description( 'Radio button selection' );

		$form->switch( 'basic_switch' )
			->label( 'Toggle Switch' )
			->description( 'A modern toggle switch' );

		$form->encrypted( 'basic_encrypted' )
			->label( 'Encrypted Field' )
			->description( 'Field value is encrypted before storage' );

		$form->file( 'basic_file' )
			->label( 'File Upload' )
			->accept( 'image/*,.pdf,.doc,.docx' )
			->description( 'File upload with type restrictions' );

		$form->wysiwyg( 'basic_wysiwyg' )
			->label( 'Rich Text Editor' )
			->description( 'WordPress TinyMCE editor field' );

		// ========================================
		// SIMPLE CONDITIONAL DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Simple Conditional Logic', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Fields that show/hide based on single conditions.', 'campaignbridge' ) . '</p>';

		$form->checkbox( 'enable_feature_a' )
			->label( 'Enable Feature A' )
			->description( 'Toggle to show/hide related fields' );

		$form->text( 'feature_a_name' )
			->label( 'Feature A Name' )
			->show_when(
				array(
					array(
						'field'    => 'enable_feature_a',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Only shown when Feature A is enabled' );

		$form->select( 'feature_a_type' )
			->label( 'Feature A Type' )
			->options(
				array(
					'type1' => 'Type 1',
					'type2' => 'Type 2',
					'type3' => 'Type 3',
				)
			)
			->show_when(
				array(
					array(
						'field'    => 'enable_feature_a',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Only shown when Feature A is enabled' );

		$form->checkbox( 'enable_feature_b' )
			->label( 'Enable Feature B' )
			->description( 'Another toggle for different fields' );

		$form->number( 'feature_b_count' )
			->label( 'Feature B Count' )
			->default( 5 )
			->min( 1 )
			->max( 50 )
			->show_when(
				array(
					array(
						'field'    => 'enable_feature_b',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Only shown when Feature B is enabled' );

		$form->textarea( 'feature_b_description' )
			->label( 'Feature B Description' )
			->rows( 2 )
			->show_when(
				array(
					array(
						'field'    => 'enable_feature_b',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Only shown when Feature B is enabled' );

		// ========================================
		// SELECT-BASED CONDITIONAL DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Select-Based Conditions', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Fields that appear based on select dropdown values.', 'campaignbridge' ) . '</p>';

		$form->select( 'content_type' )
			->label( 'Content Type' )
			->options(
				array(
					'text'     => 'Plain Text',
					'html'     => 'HTML Content',
					'markdown' => 'Markdown',
					'json'     => 'JSON Data',
				)
			)
			->default( 'text' )
			->description( 'Choose content type to show appropriate fields' );

		$form->textarea( 'plain_text_content' )
			->label( 'Plain Text Content' )
			->rows( 4 )
			->show_when(
				array(
					array(
						'field'    => 'content_type',
						'operator' => 'equals',
						'value'    => 'text',
					),
				)
			)
			->description( 'Simple text content' );

		$form->wysiwyg( 'html_content' )
			->label( 'HTML Content' )
			->show_when(
				array(
					array(
						'field'    => 'content_type',
						'operator' => 'equals',
						'value'    => 'html',
					),
				)
			)
			->description( 'Rich HTML content with formatting' );

		$form->textarea( 'markdown_content' )
			->label( 'Markdown Content' )
			->rows( 6 )
			->show_when(
				array(
					array(
						'field'    => 'content_type',
						'operator' => 'equals',
						'value'    => 'markdown',
					),
				)
			)
			->description( 'Markdown formatted content' );

		$form->textarea( 'json_content' )
			->label( 'JSON Data' )
			->rows( 8 )
			->show_when(
				array(
					array(
						'field'    => 'content_type',
						'operator' => 'equals',
						'value'    => 'json',
					),
				)
			)
			->description( 'JSON formatted data' );

		// ========================================
		// COMPLEX CONDITIONAL DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Complex Conditional Logic', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Fields with multiple conditions using AND/OR logic.', 'campaignbridge' ) . '</p>';

		$form->checkbox( 'enable_advanced_mode' )
			->label( 'Enable Advanced Mode' )
			->description( 'Unlock advanced configuration options' );

		$form->select( 'user_role' )
			->label( 'User Role' )
			->options(
				array(
					'admin'       => 'Administrator',
					'editor'      => 'Editor',
					'author'      => 'Author',
					'contributor' => 'Contributor',
				)
			)
			->default( 'author' )
			->description( 'Select user role for permissions' );

		$form->checkbox( 'enable_security' )
			->label( 'Enable Security Features' )
			->description( 'Enable additional security measures' );

		$form->encrypted( 'api_secret' )
			->label( 'API Secret Key' )
			->required()
			->show_when(
				array(
					array(
						'field'    => 'enable_advanced_mode',
						'operator' => 'is_checked',
					),
					array(
						'field'    => 'user_role',
						'operator' => 'equals',
						'value'    => 'admin',
					),
				)
			)
			->description( 'Secret key for advanced API access (requires Advanced Mode AND Admin role)' );

		$form->select( 'security_level' )
			->label( 'Security Level' )
			->options(
				array(
					'low'     => 'Low Security',
					'medium'  => 'Medium Security',
					'high'    => 'High Security',
					'maximum' => 'Maximum Security',
				)
			)
			->default( 'medium' )
			->show_when(
				array(
					array(
						'field'    => 'enable_security',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Security level when security features are enabled' );

		$form->checkbox( 'enable_audit_log' )
			->label( 'Enable Audit Logging' )
			->show_when(
				array(
					array(
						'field'    => 'enable_security',
						'operator' => 'is_checked',
					),
					array(
						'field'    => 'security_level',
						'operator' => 'in',
						'value'    => array( 'high', 'maximum' ),
					),
				)
			)
			->description( 'Log all actions when high security is enabled' );

		$form->number( 'log_retention_days' )
			->label( 'Log Retention (Days)' )
			->default( 90 )
			->min( 7 )
			->max( 365 )
			->show_when(
				array(
					array(
						'field'    => 'enable_audit_log',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'How long to keep audit logs' );

		$form->checkbox( 'enable_2fa' )
			->label( 'Enable Two-Factor Authentication' )
			->show_when(
				array(
					array(
						'field'    => 'enable_security',
						'operator' => 'is_checked',
					),
					array(
						'field'    => 'security_level',
						'operator' => 'equals',
						'value'    => 'maximum',
					),
				)
			)
			->description( 'Require 2FA for maximum security' );

		$form->text( 'backup_email' )
			->label( 'Backup Email' )
			->show_when(
				array(
					array(
						'field'    => 'enable_2fa',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Email for 2FA recovery' );

		// ========================================
		// NEGATIVE CONDITIONS DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Negative Conditions', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Fields that show when conditions are NOT met.', 'campaignbridge' ) . '</p>';

		$form->switch( 'simple_mode' )
			->label( 'Use Simple Mode' )
			->description( 'When enabled, hides advanced options' );

		$form->select( 'database_type' )
			->label( 'Database Type' )
			->options(
				array(
					'mysql'    => 'MySQL',
					'postgres' => 'PostgreSQL',
					'sqlite'   => 'SQLite',
				)
			)
			->default( 'mysql' )
			->show_when(
				array(
					array(
						'field'    => 'simple_mode',
						'operator' => 'not_checked',
					),
				)
			)
			->description( 'Database type (hidden in simple mode)' );

		$form->encrypted( 'db_password' )
			->label( 'Database Password' )
			->show_when(
				array(
					array(
						'field'    => 'simple_mode',
						'operator' => 'not_checked',
					),
					array(
						'field'    => 'database_type',
						'operator' => 'not_equals',
						'value'    => 'sqlite',
					),
				)
			)
			->description( 'Database password (hidden in simple mode and for SQLite)' );

		$form->text( 'sqlite_path' )
			->label( 'SQLite Database Path' )
			->placeholder( '/path/to/database.db' )
			->show_when(
				array(
					array(
						'field'    => 'simple_mode',
						'operator' => 'not_checked',
					),
					array(
						'field'    => 'database_type',
						'operator' => 'equals',
						'value'    => 'sqlite',
					),
				)
			)
			->description( 'File path for SQLite database' );

		// ========================================
		// DYNAMIC CONDITIONS DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Dynamic Conditions', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Conditions based on multiple field combinations.', 'campaignbridge' ) . '</p>';

		$form->select( 'environment' )
			->label( 'Environment' )
			->options(
				array(
					'development' => 'Development',
					'staging'     => 'Staging',
					'production'  => 'Production',
				)
			)
			->default( 'development' )
			->description( 'Select the current environment' );

		$form->checkbox( 'enable_debug' )
			->label( 'Enable Debug Mode' )
			->description( 'Show debug information' );

		$form->checkbox( 'enable_cache' )
			->label( 'Enable Caching' )
			->default( true )
			->description( 'Use caching for performance' );

		$form->text( 'debug_email' )
			->label( 'Debug Email Recipient' )
			->show_when(
				array(
					array(
						'field'    => 'enable_debug',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Email for debug notifications' );

		$form->number( 'cache_ttl' )
			->label( 'Cache TTL (seconds)' )
			->default( 300 )
			->min( 60 )
			->max( 3600 )
			->show_when(
				array(
					array(
						'field'    => 'enable_cache',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Cache time-to-live' );

		$form->encrypted( 'production_api_key' )
			->label( 'Production API Key' )
			->required()
			->show_when(
				array(
					array(
						'field'    => 'environment',
						'operator' => 'equals',
						'value'    => 'production',
					),
				)
			)
			->description( 'API key required for production environment' );

		$form->checkbox( 'enable_monitoring' )
			->label( 'Enable Monitoring' )
			->show_when(
				array(
					array(
						'field'    => 'environment',
						'operator' => 'in',
						'value'    => array( 'staging', 'production' ),
					),
				)
			)
			->description( 'Enable monitoring for staging and production' );

		$form->text( 'monitoring_endpoint' )
			->label( 'Monitoring Endpoint' )
			->placeholder( 'https://monitoring.example.com/webhook' )
			->show_when(
				array(
					array(
						'field'    => 'enable_monitoring',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Webhook URL for monitoring alerts' );

		// ========================================
		// VALIDATION DEMO
		// ========================================
		echo '<h3>' . esc_html__( 'Field Validation Demo', 'campaignbridge' ) . '</h3>';
		echo '<p>' . esc_html__( 'Fields with various validation rules that are conditionally required.', 'campaignbridge' ) . '</p>';

		$form->checkbox( 'require_validation' )
			->label( 'Enable Validation Rules' )
			->description( 'When checked, validation becomes required for certain fields' );

		$form->text( 'required_text' )
			->label( 'Required Text Field' )
			->show_when(
				array(
					array(
						'field'    => 'require_validation',
						'operator' => 'is_checked',
					),
				)
			)
			->required()
			->description( 'This field becomes required when validation is enabled' );

		$form->text( 'email_field' )
			->label( 'Email Address' )
			->placeholder( 'user@example.com' )
			->show_when(
				array(
					array(
						'field'    => 'require_validation',
						'operator' => 'is_checked',
					),
				)
			)
			->required()
			->description( 'Must be a valid email address when validation is enabled' );

		$form->number( 'age_field' )
			->label( 'Age' )
			->min( 13 )
			->max( 120 )
			->show_when(
				array(
					array(
						'field'    => 'require_validation',
						'operator' => 'is_checked',
					),
				)
			)
			->required()
			->description( 'Must be between 13-120 when validation is enabled' );

		$form->file( 'document_upload' )
			->label( 'Document Upload' )
			->accept( '.pdf,.doc,.docx' )
			->show_when(
				array(
					array(
						'field'    => 'require_validation',
						'operator' => 'is_checked',
					),
				)
			)
			->required()
			->description( 'Document upload becomes required when validation is enabled' );

		$form->submit( 'Save All Settings', 'primary large' );

		$form->render();
		?>
	</div>

	<div class="campaignbridge-demo-info" style="margin-top: 40px; padding: 20px; background: #f1f1f1; border-left: 4px solid #0073aa;">
		<h3><?php esc_html_e( 'Form Fields & Conditional Logic Guide', 'campaignbridge' ); ?></h3>

		<h4><?php esc_html_e( 'Available Field Types', 'campaignbridge' ); ?></h4>
		<ul>
			<li><strong>text:</strong> Single-line text input</li>
			<li><strong>number:</strong> Numeric input with min/max validation</li>
			<li><strong>textarea:</strong> Multi-line text input</li>
			<li><strong>select:</strong> Dropdown selection</li>
			<li><strong>radio:</strong> Radio button selection</li>
			<li><strong>checkbox:</strong> Single or multiple checkboxes</li>
			<li><strong>switch:</strong> Modern toggle switch</li>
			<li><strong>file:</strong> File upload with type restrictions</li>
			<li><strong>wysiwyg:</strong> WordPress rich text editor</li>
			<li><strong>encrypted:</strong> Encrypted field storage</li>
		</ul>

		<h4><?php esc_html_e( 'Conditional Operators', 'campaignbridge' ); ?></h4>
		<ul>
			<li><strong>is_checked:</strong> Checkbox/radio/switch is selected</li>
			<li><strong>not_checked:</strong> Checkbox/radio/switch is not selected</li>
			<li><strong>equals:</strong> Field value equals specified value</li>
			<li><strong>not_equals:</strong> Field value does not equal specified value</li>
			<li><strong>in:</strong> Field value is in array of values</li>
			<li><strong>not_in:</strong> Field value is not in array of values</li>
		</ul>

		<h4><?php esc_html_e( 'Key Features Demonstrated', 'campaignbridge' ); ?></h4>
		<ul>
			<li><strong>Real-time Updates:</strong> Conditions update instantly without page reload</li>
			<li><strong>Server-side Security:</strong> Conditions enforced server-side for security</li>
			<li><strong>Validation Integration:</strong> Required fields only validated when visible</li>
			<li><strong>Complex Logic:</strong> Multiple conditions with AND logic</li>
			<li><strong>Negative Conditions:</strong> Show fields when conditions are NOT met</li>
			<li><strong>Nested Dependencies:</strong> Fields dependent on other conditional fields</li>
			<li><strong>Performance Optimized:</strong> Hidden fields excluded from processing</li>
		</ul>

		<h4><?php esc_html_e( 'Usage Tips', 'campaignbridge' ); ?></h4>
		<ul>
			<li>Use <code>show_when()</code> method to define conditional logic</li>
			<li>All conditions in an array use AND logic (all must be true)</li>
			<li>Multiple condition arrays use OR logic (any can be true)</li>
			<li>Hidden fields are completely excluded from validation and submission</li>
			<li>Encrypted fields automatically handle secure storage</li>
			<li>File uploads include automatic security scanning</li>
		</ul>
	</div>
</div>
