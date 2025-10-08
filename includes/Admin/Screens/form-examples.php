<?php
/**
 * Modern Form System - Fluent API Examples
 *
 * Clean examples showing the modern fluent API approach
 */

// Include the modern form system
require_once __DIR__ . '/../Core/Form.php';
?>

<div class="wrap">
	<h1><?php _e( 'Modern Form System - Fluent API Examples', 'campaignbridge' ); ?></h1>

	<div class="modern-examples">
		<h2><?php _e( '🚀 Pre-built Forms', 'campaignbridge' ); ?></h2>

		<div class="example-section">
			<h3>Contact Form (Ultra Simple)</h3>
			<pre><code>$contact_form = Form::contact('contact_demo')
	->afterSave(function($data) {
		error_log('Contact: ' . $data['name']);
	});</code></pre>
		</div>

		<div class="example-section">
			<h3>Settings Form</h3>
			<pre><code>$settings_form = Form::settings('my_settings')
	->text('site_name')->required()->end()
	->email('admin_email')->required()->end()
	->submit('Save Settings');</code></pre>
		</div>

		<div class="example-section">
			<h3>User Registration</h3>
			<pre><code>$register_form = Form::register('user_reg')
	->beforeValidate(function($data) {
		if ($data['password'] !== $data['confirm']) {
			throw new Exception('Passwords don\'t match');
		}
		return $data;
	});</code></pre>
		</div>

		<h2><?php _e( '🔧 Field Types & Configurations', 'campaignbridge' ); ?></h2>

		<div class="example-section">
			<h3>All Input Types</h3>
			<pre><code>$form = Form::make('all_inputs')
	// Text inputs
	->text('name')->required()->placeholder('Your name')->end()
	->email('email')->required()->autocomplete('email')->end()
	->url('website')->placeholder('https://...')->end()
	->password('password')->required()->end()
	->number('age')->min(1)->max(120)->end()
	->tel('phone')->placeholder('+1 (555) 123-4567')->end()
	->search('query')->placeholder('Search...')->end()

	// Advanced inputs
	->switch('enable_feature')->default(true)->end()
	->range('volume')->min(0)->max(100)->default(75)->end()
	->color('theme_color')->default('#007cba')->end()
	->date('birthdate')->required()->end()
	->time('meeting_time')->default('09:00')->end()

	// Choice fields
	->select('country', 'Country')
		->options(['us' => 'USA', 'ca' => 'Canada', 'uk' => 'UK'])
		->default('us')
		->end()

	->radio('plan', 'Plan')
		->options(['basic' => 'Basic', 'pro' => 'Pro', 'enterprise' => 'Enterprise'])
		->default('basic')
		->end()

	->checkbox('interests', 'Interests')
		->options(['tech' => 'Technology', 'design' => 'Design', 'business' => 'Business'])
		->default(['tech', 'design'])
		->end()

	// Content fields
	->textarea('bio')->rows(4)->maxLength(500)->end()
	->wysiwyg('description')->end()

	// Files
	->file('avatar')->accept('image/*')->end()
	->file('documents')->accept('.pdf,.doc,.docx')->end()

	->submit('Submit All Fields');</code></pre>
		</div>

		<h2><?php _e( '🎨 Layout Options', 'campaignbridge' ); ?></h2>

		<div class="example-section">
			<h3>Table Layout (WordPress Default)</h3>
			<pre><code>$table_form = Form::make('table_form')
	->table() // Explicit table layout
	->text('name')->required()->end()
	->email('email')->required()->end()
	->submit('Save');</code></pre>
		</div>

		<div class="example-section">
			<h3>Div Layout (Modern)</h3>
			<pre><code>$div_form = Form::make('div_form')
	->div() // Modern div layout
	->text('name')->required()->end()
	->email('email')->required()->end()
	->submit('Send');</code></pre>
		</div>

		<div class="example-section">
			<h3>Custom Layout</h3>
			<pre><code>$custom_form = Form::make('custom_form')
	->custom(function() { echo '<div class="my-layout">'; })
	->text('field1')->end()
	->custom(function() { echo '</div><div class="my-layout">'; })
	->text('field2')->end()
	->custom(function() { echo '</div>'; })
	->submit('Submit');</code></pre>
		</div>

		<h2><?php _e( '⚙️ Data Storage', 'campaignbridge' ); ?></h2>

		<div class="example-section">
			<h3>WordPress Options</h3>
			<pre><code>$form = Form::settings('my_settings')
	->options('my_prefix_') // Saves to wp_options with prefix
	->text('api_key')->end()
	->submit('Save Options');</code></pre>
		</div>

		<div class="example-section">
			<h3>Post Meta</h3>
			<pre><code>$form = Form::make('post_form')
	->meta($post_id) // Saves to post meta
	->text('custom_field')->end()
	->submit('Save Meta');</code></pre>
		</div>

		<div class="example-section">
			<h3>Custom Storage</h3>
			<pre><code>$form = Form::make('custom_form')
	->saveMethod('custom')
	->saveData(function($data) {
		// Custom save logic
		return my_custom_save_function($data);
	})
	->text('field')->end()
	->submit('Custom Save');</code></pre>
		</div>

		<h2><?php _e( '🔗 Lifecycle Hooks', 'campaignbridge' ); ?></h2>

		<div class="example-section">
			<h3>All Hook Types</h3>
			<pre><code>$form = Form::make('hooked_form')
	->beforeValidate(function($data) {
		// Pre-validation logic
		return $data;
	})
	->afterValidate(function($data, $errors) {
		// Post-validation logic
		if (!empty($errors)) {
			error_log('Validation failed');
		}
	})
	->beforeSave(function($data) {
		// Pre-save processing
		$data['timestamp'] = time();
		return $data;
	})
	->afterSave(function($data, $success) {
		// Post-save actions
		if ($success) {
			do_action('my_form_saved', $data);
		}
	})
	->onSuccess(function($data) {
		// Success callback
		wp_redirect(add_query_arg('saved', '1'));
		exit;
	})
	->onError(function($data) {
		// Error callback
		error_log('Form save failed');
	})
	->loadData(function($fields) {
		// Custom data loading
		return get_my_custom_data();
	})
	->text('field')->end()
	->submit('Save with Hooks');</code></pre>
		</div>

		<h2><?php _e( '🛡️ Security Features', 'campaignbridge' ); ?></h2>

		<div class="example-section">
			<h3>Built-in Security</h3>
			<p>✅ Automatic CSRF protection<br>
				✅ Input sanitization<br>
				✅ User capability checks<br>
				✅ File upload validation<br>
				✅ XSS prevention</p>
		</div>

		<div class="example-section">
			<h3>Advanced Security</h3>
			<pre><code>$secure_form = Form::make('secure_form')
	->requireCapability('manage_options') // User permission check
	->rateLimit(60, 60) // 60 requests per minute
	->file('upload')
		->accept('image/*')
		->maxSize(2 * 1024 * 1024) // 2MB limit
		->end()
	->submit('Secure Upload');</code></pre>
		</div>

		<h2><?php _e( '🎯 Getting Started', 'campaignbridge' ); ?></h2>

		<div class="getting-started-steps">
			<div class="step">
				<h3>1. Include the Form System</h3>
				<pre><code>require_once __DIR__ . '/Core/Form.php';</code></pre>
			</div>

			<div class="step">
				<h3>2. Create Your Form</h3>
				<pre><code>$form = \CampaignBridge\Admin\Core\Form::settings('my_form')
	->text('name')->required()->end()
	->email('email')->required()->end()
	->submit('Save');</code></pre>
			</div>

			<div class="step">
				<h3>3. Handle & Display</h3>
				<pre><code>if ($form->submitted() && $form->valid()) {
	$screen->add_message('Saved successfully!');
} elseif ($form->submitted()) {
	foreach ($form->errors() as $error) {
		$screen->add_error($error);
	}
}

$form->render();</code></pre>
			</div>
		</div>
	</div>
</div>

<style>
.modern-examples {
	max-width: none;
}

.example-section {
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	margin: 20px 0;
	padding: 20px;
}

.example-section h3 {
	margin-top: 0;
	color: #007cba;
	font-size: 16px;
}

.example-section pre {
	background: #2d3748;
	color: #e2e8f0;
	padding: 15px;
	border-radius: 6px;
	font-size: 13px;
	line-height: 1.4;
	overflow-x: auto;
	margin: 10px 0 0 0;
}

.getting-started-steps {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.getting-started-steps .step {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 6px;
	border: 1px solid #e1e5e9;
}

.getting-started-steps .step h3 {
	margin-top: 0;
	color: #007cba;
	font-size: 16px;
}
	</style>
