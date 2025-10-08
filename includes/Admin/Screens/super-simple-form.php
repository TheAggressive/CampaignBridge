<?php
/**
 * Super Simple Form Examples - The Most Developer Friendly API
 *
 * Compare these examples to see how much more intuitive the fluent API is!
 */

// Include the new super simple Form API
require_once __DIR__ . '/../Core/Form.php';

?>

<div class="wrap">
	<h1><?php _e( 'Super Simple Form API - The Most Developer Friendly!', 'campaignbridge' ); ?></h1>

	<div class="comparison-section">
		<h2><?php _e( '🚀 Before vs After Comparison', 'campaignbridge' ); ?></h2>

		<div class="comparison">
			<div class="before">
				<h3><?php _e( '❌ OLD WAY - Complex Configuration Arrays', 'campaignbridge' ); ?></h3>
				<pre><code>$form = new Form_Builder('contact', [
	'method' => 'POST',
	'layout' => 'table',
	'data_source' => 'options',
	'prefix' => 'contact_',
	'success_message' => 'Message sent!',
	'submit_button' => [
		'text' => 'Send Message',
		'type' => 'primary'
	],
	'fields' => [
		'name' => [
			'type' => 'text',
			'label' => 'Your Name',
			'required' => true,
			'placeholder' => 'Enter your name',
			'class' => 'regular-text',
			'autocomplete' => 'name',
			'validation' => [
				'min_length' => 2,
				'max_length' => 100
			]
		],
		'email' => [
			'type' => 'email',
			'label' => 'Email Address',
			'required' => true,
			'autocomplete' => 'email'
		],
		'message' => [
			'type' => 'textarea',
			'label' => 'Message',
			'required' => true,
			'rows' => 5,
			'placeholder' => 'Your message here...'
		]
	],
	'hooks' => [
		'after_save' => function($data) {
			wp_mail('admin@example.com', 'Contact Form', $data['message']);
		}
	]
]);
$form->render();</code></pre>
				<p><strong><?php _e( 'Problems:', 'campaignbridge' ); ?></strong></p>
				<ul>
					<li><?php _e( 'Deep nested arrays', 'campaignbridge' ); ?></li>
					<li><?php _e( 'Hard to read and modify', 'campaignbridge' ); ?></li>
					<li><?php _e( 'Easy to make syntax errors', 'campaignbridge' ); ?></li>
					<li><?php _e( 'No autocomplete or type hints', 'campaignbridge' ); ?></li>
					<li><?php _e( 'Repetitive configuration', 'campaignbridge' ); ?></li>
				</ul>
			</div>

			<div class="after">
				<h3><?php _e( '✅ NEW WAY - Fluent, Readable, Intuitive', 'campaignbridge' ); ?></h3>
				<pre><code>// Contact form in 4 lines!
Form::make('contact')
	->text('name', 'Your Name')->required()->placeholder('Enter your name')
	->email('email', 'Email Address')->required()
	->textarea('message', 'Message')->required()->rows(5)
	->afterSave(function($data) {
		wp_mail('admin@example.com', 'Contact Form', $data['message']);
	})
	->render();</code></pre>
				<p><strong><?php _e( 'Benefits:', 'campaignbridge' ); ?></strong></p>
				<ul>
					<li><?php _e( 'Reads like plain English', 'campaignbridge' ); ?></li>
					<li><?php _e( 'Method chaining for flow', 'campaignbridge' ); ?></li>
					<li><?php _e( 'Autocomplete and type hints', 'campaignbridge' ); ?></li>
					<li><?php _e( 'Impossible to make syntax errors', 'campaignbridge' ); ?></li>
					<li><?php _e( '90% less code, 100% more readable', 'campaignbridge' ); ?></li>
				</ul>
			</div>
		</div>
	</div>

	<div class="examples-section">
		<h2><?php _e( '🎯 Real Examples - How Easy It Is', 'campaignbridge' ); ?></h2>

		<h3><?php _e( '1. Contact Form (3 lines)', 'campaignbridge' ); ?></h3>
		<div class="example-code">
			<pre><code>Form::make('contact')
	->text('name')->required()->label('Name')
	->email('email')->required()->label('Email')
	->textarea('message')->required()->label('Message')
	->submit('Send Message')
	->render();</code></pre>
		</div>

		<h3><?php _e( '2. User Registration (5 lines)', 'campaignbridge' ); ?></h3>
		<div class="example-code">
			<pre><code>Form::make('register')
	->text('username')->required()->label('Username')
	->email('email')->required()->label('Email')
	->password('password')->required()->minLength(8)->label('Password')
	->password('confirm_password')->required()->label('Confirm Password')
	->beforeValidate(function($data) {
		if ($data['password'] !== $data['confirm_password']) {
			throw new Exception('Passwords do not match');
		}
	})
	->render();</code></pre>
		</div>

		<h3><?php _e( '3. Settings Form (4 lines)', 'campaignbridge' ); ?></h3>
		<div class="example-code">
			<pre><code>Form::settings('app_settings')
	->text('site_name')->label('Site Name')->default(get_bloginfo('name'))
	->email('admin_email')->label('Admin Email')->default(get_option('admin_email'))
	->checkbox('debug_mode')->label('Enable Debug Mode')
	->number('cache_timeout')->label('Cache Timeout')->default(3600)->range(60, 86400)
	->render();</code></pre>
		</div>

		<h3><?php _e( '4. Pre-built Forms (1 line!)', 'campaignbridge' ); ?></h3>
		<div class="example-code">
			<pre><code>// Complete contact form
Form::contact()->render();

// Complete registration form
Form::register()->render();

// Settings form template
Form::settings()->render();</code></pre>
		</div>

		<h3><?php _e( '5. Advanced Features (Still Simple)', 'campaignbridge' ); ?></h3>
		<div class="example-code">
			<pre><code>Form::make('advanced')
	->file('avatar')->accept('image/*')->label('Profile Picture')
	->select('country')->options(['us' => 'USA', 'ca' => 'Canada'])->label('Country')
	->wysiwyg('bio')->label('Biography')
	->checkbox('newsletter')->label('Subscribe to newsletter')
	->radio('plan')->options(['free' => 'Free', 'premium' => 'Premium'])->label('Plan')
	->multipart() // For file uploads
	->beforeSave(function($data) {
		// Encrypt sensitive data
		$data['encrypted_email'] = encrypt($data['email']);
	})
	->afterSave(function($data, $result) {
		if ($result) {
			// Send welcome email
			wp_mail($data['email'], 'Welcome!', 'Thanks for joining!');
		}
	})
	->render();</code></pre>
		</div>

		<h3><?php _e( '6. Conditional Logic', 'campaignbridge' ); ?></h3>
		<div class="example-code">
			<pre><code>Form::make('conditional')
	->select('user_type')->options([
		'basic' => 'Basic User',
		'premium' => 'Premium User'
	])->label('User Type')
	->text('company_name')->label('Company Name')
		->attributes(['data-conditional-field' => 'user_type', 'data-conditional-value' => 'premium'])
	->number('employee_count')->label('Employee Count')->min(1)->max(10000)
		->attributes(['data-conditional-field' => 'user_type', 'data-conditional-value' => 'premium'])
	->render();</code></pre>
		<p><?php _e( 'Fields automatically show/hide based on user_type selection!', 'campaignbridge' ); ?></p>
		</div>
	</div>

	<div class="why-section">
		<h2><?php _e( '🤔 Why This API is Most Developer Friendly', 'campaignbridge' ); ?></h2>

		<div class="reasons">
			<div class="reason">
				<h4><?php _e( '📖 Reads Like English', 'campaignbridge' ); ?></h4>
				<p><?php _e( 'Code reads naturally: "Make a form, add a text field, make it required, set placeholder..."', 'campaignbridge' ); ?></p>
			</div>

			<div class="reason">
				<h4><?php _e( '🔗 Method Chaining', 'campaignbridge' ); ?></h4>
				<p><?php _e( 'Fluent interface allows chaining methods without repeating variable names.', 'campaignbridge' ); ?></p>
			</div>

			<div class="reason">
				<h4><?php _e( '🎯 Sensible Defaults', 'campaignbridge' ); ?></h4>
				<p><?php _e( 'Works out of the box with smart defaults. Only configure what you need.', 'campaignbridge' ); ?></p>
			</div>

			<div class="reason">
				<h4><?php _e( '🛠️ Full IDE Support', 'campaignbridge' ); ?></h4>
				<p><?php _e( 'Autocomplete, type hints, and error detection in modern IDEs.', 'campaignbridge' ); ?></p>
			</div>

			<div class="reason">
				<h4><?php _e( '📦 Pre-built Templates', 'campaignbridge' ); ?></h4>
				<p><?php _e( 'Form::contact(), Form::register(), Form::settings() - instant forms!', 'campaignbridge' ); ?></p>
			</div>

			<div class="reason">
				<h4><?php _e( '⚡ Impossibly Simple', 'campaignbridge' ); ?></h4>
				<p><?php _e( 'From 50+ lines of config arrays to 3-5 lines of readable code.', 'campaignbridge' ); ?></p>
			</div>
		</div>
	</div>

	<div class="migration-section">
		<h2><?php _e( '🔄 Easy Migration from Old API', 'campaignbridge' ); ?></h2>

		<div class="migration">
			<div class="old-code">
				<h3><?php _e( 'Old Complex Code', 'campaignbridge' ); ?></h3>
				<pre><code>$form = new Form_Builder('settings', [
	'fields' => [
		'api_key' => [
			'type' => 'password',
			'label' => 'API Key',
			'required' => true,
			'validation' => ['min_length' => 10]
		]
	]
]);
$form->render();</code></pre>
			</div>

			<div class="new-code">
				<h3><?php _e( 'New Simple Code', 'campaignbridge' ); ?></h3>
				<pre><code>Form::make('settings')
	->password('api_key', 'API Key')->required()->minLength(10)
	->render();</code></pre>
				<p><?php _e( 'Same result, 80% less code, 100% more readable!', 'campaignbridge' ); ?></p>
			</div>
		</div>

		<div class="modern-approach">
			<h3><?php _e( '🎯 Modern Fluent API', 'campaignbridge' ); ?></h3>
			<p><?php _e( 'The fluent API makes form creation intuitive and readable.', 'campaignbridge' ); ?></p>
			<pre><code>// Clean and readable
$form = Form::contact('contact_form')
	->afterSave(function($data) {
		// Handle submission
	});

$form->render();</code></pre>
		</div>
	</div>
</div>

<style>
.comparison-section {
	margin: 30px 0;
}

.comparison {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin-top: 20px;
}

.comparison .before,
.comparison .after {
	padding: 20px;
	border-radius: 8px;
}

.comparison .before {
	background: #ffe6e6;
	border: 2px solid #ffcccc;
}

.comparison .after {
	background: #e6ffe6;
	border: 2px solid #ccffcc;
}

.comparison h3 {
	margin-top: 0;
	color: #333;
}

.comparison ul {
	margin: 15px 0;
	padding-left: 20px;
}

.comparison li {
	margin-bottom: 5px;
}

.example-code {
	background: #f8f9fa;
	border: 1px solid #dee2e6;
	border-radius: 4px;
	margin: 20px 0;
	overflow: hidden;
}

.example-code h3 {
	background: #e9ecef;
	margin: 0;
	padding: 15px 20px;
	border-bottom: 1px solid #dee2e6;
	font-size: 16px;
	font-weight: 600;
}

.example-code pre {
	margin: 0;
	padding: 20px;
	background: #2d3748;
	color: #e2e8f0;
	overflow-x: auto;
	font-size: 13px;
	line-height: 1.4;
}

.example-code code {
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.example-code p {
	margin: 15px 20px;
	color: #666;
	font-style: italic;
}

.why-section {
	margin: 40px 0;
}

.reasons {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.reason {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 8px;
	border-left: 4px solid #007cba;
}

.reason h4 {
	margin: 0 0 10px 0;
	color: #007cba;
}

.reason p {
	margin: 0;
	color: #666;
}

.migration-section {
	margin: 40px 0;
}

.migration {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin: 20px 0;
}

.migration .old-code,
.migration .new-code {
	padding: 20px;
	border-radius: 8px;
}

.migration .old-code {
	background: #fff3cd;
	border: 2px solid #ffeaa7;
}

.migration .new-code {
	background: #d1ecf1;
	border: 2px solid #bee5eb;
}

.modern-approach {
	background: #e7f3ff;
	padding: 20px;
	border-radius: 8px;
	margin-top: 30px;
	border: 1px solid #b8daff;
}

.modern-approach h3 {
	margin-top: 0;
	color: #004085;
}

.modern-approach p {
	margin: 10px 0;
	color: #004085;
}

.modern-approach pre {
	background: #f8f9fa;
	padding: 15px;
	border-radius: 4px;
	margin: 15px 0 0 0;
}
</style>
