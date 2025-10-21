<?php
/**
 * Simple Form Example - Working Form with Proper Submission Handling
 *
 * This shows how to create a working form with proper submission handling,
 * validation, and success messages. Copy-paste this code into any screen file!
 *
 * @package CampaignBridge\Admin\Screens
 */


// Step 1: Include the Form system.
use CampaignBridge\Admin\Core\Form;

// Step 2: Create your form with REAL-TIME VALIDATION (No ->end() needed!).
$form = Form::make( 'simple_example' )
	->save_to_options() // Save to WordPress options
	->description( 'This is a simple form example with real-time validation.' )
	->text( 'your_name', 'Your Name' )
		->required()
		->min_length( 2 )
		->max_length( 50 )
		->placeholder( 'Enter your full name' )
	->email( 'your_email', 'Your Email' )
		->required()
		->placeholder( 'Enter your email address' )
	->url( 'website_url', 'Website URL' )
		->placeholder( 'https://example.com' )
	->number( 'age', 'Your Age' )
		->min( 18 )
		->max( 120 )
		->placeholder( 'Enter your age' )
	->password( 'password', 'Password' )
		->required()
		->min_length( 8 )
		->placeholder( 'Create a secure password' )
	->textarea( 'message', 'Message' )
		->max_length( 500 )
		->placeholder( 'Enter your message (max 500 characters)' )
		->rows( 4 )
	->success( 'Form submitted successfully with real-time validation!' ) // Optional - defaults provided
	// ->error('Custom error message')     // Optional - defaults provided
	->submit( 'Submit Form with Real-Time Validation' );

// Step 4: Render the form
$form->render();

// Debug: Show raw HTML output for first field
echo '<hr><h3>🔧 Raw HTML Debug:</h3>';
echo '<p><strong>This shows what the first field looks like in HTML:</strong></p>';
echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-size: 12px; overflow-x: auto;">';

// Create a mini version of the form to inspect HTML
$debug_form = \CampaignBridge\Admin\Core\Form::make( 'debug' )
	->text( 'debug_name', 'Debug Name' )
		->required()
		->min_length( 2 )
	->email( 'debug_email', 'Debug Email' )
		->required();

ob_start();
$debug_form->render();
$debug_html = ob_get_clean();

// Extract just the input fields from the debug HTML
preg_match_all( '/<input[^>]*data-validation="[^"]*"[^>]*>/', $debug_html, $matches );
if ( ! empty( $matches[0] ) ) {
	echo '<strong>✅ Found data-validation attributes:</strong><br>';
	foreach ( $matches[0] as $input ) {
		// Extract just the key parts for readability
		$input = preg_replace( '/\s+/', ' ', $input ); // normalize whitespace
		echo htmlspecialchars( $input ) . '<br>';
	}
} else {
	echo '<strong>❌ No data-validation attributes found!</strong><br>';
	echo 'Raw HTML: ' . htmlspecialchars( substr( $debug_html, 0, 500 ) ) . '...';
}
echo '</pre>';



// 🎉 Success/error messages now automatically appear as WordPress admin notices!
// No manual use_screen_notices() calls needed - happens automatically during form processing.

// ✨ REAL-TIME VALIDATION DEMO INSTRUCTIONS ✨
echo '<hr><h3>🧪 Test Real-Time Validation:</h3>';
echo '<ul>';
echo '<li><strong>Name field:</strong> Try typing 1 character (shows error), then 2+ characters (shows success)</li>';
echo '<li><strong>Email field:</strong> Type "invalid-email" (shows error), then "user@example.com" (shows success)</li>';
echo '<li><strong>Age field:</strong> Type "17" (shows error), then "25" (shows success)</li>';
echo '<li><strong>Password field:</strong> Type "short" (shows error), then "longpassword123" (shows success)</li>';
echo '<li><strong>Message field:</strong> Type more than 500 characters to see length validation</li>';
echo '<li><strong>All fields:</strong> Leave required fields empty or try invalid data to see instant feedback</li>';
echo '</ul>';

echo '<div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 8px;">';
echo '<h4>💡 How Real-Time Validation Works:</h4>';
echo '<ol>';
echo '<li><strong>PHP Form Creation:</strong> Validation rules are set in your form builder code</li>';
echo '<li><strong>HTML Generation:</strong> Rules are converted to JavaScript format and added as <code>data-validation</code> attributes</li>';
echo '<li><strong>Page Load:</strong> JavaScript automatically finds and initializes validation for all fields</li>';
echo '<li><strong>User Interaction:</strong> As users type, validation runs with 300ms debounce</li>';
echo '<li><strong>Visual Feedback:</strong> CSS classes change instantly showing valid/invalid states</li>';
echo '<li><strong>Accessibility:</strong> Screen readers announce validation changes</li>';
echo '<li><strong>Form Submission:</strong> JavaScript validation prevents invalid submissions</li>';
echo '</ol>';
echo '</div>';

echo '<hr><h3>🔧 Available Validation Methods:</h3>';
echo '<ul>';
echo '<li><code>->required()</code> - Field must have a value</li>';
echo '<li><code>->min_length(n)</code> - Minimum character count</li>';
echo '<li><code>->max_length(n)</code> - Maximum character count</li>';
echo '<li><code>->min(n)</code> - Minimum numeric value (for number fields)</li>';
echo '<li><code>->max(n)</code> - Maximum numeric value (for number fields)</li>';
echo '<li><code>->placeholder("text")</code> - Add placeholder text</li>';
echo '<li><code>->description("text")</code> - Add help text below field</li>';
echo '</ul>';

echo '<div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; margin: 20px 0; border-radius: 8px;">';
echo '<h4>✅ Real-Time Validation Active!</h4>';
echo '<p>The form now has automatic real-time validation. Try typing in the fields below to see instant feedback.</p>';
echo '<p><strong>Features:</strong> Required field validation, email format checking, length limits, numeric ranges</p>';
echo '</div>';

// Debug: Show validation data attributes
echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px;">';
echo '<h4>🔍 Debug Info:</h4>';
echo '<p><strong>Validation Script Loaded:</strong> ';
echo wp_script_is( 'campaignbridge-form-validation', 'enqueued' ) ? '✅ Yes' : '❌ No';
echo '</p>';
echo '<p><strong>Form Fields with Validation:</strong></p>';
echo '<ul>';
echo '<li><code>your_name</code>: required, min_length=2, max_length=50</li>';
echo '<li><code>your_email</code>: required, email format</li>';
echo '<li><code>website_url</code>: optional, URL format</li>';
echo '<li><code>age</code>: min=18, max=120</li>';
echo '<li><code>password</code>: required, min_length=8</li>';
echo '<li><code>message</code>: max_length=500</li>';
echo '</ul>';
echo '<p><strong>All CampaignBridge Scripts:</strong> ';
$scripts                = wp_scripts();
$enqueued               = array_keys( $scripts->queue );
$campaignbridge_scripts = array_filter(
	$enqueued,
	function ( $script ) {
		return strpos( $script, 'campaignbridge' ) !== false;
	}
);
echo implode( ', ', $campaignbridge_scripts );
echo '</p>';
echo '</div>';

echo '<h3>🎨 Visual States:</h3>';
echo '<ul>';
echo '<li><span style="color: #dc2626;">🔴 Invalid:</span> Red border, shake animation, error messages</li>';
echo '<li><span style="color: #16a34a;">🟢 Valid:</span> Green border, checkmark icon, success styling</li>';
echo '<li><span style="color: #3b82f6;">🔵 Validating:</span> Blue border, loading animation</li>';
echo '<li>⚪ Neutral: Default styling, no validation feedback</li>';
echo '</ul>';
