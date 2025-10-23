<?php
/**
 * Notices Testing Page
 *
 * A comprehensive test page for the CampaignBridge Notices system.
 * This demonstrates all notice types and persistence functionality.
 *
 * @package CampaignBridge\Admin\Screens
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Screens;

use CampaignBridge\Notices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submissions
if ( isset( $_POST['test_notices'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'notices_test' ) ) {
	$action = sanitize_key( $_POST['action'] ?? '' );

	switch ( $action ) {
		case 'add_all_notices':
			// Add notices of all types
			Notices::success( '✅ Success: Your settings have been saved successfully!' );
			Notices::info( 'ℹ️ Info: This is an informational message about the system status.' );
			Notices::warning( '⚠️ Warning: Please review your configuration before proceeding.' );
			Notices::error( '❌ Error: Failed to connect to the external API. Please check your credentials.' );
			Notices::success( '<strong>HTML Success:</strong> You can use <em>emphasis</em>, <code>code</code>, and <a href="#" target="_blank">links</a> in notices.' );
			break;

		case 'add_single_notice':
			$type    = sanitize_key( $_POST['notice_type'] ?? 'info' );
			$message = sanitize_text_field( $_POST['notice_message'] ?? 'Test message' );

			switch ( $type ) {
				case 'success':
					Notices::success( $message );
					break;
				case 'warning':
					Notices::warning( $message );
					break;
				case 'error':
					Notices::error( $message );
					break;
				case 'info':
				default:
					Notices::info( $message );
					break;
			}
			break;

		case 'clear_notices':
			$count = Notices::count();
			Notices::clear();
			Notices::success( "Cleared {$count} notices from the queue." );
			break;

		case 'test_persistence':
			Notices::info( 'This notice will persist across page loads globally.', array( 'persist' => true ) );
			// No redirect needed - notices appear immediately!
			Notices::success( 'Notice added! Navigate to another admin page to test persistence.' );

			// Test transient directly
			$test_key = 'cb_test_transient_' . time();
			set_transient( $test_key, 'test_value', 60 );
			$test_value = get_transient( $test_key );
			if ( $test_value === 'test_value' ) {
				Notices::success( '✅ Transients are working correctly.' );
			} else {
				Notices::error( '❌ Transients are NOT working - persistence will fail.' );
			}
			delete_transient( $test_key );
			break;
	}
}
?>

<div class="wrap">
	<h1>🔔 CampaignBridge Notices Testing</h1>

	<p>This page allows you to test the CampaignBridge global notice system with immediate display and optional persistence.</p>

	<div class="notice notice-info">
		<p><strong>How it works:</strong> Notices display immediately on admin pages. Use <code>['persist' => true]</code> to keep notices across page loads globally.</p>
	</div>

	<!-- Current Status -->
	<div class="card">
		<h2>Current Status</h2>
		<table class="widefat">
			<tr>
				<td><strong>Notices in Queue:</strong></td>
				<td><?php echo esc_html( Notices::count() ); ?></td>
			</tr>
			<tr>
				<td><strong>Has Notices:</strong></td>
				<td><?php echo Notices::has_notices() ? '✅ Yes' : '❌ No'; ?></td>
			</tr>
		</table>

		<?php if ( Notices::has_notices() ) : ?>
		<h3>Queued Notices (Debug)</h3>
		<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto;">
			<?php print_r( Notices::get_all() ); ?>
		</pre>
		<?php endif; ?>
	</div>

	<!-- Test Actions -->
	<div class="card">
		<h2>Test Actions</h2>

		<form method="post" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'notices_test' ); ?>
			<input type="hidden" name="test_notices" value="1">
			<input type="hidden" name="action" value="add_all_notices">

			<button type="submit" class="button button-primary">
				🎨 Add All Notice Types
			</button>
			<p class="description">Adds one notice of each type (success, info, warning, error) to demonstrate all styles.</p>
		</form>

		<form method="post" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'notices_test' ); ?>
			<input type="hidden" name="test_notices" value="1">
			<input type="hidden" name="action" value="add_single_notice">

			<table class="form-table">
				<tr>
					<th scope="row">Notice Type</th>
					<td>
						<select name="notice_type">
							<option value="success">Success ✅</option>
							<option value="info">Info ℹ️</option>
							<option value="warning">Warning ⚠️</option>
							<option value="error">Error ❌</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Message</th>
					<td>
						<input type="text" name="notice_message" value="Custom test message" class="regular-text" required>
					</td>
				</tr>
			</table>

			<button type="submit" class="button">
				➕ Add Single Notice
			</button>
		</form>

		<form method="post" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'notices_test' ); ?>
			<input type="hidden" name="test_notices" value="1">
			<input type="hidden" name="action" value="clear_notices">

			<button type="submit" class="button button-secondary">
				🗑️ Clear All Notices
			</button>
			<p class="description">Removes all notices from the queue.</p>
		</form>

		<form method="post">
			<?php wp_nonce_field( 'notices_test' ); ?>
			<input type="hidden" name="test_notices" value="1">
			<input type="hidden" name="action" value="test_persistence">

			<button type="submit" class="button">
				💾 Test Persistence
			</button>
			<p class="description">Adds a persistent notice that survives page refreshes (per-user, only you see it).</p>
		</form>
	</div>

	<!-- Usage Examples -->
	<div class="card">
		<h2>Usage Examples</h2>

		<h3>Basic Usage</h3>
		<pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd;">
// Add different types of notices
use CampaignBridge\Notices;

Notices::success('Settings saved successfully!');
Notices::error('Failed to save settings.');
Notices::warning('Please review your configuration.');
Notices::info('System status updated.');

// Generic method with type parameter
Notices::add('Custom message', 'success');
		</pre>

		<h3>Advanced Usage</h3>
		<pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd;">
// HTML content is supported (will be sanitized)
Notices::success('&lt;strong&gt;Success!&lt;/strong&gt; &lt;a href="#"&gt;View details&lt;/a&gt;');

// Persistent notices (survive page loads for current user only)
Notices::warning('This warning persists for you!', ['persist' => true]);

// Check notice status
if (Notices::has_notices()) {
	$count = Notices::count();
	echo "You have {$count} notices.";
}

// Get all notices for debugging
$all_notices = Notices::get_all();

// Clear all notices
Notices::clear();
		</pre>

		<h3>Integration Examples</h3>
		<pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd;">
// In form handlers
if ($form->is_valid()) {
	Notices::success('Form submitted successfully!');
	wp_redirect(admin_url('admin.php?page=my-plugin'));
	exit;
} else {
	Notices::error('Please correct the form errors.');
}

// In API handlers
try {
	$result = $api->process_request();
	Notices::success('API request completed successfully.');
} catch (Exception $e) {
	Notices::error('API request failed: ' . $e->getMessage());
}

// In settings pages
if (update_option('my_setting', $new_value)) {
	Notices::success('Settings updated successfully!');
} else {
	Notices::error('Failed to update settings.');
}
		</pre>
	</div>

	<!-- Notice Types Reference -->
	<div class="card">
		<h2>Notice Types Reference</h2>
		<table class="widefat">
			<thead>
				<tr>
					<th>Type</th>
					<th>Method</th>
					<th>CSS Classes</th>
					<th>Use Case</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><span style="color: #46b450;">●</span> Success</td>
					<td><code>Notices::success()</code></td>
					<td><code>notice notice-success</code></td>
					<td>Confirming successful operations</td>
				</tr>
				<tr>
					<td><span style="color: #00a0d2;">●</span> Info</td>
					<td><code>Notices::info()</code></td>
					<td><code>notice notice-info</code></td>
					<td>Providing information or tips</td>
				</tr>
				<tr>
					<td><span style="color: #ffb900;">●</span> Warning</td>
					<td><code>Notices::warning()</code></td>
					<td><code>notice notice-warning</code></td>
					<td>Alerting about potential issues</td>
				</tr>
				<tr>
					<td><span style="color: #dc3232;">●</span> Error</td>
					<td><code>Notices::error()</code></td>
					<td><code>notice notice-error</code></td>
					<td>Reporting failures or errors</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<style>
.card {
	background: white;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	margin: 20px 0;
	padding: 20px;
}

.card h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.form-table th {
	width: 150px;
}

.button {
	margin-right: 10px;
}

.description {
	margin: 5px 0 15px 0;
	color: #666;
	font-style: italic;
}
</style>
