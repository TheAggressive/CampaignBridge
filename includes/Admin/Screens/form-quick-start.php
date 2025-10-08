<?php
/**
 * Form System - Quick Start Guide
 *
 * Real examples you can copy-paste right now!
 */

// Include the Form system (add this to any screen file)
require_once __DIR__ . '/../Core/Form.php';

?>

<div class="wrap">
	<h1><?php _e( 'Form System - Quick Start Guide', 'campaignbridge' ); ?></h1>

	<div class="quick-start-intro">
		<p><?php _e( 'Here are practical examples you can copy-paste into your screens RIGHT NOW. Everything is secure, accessible, and production-ready.', 'campaignbridge' ); ?></p>
	</div>

	<div class="examples-grid">

		<!-- Example 1: Simple Contact Form -->
		<div class="example-card">
			<h3><?php _e( '📧 Contact Form (3 lines)', 'campaignbridge' ); ?></h3>
			<div class="code-block">
				<pre><code>// Copy this into any screen file
$form = \CampaignBridge\Admin\Core\Form::contact('contact_form')
	->afterSave(function($data) {
		wp_mail('admin@example.com', 'Contact: ' . $data['subject'], $data['message']);
	});

$form->render();</code></pre>
			</div>
			<p><?php _e( 'Creates a complete contact form with name, email, subject, message fields. Automatically sends email on submit.', 'campaignbridge' ); ?></p>
		</div>

		<!-- Example 2: Settings Form -->
		<div class="example-card">
			<h3><?php _e( '⚙️ Plugin Settings (5 lines)', 'campaignbridge' ); ?></h3>
			<div class="code-block">
				<pre><code>$form = \CampaignBridge\Admin\Core\Form::settings('my_settings')
	->text('api_key', 'API Key')->required()->placeholder('Enter your API key')
	->email('admin_email', 'Admin Email')->default(get_option('admin_email'))
	->checkbox('debug_mode', 'Enable Debug')->default(false)
	->number('cache_timeout', 'Cache Timeout')->min(60)->max(3600)->default(300);

$form->render();</code></pre>
			</div>
			<p><?php _e( 'Creates a settings form that saves to WordPress options automatically. Includes validation and defaults.', 'campaignbridge' ); ?></p>
		</div>

		<!-- Example 3: Custom Form -->
		<div class="example-card">
			<h3><?php _e( '🎯 Custom Form (8 lines)', 'campaignbridge' ); ?></h3>
			<div class="code-block">
				<pre><code>$form = \CampaignBridge\Admin\Core\Form::make('custom_form')
	->text('project_name')->required()->label('Project Name')
	->select('priority')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])
	->textarea('description')->rows(4)->placeholder('Describe your project...')
	->checkbox('urgent')->label('Mark as urgent')
	->file('attachment')->accept('.pdf,.doc,.docx')->label('Attach file')
	->submit('Create Project');

$form->render();</code></pre>
			</div>
			<p><?php _e( 'Build any custom form with the fluent API. Everything chains together naturally.', 'campaignbridge' ); ?></p>
		</div>

		<!-- Example 4: User Registration -->
		<div class="example-card">
			<h3><?php _e( '👤 User Registration (6 lines)', 'campaignbridge' ); ?></h3>
			<div class="code-block">
				<pre><code>$form = \CampaignBridge\Admin\Core\Form::register('user_registration')
	->beforeValidate(function($data) {
		if ($data['password'] !== $data['password_confirm']) {
			throw new Exception('Passwords do not match!');
		}
	})
	->afterSave(function($data, $result) {
		if ($result) {
			wp_mail($data['email'], 'Welcome!', 'Your account has been created.');
		}
	});

$form->render();</code></pre>
			</div>
			<p><?php _e( 'Pre-built registration form with password confirmation validation and welcome email.', 'campaignbridge' ); ?></p>
		</div>

	</div>

	<div class="integration-guide">
		<h2><?php _e( '🔧 How to Add to Your Plugin', 'campaignbridge' ); ?></h2>

		<h3><?php _e( 'Step 1: Include the Form System', 'campaignbridge' ); ?></h3>
		<div class="code-block">
			<pre><code>// Add this to the top of any screen file
require_once __DIR__ . '/../Core/Form.php';

// Or add to your main plugin file to autoload
require_once plugin_dir_path(__FILE__) . 'includes/Admin/Core/Form.php';</code></pre>
		</div>

		<h3><?php _e( 'Step 2: Create Your Form', 'campaignbridge' ); ?></h3>
		<div class="code-block">
			<pre><code>// In your screen file (e.g., includes/Admin/Screens/my-form.php)
$form = \CampaignBridge\Admin\Core\Form::make('my_form_id')
	->text('field_name')->required()
	->email('email_field')->required()
	->submit('Save Data');

$form->render();</code></pre>
		</div>

		<h3><?php _e( 'Step 3: Handle Form State (Optional)', 'campaignbridge' ); ?></h3>
		<div class="code-block">
			<pre><code>// Check if form was submitted and show messages
if ($form->submitted() && $form->valid()) {
	$screen->add_message('Data saved successfully!');
} elseif ($form->submitted()) {
	foreach ($form->errors() as $error) {
		$screen->add_error($error);
	}
}</code></pre>
		</div>
	</div>

	<div class="real-examples">
		<h2><?php _e( '📋 Real Screen Examples', 'campaignbridge' ); ?></h2>

		<h3><?php _e( 'Replace Manual Settings Form', 'campaignbridge' ); ?></h3>
		<div class="code-block">
			<pre><code>// BEFORE: Manual form handling (50+ lines)
// Form HTML, validation, saving, error handling...

// AFTER: Form Builder (10 lines)
$form = \CampaignBridge\Admin\Core\Form::settings('email_settings')
	->text('from_name')->default(get_bloginfo('name'))->required()
	->email('from_email')->default(get_option('admin_email'))->required()
	->email('reply_to')->default(get_option('admin_email'))
	->beforeSave(function($data) {
		// Custom validation
		if ($data['from_email'] === $data['reply_to']) {
			throw new Exception('From and Reply-To emails should be different.');
		}
		return $data;
	});

$form->render();</code></pre>
		</div>

		<h3><?php _e( 'Add Form to Existing Controller', 'campaignbridge' ); ?></h3>
		<div class="code-block">
			<pre><code>// In your Settings_Controller.php
public function handle_request() {
	// Existing form handling...

	// Add new form
	$form = \CampaignBridge\Admin\Core\Form::make('new_feature_settings')
		->text('feature_name')->required()
		->checkbox('enable_feature')->default(true);

	if ($form->submitted() && $form->valid()) {
		// Handle success
	}
}</code></pre>
		</div>
	</div>

	<div class="method-reference">
		<h2><?php _e( '📚 Method Reference', 'campaignbridge' ); ?></h2>

		<div class="method-categories">

			<div class="method-category">
				<h4><?php _e( 'Form Creation', 'campaignbridge' ); ?></h4>
				<ul>
					<li><code>Form::make('id')</code> - Create custom form</li>
					<li><code>Form::contact('id')</code> - Pre-built contact form</li>
					<li><code>Form::register('id')</code> - Pre-built registration form</li>
					<li><code>Form::settings('id')</code> - Pre-built settings form</li>
				</ul>
			</div>

			<div class="method-category">
				<h4><?php _e( 'Field Types', 'campaignbridge' ); ?></h4>
				<ul>
					<li><code>->text('name', 'Label')</code> - Text input</li>
					<li><code>->email('name', 'Label')</code> - Email input</li>
					<li><code>->password('name', 'Label')</code> - Password input</li>
					<li><code>->number('name', 'Label')</code> - Number input</li>
					<li><code>->textarea('name', 'Label')</code> - Textarea</li>
					<li><code>->select('name', 'Label')</code> - Select dropdown</li>
					<li><code>->radio('name', 'Label')</code> - Radio buttons</li>
					<li><code>->checkbox('name', 'Label')</code> - Checkbox</li>
					<li><code>->file('name', 'Label')</code> - File upload</li>
					<li><code>->wysiwyg('name', 'Label')</code> - Rich editor</li>
				</ul>
			</div>

			<div class="method-category">
				<h4><?php _e( 'Field Configuration', 'campaignbridge' ); ?></h4>
				<ul>
					<li><code>->required()</code> - Make field required</li>
					<li><code>->label('Text')</code> - Set field label</li>
					<li><code>->default('value')</code> - Set default value</li>
					<li><code>->placeholder('text')</code> - Set placeholder</li>
					<li><code>->description('text')</code> - Set help text</li>
					<li><code>->options([...])</code> - Set select/radio options</li>
					<li><code>->min(0)->max(100)</code> - Set number ranges</li>
					<li><code>->minLength(5)</code> - Set minimum length</li>
					<li><code>->accept('image/*')</code> - File types for uploads</li>
				</ul>
			</div>

			<div class="method-category">
				<h4><?php _e( 'Form Configuration', 'campaignbridge' ); ?></h4>
				<ul>
					<li><code>->method('POST')</code> - Set HTTP method</li>
					<li><code>->action('/url')</code> - Set form action</li>
					<li><code>->table()</code> - Table layout</li>
					<li><code>->div()</code> - Div layout</li>
					<li><code>->options('prefix')</code> - Save to options</li>
					<li><code>->meta($post_id)</code> - Save to post meta</li>
					<li><code>->success('Message')</code> - Success message</li>
					<li><code>->submit('Text')</code> - Submit button</li>
				</ul>
			</div>

			<div class="method-category">
				<h4><?php _e( 'Lifecycle Hooks', 'campaignbridge' ); ?></h4>
				<ul>
					<li><code>->beforeValidate($fn)</code> - Pre-validation</li>
					<li><code>->afterValidate($fn)</code> - Post-validation</li>
					<li><code>->beforeSave($fn)</code> - Pre-save processing</li>
					<li><code>->afterSave($fn)</code> - Post-save actions</li>
					<li><code>->onSuccess($fn)</code> - Success callback</li>
					<li><code>->onError($fn)</code> - Error callback</li>
				</ul>
			</div>

			<div class="method-category">
				<h4><?php _e( 'Form State', 'campaignbridge' ); ?></h4>
				<ul>
					<li><code>$form->submitted()</code> - Check if submitted</li>
					<li><code>$form->valid()</code> - Check if valid</li>
					<li><code>$form->errors()</code> - Get validation errors</li>
					<li><code>$form->data('field')</code> - Get form data</li>
					<li><code>$form->render()</code> - Output the form</li>
				</ul>
			</div>

		</div>
	</div>

	<div class="next-steps">
		<h2><?php _e( '🚀 Next Steps', 'campaignbridge' ); ?></h2>
		<ol>
			<li><?php _e( 'Copy one of the examples above into a screen file', 'campaignbridge' ); ?></li>
			<li><?php _e( 'Include the Form system: <code>require_once __DIR__ . \'/../Core/Form.php\';</code>', 'campaignbridge' ); ?></li>
			<li><?php _e( 'Customize the fields and options for your needs', 'campaignbridge' ); ?></li>
			<li><?php _e( 'Add your business logic in the lifecycle hooks', 'campaignbridge' ); ?></li>
			<li><?php _e( 'Test the form - everything works automatically!', 'campaignbridge' ); ?></li>
		</ol>

		<div class="pro-tips">
			<h3><?php _e( '💡 Pro Tips', 'campaignbridge' ); ?></h3>
			<ul>
				<li><?php _e( 'Use meaningful field names - they become database keys', 'campaignbridge' ); ?></li>
				<li><?php _e( 'Add validation early - use the validation helpers', 'campaignbridge' ); ?></li>
				<li><?php _e( 'Leverage hooks for complex logic - keep field definitions clean', 'campaignbridge' ); ?></li>
				<li><?php _e( 'Test with different user roles - security is automatic', 'campaignbridge' ); ?></li>
				<li><?php _e( 'Use pre-built forms for common patterns - saves time', 'campaignbridge' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<style>
.quick-start-intro {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 30px;
	border-radius: 12px;
	margin: 20px 0;
	text-align: center;
}

.examples-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
	gap: 20px;
	margin: 30px 0;
}

.example-card {
	background: white;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.example-card h3 {
	margin-top: 0;
	color: #007cba;
	border-bottom: 2px solid #007cba;
	padding-bottom: 8px;
}

.code-block {
	background: #f8f9fa;
	border: 1px solid #dee2e6;
	border-radius: 4px;
	margin: 15px 0;
	overflow: hidden;
}

.code-block pre {
	margin: 0;
	padding: 15px;
	background: #2d3748;
	color: #e2e8f0;
	overflow-x: auto;
	font-size: 13px;
	line-height: 1.4;
}

.code-block code {
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.example-card p {
	color: #666;
	font-size: 14px;
	line-height: 1.4;
	margin: 15px 0 0 0;
}

.integration-guide {
	background: #f8f9fa;
	padding: 30px;
	border-radius: 8px;
	margin: 30px 0;
	border: 1px solid #dee2e6;
}

.integration-guide h2,
.integration-guide h3 {
	color: #007cba;
}

.integration-guide h3 {
	margin-top: 30px;
	border-bottom: 1px solid #dee2e6;
	padding-bottom: 8px;
}

.method-reference {
	margin: 40px 0;
}

.method-categories {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.method-category {
	background: white;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
}

.method-category h4 {
	margin-top: 0;
	color: #007cba;
	border-bottom: 1px solid #007cba;
	padding-bottom: 8px;
}

.method-category ul {
	margin: 15px 0 0 0;
	padding-left: 20px;
}

.method-category li {
	margin-bottom: 5px;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 13px;
	color: #333;
}

.method-category code {
	background: #f1f3f4;
	padding: 2px 4px;
	border-radius: 3px;
	font-size: 12px;
}

.real-examples {
	background: #e3f2fd;
	padding: 30px;
	border-radius: 8px;
	margin: 30px 0;
	border: 1px solid #bbdefb;
}

.real-examples h2,
.real-examples h3 {
	color: #1565c0;
}

.next-steps {
	background: white;
	padding: 30px;
	border-radius: 8px;
	border: 1px solid #ddd;
	margin: 30px 0;
}

.next-steps ol {
	padding-left: 20px;
}

.next-steps li {
	margin-bottom: 10px;
	line-height: 1.5;
}

.next-steps code {
	background: #f1f3f4;
	padding: 2px 4px;
	border-radius: 3px;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 12px;
}

.pro-tips {
	background: #fff3cd;
	padding: 20px;
	border-radius: 8px;
	margin-top: 30px;
	border: 1px solid #ffeaa7;
}

.pro-tips h3 {
	color: #856404;
	margin-top: 0;
}

.pro-tips ul {
	margin: 15px 0 0 0;
	padding-left: 20px;
	color: #856404;
}

.pro-tips li {
	margin-bottom: 8px;
	line-height: 1.4;
}

@media (max-width: 768px) {
	.examples-grid {
		grid-template-columns: 1fr;
	}

	.method-categories {
		grid-template-columns: 1fr;
	}
}
</style>
