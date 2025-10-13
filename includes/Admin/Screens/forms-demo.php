<?php
/**
 * Complete Form System Demo - All Forms, All Approaches
 *
 * This comprehensive demo shows EVERY way to use the form system:
 * - Old Form_Builder approach (for comparison)
 * - New Fluent API approach (recommended)
 * - All field types and advanced inputs
 * - Different form types (contact, settings, registration, custom)
 * - Multiple layouts and configurations
 */

// Include the form system
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
require_once __DIR__ . '/../Core/Forms/Form_Field_Textarea.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Select.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Checkbox.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Radio.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_File.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Wysiwyg.php';
require_once __DIR__ . '/../Core/Forms/Form_Field_Switch.php';

// Ensure WordPress environment is available
if ( ! function_exists( 'wp_parse_args' ) ) {
	// Provide fallback implementations for WordPress functions when not in WP context
	if ( ! function_exists( 'wp_parse_args' ) ) {
		function wp_parse_args( $args, $defaults = array() ) {
			if ( is_object( $args ) ) {
				$args = get_object_vars( $args );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			return array_merge( $defaults, $args );
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	if ( ! function_exists( 'wp_kses_post' ) ) {
		function wp_kses_post( $content ) {
			return $content; // Basic fallback - in real WP this would sanitize
		}
	}

	if ( ! function_exists( 'wp_editor' ) ) {
		function wp_editor( $content, $editor_id, $settings = array() ) {
			echo '<textarea id="' . esc_attr( $editor_id ) . '" name="' . esc_attr( $editor_id ) . '">' . esc_textarea( $content ) . '</textarea>';
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_textarea' ) ) {
		function esc_textarea( $text ) {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url, $protocols = null, $_context = 'display' ) {
			return filter_var( $url, FILTER_SANITIZE_URL );
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			return $default;
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( '_e' ) ) {
		function _e( $text, $domain = 'default' ) {
			echo $text;
		}
	}

	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( $action ) {
			return md5( $action . 'nonce_salt' );
		}
	}

	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $action ) {
			return $nonce === wp_create_nonce( $action );
		}
	}

	if ( ! function_exists( 'wp_nonce_field' ) ) {
		function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
			$nonce  = wp_create_nonce( $action );
			$output = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $nonce ) . '" />';
			if ( $referer ) {
				$output .= '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( $_SERVER['REQUEST_URI'] ?? '' ) . '" />';
			}
			if ( $echo ) {
				echo $output;
			}
			return $output;
		}
	}

	if ( ! function_exists( 'get_bloginfo' ) ) {
		function get_bloginfo( $show = '' ) {
			$defaults = array(
				'name'        => 'Demo Site',
				'description' => 'Demo site description',
				'url'         => 'https://example.com',
				'admin_email' => 'admin@example.com',
			);
			return $defaults[ $show ] ?? 'Demo Value';
		}
	}

	if ( ! function_exists( 'get_site_url' ) ) {
		function get_site_url() {
			return 'https://example.com';
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			$defaults = array(
				'admin_email' => 'admin@example.com',
			);
			return $defaults[ $option ] ?? $default;
		}
	}

	// Set up basic $_SERVER variables for testing
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		$_SERVER['REQUEST_URI'] = '/test';
	}

	if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$_SERVER['HTTP_USER_AGENT'] = 'Demo User Agent';
	}
}

// ============================================================================
// DEMO 1: FLUENT API - Contact Form (Super Simple)
// ============================================================================
$contact_form = \CampaignBridge\Admin\Core\Form_Factory::contact( 'contact_demo' )
	->after_save(
		function ( $data ) {
			// Simulate sending email
			error_log( 'Contact form submitted: ' . $data['name'] . ' <' . $data['email'] . '>' );
		}
	);

// ============================================================================
// DEMO 2: FLUENT API - Settings Form (All Field Types)
// ============================================================================
$settings_form = \CampaignBridge\Admin\Core\Form_Factory::settings_api( 'comprehensive_settings' )
	// Basic inputs
	->text( 'site_name', 'Site Name' )
		->default( get_bloginfo( 'name' ) )
		->required()
		->description( 'Your website name' )
		->end()

	->email( 'admin_email', 'Admin Email' )
		->default( get_option( 'admin_email' ) )
		->required()
		->description( 'Primary admin email' )
		->end()

	->url( 'site_url', 'Website URL' )
		->default( get_site_url() )
		->description( 'Your website URL' )
		->end()

	->password( 'api_key', 'API Key' )
		->description( 'External API key (optional)' )
		->end()

	->number( 'max_users', 'Max Users' )
		->default( 100 )
		->min( 1 )
		->max( 10000 )
		->description( 'Maximum allowed users' )
		->end()

	->tel( 'support_phone', 'Support Phone' )
		->placeholder( '+1 (555) 123-4567' )
		->description( 'Customer support phone' )
		->end()

	->search( 'search_term', 'Search' )
		->placeholder( 'Search documentation...' )
		->description( 'Quick search functionality' )
		->end()

	// Advanced inputs
	->switch( 'enable_feature', 'Enable New Feature' )
		->description( 'Turn on the new feature' )
		->default( false )
		->end()

	->toggle( 'debug_mode', 'Debug Mode' )
		->description( 'Enable detailed logging' )
		->default( true )
		->end()

	->range( 'volume', 'Volume Level' )
		->description( 'Set system volume (0-100)' )
		->min( 0 )->max( 100 )->default( 75 )
		->end()

	->slider( 'brightness', 'Screen Brightness' )
		->description( 'Display brightness percentage' )
		->min( 10 )->max( 100 )->step( 5 )->default( 80 )
		->end()

	->color( 'theme_color', 'Theme Color' )
		->description( 'Primary theme color' )
		->default( '#007cba' )

	->date( 'launch_date', 'Launch Date' )
		->description( 'Product launch date' )
		->required()

	->time( 'daily_backup', 'Daily Backup Time' )
		->description( 'When to run daily backups' )
		->default( '02:00' )

	->datetime( 'maintenance_window', 'Maintenance Window' )
		->description( 'Scheduled maintenance period' )

	// Choice fields
	->select( 'theme', 'Theme' )
		->options(
			array(
				'light'  => 'Light Theme',
				'dark'   => 'Dark Theme',
				'auto'   => 'Auto (System)',
				'custom' => 'Custom Theme',
			)
		)
		->default( 'light' )
		->description( 'Choose your theme' )
		->end()

	->radio( 'notification_freq', 'Notification Frequency' )
		->options(
			array(
				'realtime' => 'Real-time',
				'daily'    => 'Daily Digest',
				'weekly'   => 'Weekly Summary',
				'never'    => 'Never',
			)
		)
		->default( 'daily' )
		->description( 'How often to receive notifications' )
		->end()

	->checkbox( 'enable_cache', 'Enable Caching' )
		->description( 'Cache data for better performance' )
		->default( true )
		->end()

	->checkbox( 'enabled_features', 'Enabled Features' )
		->options(
			array(
				'analytics'     => 'Analytics Tracking',
				'logging'       => 'Error Logging',
				'backups'       => 'Auto Backups',
				'notifications' => 'Email Notifications',
				'reports'       => 'Usage Reports',
			)
		)
		->default( array( 'logging', 'notifications', 'backups' ) )
		->description( 'Select which features to enable' )
		->end()

	// Content fields
	->textarea( 'site_description', 'Site Description' )
		->rows( 4 )
		->placeholder( 'Describe your website...' )
		->max_length( 500 )
		->description( 'Brief site description (max 500 chars)' )
		->end()

	->wysiwyg( 'welcome_message', 'Welcome Message' )
		->description( 'HTML welcome message for new users' )
		->end()

	// File uploads
	->file( 'site_logo', 'Site Logo' )
		->accept( 'image/*' )
		->description( 'Upload your site logo (JPG, PNG, GIF - max 2MB)' )
		->end()

	->file( 'documents', 'Documents' )
		->accept( '.pdf,.doc,.docx,.txt' )
		->description( 'Upload supporting documents' )
		->end()

	->submit( 'Save All Settings' );

// ============================================================================
// DEMO 3: FLUENT API - User Registration Form
// ============================================================================
$register_form = \CampaignBridge\Admin\Core\Form_Factory::register( 'user_registration_demo' )
	->before_validate(
		function ( $data ) {
			if ( $data['password'] !== $data['password_confirm'] ) {
				throw new Exception( 'Passwords do not match!' );
			}
			return $data;
		}
	)
	->after_save(
		function ( $data, $result ) {
			if ( $result ) {
				error_log( 'New user registered: ' . $data['username'] );
			}
		}
	);

// ============================================================================
// DEMO 4: FLUENT API - Custom Form with Custom Layout
// ============================================================================
$custom_form = \CampaignBridge\Admin\Core\Form::make( 'custom_layout_demo' )
	->method( 'POST' )
	->render_custom(
		function () {
			// Custom layout renderer
			echo '<div class="custom-form-layout">';
			echo '<div class="form-section">';
		}
	)
	->text( 'first_name', 'First Name' )->required()->end()
	->text( 'last_name', 'Last Name' )->required()->end()
	->render_custom(
		function () {
			// Close first section, start second
			echo '</div><div class="form-section">';
		}
	)
	->email( 'email', 'Email' )->required()->end()
	->tel( 'phone', 'Phone' )->end()
	->render_custom(
		function () {
			// Close second section, start third
			echo '</div><div class="form-section">';
		}
	)
	->select( 'department', 'Department' )
		->options(
			array(
				'engineering' => 'Engineering',
				'marketing'   => 'Marketing',
				'sales'       => 'Sales',
				'support'     => 'Support',
			)
		)
		->end()
	->checkbox( 'subscribe_newsletter', 'Subscribe to Newsletter' )
		->default( true )
		->end()
	->render_custom(
		function () {
			// Close layout
			echo '</div></div>';
		}
	)
	->submit( 'Submit Custom Form' );


// ============================================================================
// DEMO 6: FLUENT API - Div Layout (Modern Alternative to Table)
// ============================================================================
$div_form = \CampaignBridge\Admin\Core\Form::make( 'div_layout_demo' )
	->div() // Use div layout instead of table.
	->text( 'name', 'Name' )->required()->placeholder( 'Your name' )->end()
	->email( 'email', 'Email' )->required()->placeholder( 'your@email.com' )->end()
	->textarea( 'message', 'Message' )->rows( 4 )->placeholder( 'Your message...' )->end()
	->checkbox( 'agree', 'I agree to terms' )->required()->end()
	->submit( 'Send Message' );
?>

<div class="wrap">
	<h1>Complete Form System Demo - All Forms, All Approaches</h1>

	<div class="demo-intro">
		<p>🎯 This comprehensive demo shows EVERY way to use the form system. From simple contact forms to complex settings panels with advanced input types</p>

		<div class="demo-stats">
			<div class="stat">
				<span class="number">5</span>
				<span class="label">Different Forms</span>
			</div>
			<div class="stat">
				<span class="number">20+</span>
				<span class="label">Field Types</span>
			</div>
			<div class="stat">
				<span class="number">3</span>
				<span class="label">API Approaches</span>
			</div>
			<div class="stat">
				<span class="number">∞</span>
				<span class="label">Possibilities</span>
			</div>
		</div>
	</div>

	<!-- ============================================================================
		DEMO 1: SIMPLE CONTACT FORM
		============================================================================ -->
	<div class="demo-section">
		<div class="demo-header">
			<h2>📧 Demo 1: Simple Contact Form</h2>
			<p>Ultra-simple contact form using the pre-built Form_Factory::contact() method</p>
		</div>
		<div class="demo-code">
			<pre><code>$contact_form = Form_Factory::contact('contact_demo')
	->after_save(function($data) {
		error_log('Contact: ' . $data['name']);
	});</code></pre>
		</div>
		<div class="demo-form">
			<?php
			if ( $contact_form->submitted() && $contact_form->valid() ) {
				echo '<div class="notice notice-success"><p>Contact form submitted successfully!</p></div>';
			}
			$contact_form->render();
			?>
		</div>
	</div>

	<!-- ============================================================================
		DEMO 2: COMPREHENSIVE SETTINGS FORM (ALL FIELD TYPES)
		============================================================================ -->
	<div class="demo-section">
		<div class="demo-header">
			<h2>⚙️ Demo 2: Comprehensive Settings Form</h2>
			<p>Shows ALL field types including advanced inputs like switches, sliders, color pickers, and date/time controls</p>
		</div>
		<div class="demo-code">
			<pre><code>$form = Form::make('comprehensive_settings')
	->save_to_settings_api('comprehensive_settings')
	->table()
	->success('Settings saved successfully!')
	->text('site_name')->required()
	->email('admin_email')->required()
	->switch('enable_feature')->default(false)
	->range('volume')->min(0)->max(100)->end()
	->color('theme_color')->default('#007cba')
	->date('launch_date')->required()
	->select('theme')->options([...])
	->checkbox('features')->options([...])
	->textarea('description')->rows(4)
	->file('logo')->accept('image/*')
	->submit('Save All Settings');</code></pre>
		</div>
		<div class="demo-form">
			<?php
			if ( $settings_form->submitted() && $settings_form->valid() ) {
				echo '<div class="notice notice-success"><p>✅ Comprehensive settings saved! Check all the different input types above.</p></div>';
			} elseif ( $settings_form->submitted() ) {
				echo '<div class="notice notice-error"><p>❌ Form validation failed. Please check your inputs.</p></div>';
			}
			$settings_form->render();
			?>
		</div>
	</div>

	<!-- ============================================================================
		DEMO 3: USER REGISTRATION FORM
		============================================================================ -->
	<div class="demo-section">
		<div class="demo-header">
			<h2>👤 Demo 3: User Registration Form</h2>
			<p>Pre-built registration form with password confirmation validation</p>
		</div>
		<div class="demo-code">
			<pre><code>$register_form = Form_Factory::register('user_registration_demo')
	->before_validate(function($data) {
		if ($data['password'] !== $data['password_confirm']) {
			throw new Exception('Passwords do not match!');
		}
		return $data;
	});</code></pre>
		</div>
		<div class="demo-form">
			<?php
			if ( $register_form->submitted() && $register_form->valid() ) {
				echo '<div class="notice notice-success"><p>🎉 User registration successful!</p></div>';
			}
			$register_form->render();
			?>
		</div>
	</div>

	<!-- ============================================================================
		DEMO 4: CUSTOM LAYOUT FORM
		============================================================================ -->
	<div class="demo-section">
		<div class="demo-header">
			<h2>🎨 Demo 4: Custom Layout Form</h2>
			<p>Custom layout using div-based sections instead of table layout</p>
		</div>
		<div class="demo-code">
			<pre><code>$custom_form = Form::make('custom_layout_demo')
	->render_custom(function() { echo '<div class="sections">'; })
	->text('first_name')->required()
	->render_custom(function() { echo '</div><div class="sections">'; })
	->email('email')->required()
	->submit('Submit');</code></pre>
		</div>
		<div class="demo-form">
			<?php
			if ( $custom_form->submitted() && $custom_form->valid() ) {
				echo '<div class="notice notice-success"><p>Custom form submitted with custom layout!</p></div>';
			}
			$custom_form->render();
			?>
		</div>
	</div>


	<!-- ============================================================================
		DEMO 6: DIV LAYOUT (MODERN ALTERNATIVE)
		============================================================================ -->
	<div class="demo-section">
		<div class="demo-header">
			<h2>📱 Demo 6: Modern Div Layout</h2>
			<p>Clean div-based layout instead of traditional table layout</p>
		</div>
		<div class="demo-code">
			<pre><code>$div_form = Form::make('div_layout_demo')
	->div() // Modern div layout
	->text('name')->required()->end()
	->email('email')->required()->end()
	->textarea('message')->rows(4)->end()
	->submit('Send Message');</code></pre>
		</div>
		<div class="demo-form">
			<?php
			if ( $div_form->submitted() && $div_form->valid() ) {
				echo '<div class="notice notice-success"><p>Message sent successfully!</p></div>';
			}
			$div_form->render();
			?>
		</div>
	</div>

	<!-- ============================================================================
		SUMMARY & COMPARISON
		============================================================================ -->
	<div class="comparison-section">
		<h2>📊 API Comparison</h2>

		<div class="comparison-table">
			<div class="comparison-row">
				<div class="approach">✅ Fluent API</div>
				<div class="code">$form = Form::make('id')->save_to_settings_api('id')->table()->text('name')->required()->end();</div>
				<div class="pros">Readable, chainable, modern</div>
				<div class="cons">Learning curve</div>
			</div>

			<div class="comparison-row">
				<div class="approach">🚀 Pre-built Forms</div>
				<div class="code">$form = Form_Factory::contact('id')->after_save($fn);</div>
				<div class="pros">Ultra-fast setup</div>
				<div class="cons">Less customization</div>
			</div>

			<div class="comparison-row">
				<div class="approach">🎨 Custom Layouts</div>
				<div class="code">$form->render_custom($fn)->text('name')->end();</div>
				<div class="pros">Full control</div>
				<div class="cons">More complex</div>
			</div>
		</div>
	</div>

	<div class="getting-started">
		<h2>🚀 Getting Started</h2>

		<div class="steps">
			<div class="step">
				<h3>1. Include the Form System</h3>
				<pre><code>require_once __DIR__ . '/../Core/Form.php';</code></pre>
			</div>

			<div class="step">
				<h3>2. Create Your Form</h3>
				<pre><code>$form = \CampaignBridge\Admin\Core\Form::make('my_form')
	->save_to_settings_api('my_form')
	->table()
	->success('Settings saved successfully!')
	->text('name')->required()->end()
	->email('email')->required()->end()
	->submit('Save');</code></pre>
			</div>

			<div class="step">
				<h3>3. Handle & Render</h3>
				<pre><code>if ($form->submitted() && $form->valid()) {
	$screen->add_message('Saved!');
}
$form->render();</code></pre>
			</div>
		</div>
	</div>
</div>

<style>
/* ============================================================================
	GENERAL STYLES
	============================================================================ */

.demo-intro {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 30px;
	margin: 20px 0;
	border-radius: 12px;
	text-align: center;
	box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.demo-intro p {
	font-size: 18px;
	margin-bottom: 20px;
}

.demo-stats {
	display: flex;
	justify-content: space-around;
	flex-wrap: wrap;
	gap: 20px;
	margin-top: 20px;
}

.demo-stats .stat {
	background: rgba(255,255,255,0.2);
	padding: 15px;
	border-radius: 8px;
	min-width: 100px;
}

.demo-stats .number {
	display: block;
	font-size: 28px;
	font-weight: bold;
}

.demo-stats .label {
	display: block;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 1px;
	opacity: 0.9;
}

/* ============================================================================
	DEMO SECTIONS
	============================================================================ */

.demo-section {
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	margin: 30px 0;
	box-shadow: 0 2px 10px rgba(0,0,0,0.05);
	overflow: hidden;
}

.demo-header {
	background: #f8f9fa;
	padding: 20px;
	border-bottom: 1px solid #e1e5e9;
}

.demo-header h2 {
	margin: 0 0 8px 0;
	font-size: 20px;
	font-weight: 600;
}

.demo-header p {
	margin: 0;
	color: #6c757d;
	font-size: 14px;
}

.demo-code {
	background: #2d3748;
	color: #e2e8f0;
	padding: 15px;
	margin: 0;
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 13px;
	line-height: 1.4;
	border-bottom: 1px solid #404854;
}

.demo-code pre {
	margin: 0;
	white-space: pre-wrap;
	word-break: break-all;
}

.demo-code code {
	background: none;
	padding: 0;
	border-radius: 0;
	font-size: inherit;
}

.demo-form {
	padding: 20px;
	background: #fafbfc;
}

/* ============================================================================
	COMPARISON SECTION
	============================================================================ */

.comparison-section {
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	margin: 30px 0;
	padding: 20px;
}

.comparison-section h2 {
	margin-top: 0;
	font-size: 24px;
	text-align: center;
}

.comparison-table {
	margin-top: 20px;
}

.comparison-row {
	display: grid;
	grid-template-columns: 200px 1fr 150px 150px;
	gap: 15px;
	padding: 15px;
	border-bottom: 1px solid #e1e5e9;
	align-items: center;
}

.comparison-row:last-child {
	border-bottom: none;
}

.approach {
	font-weight: bold;
	font-size: 14px;
}

.code {
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
	font-size: 12px;
	background: #f8f9fa;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #e1e5e9;
}

.pros {
	color: #28a745;
	font-weight: 500;
}

.cons {
	color: #dc3545;
	font-weight: 500;
}

/* ============================================================================
	GETTING STARTED
	============================================================================ */

.getting-started {
	background: #f8f9fa;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	margin: 30px 0;
	padding: 20px;
}

.getting-started h2 {
	margin-top: 0;
	text-align: center;
}

.steps {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.step {
	background: white;
	padding: 20px;
	border-radius: 6px;
	border: 1px solid #e1e5e9;
}

.step h3 {
	margin-top: 0;
	color: #007cba;
	font-size: 16px;
}

.step pre {
	background: #2d3748;
	color: #e2e8f0;
	padding: 10px;
	border-radius: 4px;
	margin: 10px 0 0 0;
	font-size: 12px;
	line-height: 1.4;
}

.step code {
	background: none;
	padding: 0;
	border-radius: 0;
	font-size: inherit;
}

/* ============================================================================
	FORM STYLING
	============================================================================ */

.campaignbridge-form {
	max-width: none;
}

.campaignbridge-form .form-table th {
	width: 200px;
	min-width: 200px;
}

/* Field groups */
.campaignbridge-radio-group,
.campaignbridge-checkbox-group {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 5px;
}

.campaignbridge-radio-label,
.campaignbridge-checkbox-label {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	cursor: pointer;
	font-weight: normal;
}

/* Custom layout styles */
.custom-form-layout {
	display: flex;
	flex-wrap: wrap;
	gap: 20px;
}

.custom-form-layout .form-section {
	flex: 1;
	min-width: 250px;
	padding: 15px;
	background: white;
	border: 1px solid #e1e5e9;
	border-radius: 6px;
}

.custom-form-layout .form-section h3 {
	margin-top: 0;
	font-size: 16px;
	border-bottom: 1px solid #e1e5e9;
	padding-bottom: 8px;
}

/* Switch styling */
.campaignbridge-switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 24px;
}

.campaignbridge-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

.slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: .4s;
	border-radius: 24px;
}

.slider:before {
	position: absolute;
	content: "";
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .4s;
	border-radius: 50%;
}

input:checked + .slider {
	background-color: #007cba;
}

input:checked + .slider:before {
	transform: translateX(26px);
}

/* Range slider styling */
input[type="range"] {
	-webkit-appearance: none;
	width: 100%;
	height: 6px;
	border-radius: 3px;
	background: #ddd;
	outline: none;
}

input[type="range"]::-webkit-slider-thumb {
	-webkit-appearance: none;
	appearance: none;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: #007cba;
	cursor: pointer;
}

input[type="range"]::-moz-range-thumb {
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: #007cba;
	cursor: pointer;
	border: none;
}

/* Color picker styling */
input[type="color"] {
	width: 60px;
	height: 40px;
	border: 1px solid #ddd;
	border-radius: 4px;
	cursor: pointer;
}

/* Date/time inputs */
input[type="date"],
input[type="time"],
input[type="datetime-local"] {
	padding: 6px 8px;
	border: 1px solid #ddd;
	border-radius: 4px;
	font-size: 14px;
}

/* File upload styling */
.current-files {
	margin-top: 10px;
	padding: 10px;
	background: #f8f9fa;
	border: 1px solid #dee2e6;
	border-radius: 4px;
}

.current-file {
	margin: 5px 0;
	font-size: 12px;
	color: #6c757d;
}

.file-requirements {
	margin-top: 10px;
	padding: 10px;
	background: #e3f2fd;
	border: 1px solid #bbdefb;
	border-radius: 4px;
}

.file-requirements .description {
	margin: 5px 0;
	color: #1565c0;
	font-size: 12px;
}

/* Responsive design */
@media (max-width: 768px) {
	.demo-stats {
		grid-template-columns: repeat(2, 1fr);
	}

	.comparison-row {
		grid-template-columns: 1fr;
		gap: 8px;
		text-align: center;
	}

	.steps {
		grid-template-columns: 1fr;
	}

	.custom-form-layout {
		flex-direction: column;
	}

	.custom-form-layout .form-section {
		min-width: auto;
	}
}
</style>
