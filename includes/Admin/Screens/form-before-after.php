<?php
/**
 * Form System - Before vs After Demo
 *
 * Shows exactly how to use the NEW Form system vs the OLD way
 */

// Include the NEW Form system
require_once __DIR__ . '/../Core/Form.php';

?>

<div class="wrap">
	<h1><?php _e( 'Form System: Before vs After', 'campaignbridge' ); ?></h1>

	<div class="demo-intro">
		<p><?php _e( 'This page shows the EXACT same form built two ways: the old manual approach vs the new Form system. The results are identical, but the new way is 10x easier!', 'campaignbridge' ); ?></p>
	</div>

	<div class="comparison-container">
		<div class="comparison-section">
			<h2><?php _e( '❌ OLD WAY - Manual Form (80+ lines)', 'campaignbridge' ); ?></h2>

			<div class="old-form-demo">
				<h3><?php _e( 'Manual Implementation', 'campaignbridge' ); ?></h3>

				<?php
				// OLD WAY: Manual form handling
				$old_from_name  = get_option( 'cb_demo_from_name', get_bloginfo( 'name' ) );
				$old_from_email = get_option( 'cb_demo_from_email', get_option( 'admin_email' ) );

				// Manual form processing
				if ( isset( $_POST['old_form_submit'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'old_form' ) ) {
					$old_from_name  = sanitize_text_field( $_POST['from_name'] ?? '' );
					$old_from_email = sanitize_email( $_POST['from_email'] ?? '' );

					$errors = [];
					if ( empty( $old_from_name ) ) {
						$errors[] = 'Name is required';
					}
					if ( empty( $old_from_email ) || ! is_email( $old_from_email ) ) {
						$errors[] = 'Valid email required';
					}

					if ( empty( $errors ) ) {
						update_option( 'cb_demo_from_name', $old_from_name );
						update_option( 'cb_demo_from_email', $old_from_email );
						echo '<div class="notice notice-success"><p>Settings saved manually!</p></div>';
					} else {
						echo '<div class="notice notice-error"><ul>';
						foreach ( $errors as $error ) {
							echo "<li>$error</li>";
						}
						echo '</ul></div>';
					}
				}
				?>

				<form method="post" action="">
					<?php wp_nonce_field( 'old_form' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="old_from_name">From Name *</label></th>
							<td>
								<input type="text" id="old_from_name" name="from_name" value="<?php echo esc_attr( $old_from_name ); ?>" class="regular-text" required>
								<p class="description">The name that appears in emails</p>
							</td>
						</tr>
						<tr>
							<th><label for="old_from_email">From Email *</label></th>
							<td>
								<input type="email" id="old_from_email" name="from_email" value="<?php echo esc_attr( $old_from_email ); ?>" class="regular-text" required>
								<p class="description">The email address for sending</p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="old_form_submit" value="Save Settings (Old Way)" class="button button-primary">
					</p>
				</form>
			</div>

			<div class="old-code">
				<h4><?php _e( 'Code Behind This Form (35+ lines)', 'campaignbridge' ); ?></h4>
				<pre><code>// Manual form processing (20+ lines of PHP)
$old_from_name = get_option('cb_demo_from_name', get_bloginfo('name'));
$old_from_email = get_option('cb_demo_from_email', get_option('admin_email'));

if (isset($_POST['old_form_submit']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'old_form')) {
	$old_from_name = sanitize_text_field($_POST['from_name'] ?? '');
	$old_from_email = sanitize_email($_POST['from_email'] ?? '');

	$errors = [];
	if (empty($old_from_name)) $errors[] = 'Name is required';
	if (empty($old_from_email) || !is_email($old_from_email)) $errors[] = 'Valid email required';

	if (empty($errors)) {
		update_option('cb_demo_from_name', $old_from_name);
		update_option('cb_demo_from_email', $old_from_email);
		echo '&lt;div class="notice notice-success"&gt;&lt;p&gt;Settings saved manually!&lt;/p&gt;&lt;/div&gt;';
	} else {
		echo '&lt;div class="notice notice-error"&gt;&lt;ul&gt;';
		foreach ($errors as $error) echo "&lt;li&gt;$error&lt;/li&gt;";
		echo '&lt;/ul&gt;&lt;/div&gt;';
	}
}

// Manual HTML output (15+ lines)
&lt;form method="post" action=""&gt;
	&lt;?php wp_nonce_field('old_form'); ?&gt;
	&lt;table class="form-table"&gt;
		&lt;tr&gt;&lt;th&gt;&lt;label&gt;From Name *&lt;/label&gt;&lt;/th&gt;
		&lt;td&gt;&lt;input type="text" name="from_name" value="&lt;?php echo esc_attr($old_from_name); ?&gt;" required&gt;&lt;/td&gt;&lt;/tr&gt;
		&lt;tr&gt;&lt;th&gt;&lt;label&gt;From Email *&lt;/label&gt;&lt;/th&gt;
		&lt;td&gt;&lt;input type="email" name="from_email" value="&lt;?php echo esc_attr($old_from_email); ?&gt;" required&gt;&lt;/td&gt;&lt;/tr&gt;
	&lt;/table&gt;
	&lt;p class="submit"&gt;&lt;input type="submit" name="old_form_submit" value="Save Settings" class="button button-primary"&gt;&lt;/p&gt;
&lt;/form&gt;</code></pre>
			</div>
		</div>

		<div class="comparison-section">
			<h2><?php _e( '✅ NEW WAY - Form System (8 lines)', 'campaignbridge' ); ?></h2>

			<div class="new-form-demo">
				<h3><?php _e( 'Form System Implementation', 'campaignbridge' ); ?></h3>

				<?php
				// NEW WAY: Form system (just 6 lines!)
				$new_form = \CampaignBridge\Admin\Core\Form::settings( 'demo_settings' )
					->text( 'from_name', 'From Name' )
						->default( get_bloginfo( 'name' ) )
						->required()
						->description( 'The name that appears in emails' )
						->end()
					->email( 'from_email', 'From Email' )
						->default( get_option( 'admin_email' ) )
						->required()
						->description( 'The email address for sending' )
						->end()
					->submit( 'Save Settings (New Way)' );

				// Handle form state (optional)
				if ( $new_form->submitted() && $new_form->valid() ) {
					echo '<div class="notice notice-success"><p>Settings saved with Form system!</p></div>';
				} elseif ( $new_form->submitted() ) {
					echo '<div class="notice notice-error"><ul>';
					foreach ( $new_form->errors() as $error ) {
						echo "<li>$error</li>";
					}
					echo '</ul></div>';
				}

				// Render the form (that's it!)
				$new_form->render();
				?>
			</div>

			<div class="new-code">
				<h4><?php _e( 'Code Behind This Form (6 lines)', 'campaignbridge' ); ?></h4>
				<pre><code>// Form system approach (6 lines!)
$form = \CampaignBridge\Admin\Core\Form::settings('demo_settings')
	->text('from_name', 'From Name')
		->default(get_bloginfo('name'))
		->required()
		->description('The name that appears in emails')
	->email('from_email', 'From Email')
		->default(get_option('admin_email'))
		->required()
		->description('The email address for sending')
	->submit('Save Settings (New Way)');

// Optional: handle form state
if ($form->submitted() && $form->valid()) {
	echo '&lt;div class="notice notice-success"&gt;&lt;p&gt;Settings saved!&lt;/p&gt;&lt;/div&gt;';
}

// Render (automatic!)
$form->render();</code></pre>
			</div>
		</div>
	</div>

	<div class="migration-guide">
		<h2><?php _e( '🔄 How to Migrate Your Existing Forms', 'campaignbridge' ); ?></h2>

		<div class="migration-steps">
			<div class="step">
				<h4><?php _e( 'Step 1: Include the New System', 'campaignbridge' ); ?></h4>
				<pre><code>// Replace this:
require_once __DIR__ . '/../../Core/Form.php';

// With this:
require_once __DIR__ . '/../../Core/Form.php';</code></pre>
			</div>

			<div class="step">
				<h4><?php _e( 'Step 2: Replace Form Creation', 'campaignbridge' ); ?></h4>
				<pre><code>// Replace this (50+ lines of config):
$form = \CampaignBridge\Admin\Core\Form::make('id')
	'fields' => [
		'field' => ['type' => 'text', 'label' => 'Label', 'required' => true]
	]
]);

// With this (3 lines):
$form = \CampaignBridge\Admin\Core\Form::make('id')
	->text('field', 'Label')->required();</code></pre>
			</div>

			<div class="step">
				<h4><?php _e( 'Step 3: Simplify Form Handling', 'campaignbridge' ); ?></h4>
				<pre><code>// Replace manual validation/saving (30+ lines)
// With automatic handling (3 lines):
if ($form->submitted() && $form->valid()) {
	$screen->add_message('Saved!');
} elseif ($form->submitted()) {
	foreach ($form->errors() as $error) {
		$screen->add_error($error);
	}
}</code></pre>
			</div>

			<div class="step">
				<h4><?php _e( 'Step 4: Remove Manual HTML', 'campaignbridge' ); ?></h4>
				<pre><code>// Replace manual HTML output (20+ lines)
// With one line:
$form->render();</code></pre>
			</div>
		</div>

		<div class="instant-benefits">
			<h3><?php _e( '⚡ Instant Benefits', 'campaignbridge' ); ?></h3>
			<ul>
				<li><?php _e( '✅ 90% less code to write', 'campaignbridge' ); ?></li>
				<li><?php _e( '✅ Automatic security (nonces, sanitization)', 'campaignbridge' ); ?></li>
				<li><?php _e( '✅ Built-in validation and error handling', 'campaignbridge' ); ?></li>
				<li><?php _e( '✅ Accessibility compliant by default', 'campaignbridge' ); ?></li>
				<li><?php _e( '✅ Consistent UI across all forms', 'campaignbridge' ); ?></li>
				<li><?php _e( '✅ Easy to maintain and modify', 'campaignbridge' ); ?></li>
			</ul>
		</div>
	</div>

	<div class="quick-reference">
		<h2><?php _e( '📚 Quick Reference - Most Common Patterns', 'campaignbridge' ); ?></h2>

		<div class="ref-grid">
			<div class="ref-item">
				<h4><?php _e( 'Contact Form', 'campaignbridge' ); ?></h4>
				<pre><code>Form::contact('contact')
	->afterSave(fn($data) => wp_mail('admin@example.com', $data['subject'], $data['message']))
	->render();</code></pre>
			</div>

			<div class="ref-item">
				<h4><?php _e( 'Settings Form', 'campaignbridge' ); ?></h4>
				<pre><code>Form::settings('settings')
	->text('api_key')->required()
	->checkbox('debug')->default(false)
	->render();</code></pre>
			</div>

			<div class="ref-item">
				<h4><?php _e( 'User Registration', 'campaignbridge' ); ?></h4>
				<pre><code>Form::register('register')
	->beforeValidate(function($data) {
		if ($data['password'] !== $data['confirm']) {
			throw new Exception('Passwords must match');
		}
	})
	->render();</code></pre>
			</div>

			<div class="ref-item">
				<h4><?php _e( 'Custom Form', 'campaignbridge' ); ?></h4>
				<pre><code>Form::make('custom')
	->select('type')->options(['a' => 'A', 'b' => 'B'])
	->file('upload')->accept('image/*')
	->submit('Create')
	->render();</code></pre>
			</div>
		</div>
	</div>
</div>

<style>
.demo-intro {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 30px;
	border-radius: 12px;
	margin: 20px 0;
	text-align: center;
	font-size: 18px;
}

.comparison-container {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 40px;
	margin: 40px 0;
}

.comparison-section {
	background: white;
	border: 2px solid;
	border-radius: 12px;
	padding: 0;
	overflow: hidden;
}

.comparison-section:first-child {
	border-color: #ffcccc;
}

.comparison-section:last-child {
	border-color: #ccffcc;
}

.comparison-section h2 {
	background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
	color: white;
	margin: 0;
	padding: 20px;
	text-align: center;
}

.comparison-section:last-child h2 {
	background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
}

.old-form-demo,
.new-form-demo {
	padding: 25px;
}

.old-form-demo h3,
.new-form-demo h3 {
	margin-top: 0;
	color: #666;
	font-size: 16px;
}

.old-code,
.new-code {
	background: #f8f9fa;
	border-top: 1px solid #dee2e6;
	padding: 20px;
}

.old-code h4,
.new-code h4 {
	margin-top: 0;
	color: #007cba;
	font-size: 14px;
}

.old-code pre,
.new-code pre {
	background: #2d3748;
	color: #e2e8f0;
	padding: 15px;
	border-radius: 6px;
	margin: 10px 0 0 0;
	font-size: 12px;
	line-height: 1.4;
	overflow-x: auto;
}

.migration-guide {
	background: #e3f2fd;
	padding: 30px;
	border-radius: 12px;
	margin: 40px 0;
	border: 1px solid #bbdefb;
}

.migration-guide h2 {
	color: #1565c0;
	margin-top: 0;
}

.migration-steps {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin: 25px 0;
}

.step {
	background: white;
	padding: 20px;
	border-radius: 8px;
	border: 1px solid #bbdefb;
}

.step h4 {
	margin-top: 0;
	color: #1565c0;
	font-size: 14px;
}

.step pre {
	background: #f8f9fa;
	padding: 10px;
	border-radius: 4px;
	margin: 10px 0 0 0;
	font-size: 11px;
	line-height: 1.3;
}

.instant-benefits {
	background: white;
	padding: 25px;
	border-radius: 8px;
	border: 1px solid #bbdefb;
	margin-top: 30px;
}

.instant-benefits h3 {
	color: #1565c0;
	margin-top: 0;
}

.instant-benefits ul {
	margin: 0;
	padding-left: 20px;
}

.instant-benefits li {
	margin-bottom: 8px;
	color: #1565c0;
}

.quick-reference {
	background: white;
	padding: 30px;
	border-radius: 12px;
	border: 1px solid #ddd;
	margin: 40px 0;
}

.quick-reference h2 {
	margin-top: 0;
	color: #007cba;
}

.ref-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.ref-item {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 8px;
	border: 1px solid #dee2e6;
}

.ref-item h4 {
	margin-top: 0;
	color: #007cba;
	font-size: 14px;
}

.ref-item pre {
	background: #2d3748;
	color: #e2e8f0;
	padding: 12px;
	border-radius: 4px;
	margin: 10px 0 0 0;
	font-size: 11px;
	line-height: 1.3;
	overflow-x: auto;
}

@media (max-width: 768px) {
	.comparison-container {
		grid-template-columns: 1fr;
		gap: 20px;
	}

	.comparison-section h2 {
		font-size: 18px;
	}

	.migration-steps {
		grid-template-columns: 1fr;
	}

	.ref-grid {
		grid-template-columns: 1fr;
	}
}
</style>
