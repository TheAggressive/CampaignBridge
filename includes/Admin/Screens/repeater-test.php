<?php
/**
 * Repeater Field Test Screen.
 *
 * Demonstrates and tests all repeater field capabilities.
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the Form API.
use CampaignBridge\Admin\Core\Form;

// Get test data from controller.
global $screen;
if ( ! isset( $screen ) ) {
	$screen = null; // Fallback for PHPStan
}
$test_data = $screen ? $screen->get( 'test_data', array() ) : array();

// Example 1: Stateless Mode (no persistent data).
$preferences = array(
	'newsletter' => 'Subscribe to Newsletter',
	'updates'    => 'Receive Product Updates',
	'promotions' => 'Receive Promotional Offers',
	'tips'       => 'Weekly Tips & Tricks',
);

$form1 = Form::make( 'repeater_stateless' )
	->description( 'Stateless mode - all choices start unchecked' )
	->repeater( 'preferences', $preferences )->switch()
	->save_to_options( 'campaignbridge_test_' )
	->success( 'Stateless preferences saved!' )
	->submit( 'Save Preferences' );

// Example 2: State-Based Mode (with persistent data).
$features = array(
	'analytics'   => 'Analytics Dashboard',
	'reporting'   => 'Advanced Reporting',
	'export'      => 'Data Export',
	'integration' => 'API Integration',
	'automation'  => 'Workflow Automation',
);

$saved_features = get_option( 'campaignbridge_test_enabled_features', array() );

$form2 = Form::make( 'repeater_state_based' )
	->description( 'State-based mode - compares with persistent data' )
	->repeater( 'enabled_features', $features, $saved_features )->switch()
	->save_to_options( 'campaignbridge_test_' )
	->success( 'Feature settings saved!' )
	->submit( 'Save Features' );

// Example 3: With Default Checked.
$notifications = array(
	'email'   => 'Email Notifications',
	'sms'     => 'SMS Notifications',
	'push'    => 'Push Notifications',
	'browser' => 'Browser Notifications',
);

$form3 = Form::make( 'repeater_with_default' )
	->description( 'With default - specific choice checked by default' )
	->repeater( 'notification_types', $notifications )->default( 'email' )->switch()
	->save_to_options( 'campaignbridge_test_' )
	->success( 'Notification settings saved!' )
	->submit( 'Save Notifications' );

// Example 4: Checkbox Type.
$permissions = array(
	'read'   => 'Read Access',
	'write'  => 'Write Access',
	'delete' => 'Delete Access',
	'admin'  => 'Admin Access',
);

$saved_permissions = get_option( 'campaignbridge_test_user_permissions', array() );

$form4 = Form::make( 'repeater_checkbox' )
	->description( 'Checkbox field type example' )
	->repeater( 'user_permissions', $permissions, $saved_permissions )->checkbox()
	->save_to_options( 'campaignbridge_test_' )
	->success( 'Permissions saved!' )
	->submit( 'Save Permissions' );

// Example 5: Radio Type.
$themes = array(
	'light'  => 'Light Theme',
	'dark'   => 'Dark Theme',
	'auto'   => 'Auto (System)',
	'custom' => 'Custom Theme',
);

$saved_theme = get_option( 'campaignbridge_test_theme_preference', array() );

$form5 = Form::make( 'repeater_radio' )
	->description( 'Radio field type example' )
	->repeater( 'theme_preference', $themes, $saved_theme )->radio()
	->save_to_options( 'campaignbridge_test_' )
	->success( 'Theme preference saved!' )
	->submit( 'Save Theme' );
?>

<div class="campaignbridge-repeater-test">
	<div class="campaignbridge-repeater-test__header">
		<h1><?php esc_html_e( 'Repeater Field Test Suite', 'campaignbridge' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'This screen demonstrates all repeater field capabilities including different modes, field types, and use cases.', 'campaignbridge' ); ?>
		</p>
		<?php settings_errors( 'campaignbridge_repeater_test' ); ?>
	</div>

	<div class="campaignbridge-repeater-test__content">
		<!-- Example 1: Stateless Mode -->
		<div class="campaignbridge-repeater-test__section">
			<div class="campaignbridge-repeater-test__section-header">
				<h2><?php esc_html_e( '1. Stateless Mode (Switch Fields)', 'campaignbridge' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'All choices start unchecked. No persistent data comparison.', 'campaignbridge' ); ?>
				</p>
				<code class="campaignbridge-repeater-test__code">
					->repeater('preferences', $choices)->switch()
				</code>
			</div>
			<div class="campaignbridge-repeater-test__form">
				<?php $form1->render(); ?>
			</div>
		</div>

		<!-- Example 2: State-Based Mode -->
		<div class="campaignbridge-repeater-test__section">
			<div class="campaignbridge-repeater-test__section-header">
				<h2><?php esc_html_e( '2. State-Based Mode (Switch Fields)', 'campaignbridge' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Automatically compares with saved data and checks matching choices.', 'campaignbridge' ); ?>
				</p>
				<code class="campaignbridge-repeater-test__code">
					->repeater('enabled_features', $choices, $saved_data)->switch()
				</code>
			</div>
			<div class="campaignbridge-repeater-test__form">
				<?php $form2->render(); ?>
			</div>
		</div>

		<!-- Example 3: With Default -->
		<div class="campaignbridge-repeater-test__section">
			<div class="campaignbridge-repeater-test__section-header">
				<h2><?php esc_html_e( '3. With Default Checked (Switch Fields)', 'campaignbridge' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Specific choice is checked by default (email in this case).', 'campaignbridge' ); ?>
				</p>
				<code class="campaignbridge-repeater-test__code">
					->repeater('notifications', $choices)->default('email')->switch()
				</code>
			</div>
			<div class="campaignbridge-repeater-test__form">
				<?php $form3->render(); ?>
			</div>
		</div>

		<!-- Example 4: Checkbox Type -->
		<div class="campaignbridge-repeater-test__section">
			<div class="campaignbridge-repeater-test__section-header">
				<h2><?php esc_html_e( '4. Checkbox Field Type', 'campaignbridge' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Using checkbox() method instead of switch().', 'campaignbridge' ); ?>
				</p>
				<code class="campaignbridge-repeater-test__code">
					->repeater('permissions', $choices, $saved_data)->checkbox()
				</code>
			</div>
			<div class="campaignbridge-repeater-test__form">
				<?php $form4->render(); ?>
			</div>
		</div>

		<!-- Example 5: Radio Type -->
		<div class="campaignbridge-repeater-test__section">
			<div class="campaignbridge-repeater-test__section-header">
				<h2><?php esc_html_e( '5. Radio Field Type', 'campaignbridge' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Using radio() method for single selection.', 'campaignbridge' ); ?>
				</p>
				<code class="campaignbridge-repeater-test__code">
					->repeater('theme', $choices, $saved_data)->radio()
				</code>
			</div>
			<div class="campaignbridge-repeater-test__form">
				<?php $form5->render(); ?>
			</div>
		</div>

		<!-- Test Results Summary -->
		<div class="campaignbridge-repeater-test__summary">
			<h2><?php esc_html_e( 'Test Results Summary', 'campaignbridge' ); ?></h2>

			<?php if ( ! empty( $_POST ) && current_user_can( 'manage_options' ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test/debug output only ?>
				<div class="campaignbridge-repeater-test__debug">
					<h3>Debug: Last POST Data</h3>
					<code><?php echo esc_html( wp_json_encode( $_POST, JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' ); ?></code>
				</div>
			<?php endif; ?>

			<div class="campaignbridge-repeater-test__debug">
				<h3>Debug: Database State</h3>
				<strong>Raw Database Values:</strong>
				<code>
				<?php
					$db_values = array(
						'campaignbridge_test_preferences' => get_option( 'campaignbridge_test_preferences', 'NOT SET' ),
						'campaignbridge_test_enabled_features' => get_option( 'campaignbridge_test_enabled_features', 'NOT SET' ),
						'campaignbridge_test_notification_types' => get_option( 'campaignbridge_test_notification_types', 'NOT SET' ),
						'campaignbridge_test_user_permissions' => get_option( 'campaignbridge_test_user_permissions', 'NOT SET' ),
						'campaignbridge_test_theme_preference' => get_option( 'campaignbridge_test_theme_preference', 'NOT SET' ),
					);
					echo esc_html( wp_json_encode( $db_values, JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' );
					?>
				</code>
			</div>

			<div class="campaignbridge-repeater-test__results">
				<div class="campaignbridge-repeater-test__result">
					<strong>Stateless Preferences:</strong>
					<code><?php echo esc_html( wp_json_encode( get_option( 'campaignbridge_test_preferences', array() ), JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' ); ?></code>
				</div>
				<div class="campaignbridge-repeater-test__result">
					<strong>Enabled Features:</strong>
					<code><?php echo esc_html( wp_json_encode( get_option( 'campaignbridge_test_enabled_features', array() ), JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' ); ?></code>
				</div>
				<div class="campaignbridge-repeater-test__result">
					<strong>Notification Types:</strong>
					<code><?php echo esc_html( wp_json_encode( get_option( 'campaignbridge_test_notification_types', array() ), JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' ); ?></code>
				</div>
				<div class="campaignbridge-repeater-test__result">
					<strong>User Permissions:</strong>
					<code><?php echo esc_html( wp_json_encode( get_option( 'campaignbridge_test_user_permissions', array() ), JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' ); ?></code>
				</div>
				<div class="campaignbridge-repeater-test__result">
					<strong>Theme Preference:</strong>
					<code><?php echo esc_html( wp_json_encode( get_option( 'campaignbridge_test_theme_preference', array() ), JSON_PRETTY_PRINT ) ?: 'JSON encoding failed' ); ?></code>
				</div>
			</div>
		</div>

		<!-- Documentation Links -->
		<div class="campaignbridge-repeater-test__docs">
			<h3><?php esc_html_e( 'Documentation', 'campaignbridge' ); ?></h3>
			<ul>
				<li><strong>FORM.md:</strong> Complete usage documentation</li>
				<li><strong>CHANGELOG.md:</strong> Migration guide and version history</li>
				<li><strong>MANUAL_TESTING_GUIDE.md:</strong> Comprehensive testing scenarios</li>
				<li><strong>Tests:</strong> tests/Unit/Form_Field_Repeater_Test.php (25 tests, all passing)</li>
			</ul>
		</div>
	</div>
</div>

<style>
/* CampaignBridge Repeater Test Styles - Following BEM conventions */

.campaignbridge-repeater-test {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	margin-top: 20px;
	box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.campaignbridge-repeater-test__header {
	padding: 24px 24px 0 24px;
	border-bottom: 1px solid #e5e5e5;
	margin-bottom: 0;
}

.campaignbridge-repeater-test__header h1 {
	margin: 0 0 12px 0;
	font-size: 23px;
	font-weight: 400;
	line-height: 1.3;
}

.campaignbridge-repeater-test__header .description {
	margin: 0 0 20px 0;
	color: #666;
	font-size: 14px;
}

.campaignbridge-repeater-test__content {
	padding: 24px;
}

.campaignbridge-repeater-test__section {
	margin-bottom: 40px;
	padding-bottom: 40px;
	border-bottom: 2px solid #e5e5e5;
}

.campaignbridge-repeater-test__section:last-of-type {
	border-bottom: none;
}

.campaignbridge-repeater-test__section-header h2 {
	margin: 0 0 8px 0;
	font-size: 18px;
	font-weight: 600;
	line-height: 1.4;
	color: #1d2327;
}

.campaignbridge-repeater-test__section-header .description {
	margin: 0 0 12px 0;
	color: #666;
	font-size: 14px;
	line-height: 1.5;
}

.campaignbridge-repeater-test__code {
	display: block;
	padding: 12px 16px;
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	font-family: Consolas, Monaco, monospace;
	font-size: 13px;
	color: #2271b1;
	margin-bottom: 16px;
	overflow-x: auto;
}

.campaignbridge-repeater-test__form {
	background: #f8f9fa;
	padding: 24px;
	border: 1px solid #e5e5e5;
	border-radius: 6px;
}

.campaignbridge-repeater-test__summary {
	background: #f0f6fc;
	border: 1px solid #0073aa;
	border-radius: 6px;
	padding: 24px;
	margin-bottom: 24px;
}

.campaignbridge-repeater-test__summary h2 {
	margin: 0 0 16px 0;
	font-size: 16px;
	font-weight: 600;
	color: #0073aa;
}

.campaignbridge-repeater-test__debug {
	background: #fff3cd;
	border: 1px solid #ffc107;
	border-radius: 4px;
	padding: 16px;
	margin-bottom: 20px;
}

.campaignbridge-repeater-test__debug h3 {
	margin: 0 0 12px 0;
	font-size: 14px;
	font-weight: 600;
	color: #856404;
}

.campaignbridge-repeater-test__debug code {
	display: block;
	max-height: 300px;
	overflow: auto;
	padding: 12px;
	background: #fff;
	border: 1px solid #ffc107;
	border-radius: 3px;
	font-family: Consolas, Monaco, monospace;
	font-size: 11px;
	line-height: 1.5;
	white-space: pre-wrap;
	word-break: break-all;
}

.campaignbridge-repeater-test__results {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.campaignbridge-repeater-test__result {
	padding: 12px;
	background: #fff;
	border: 1px solid #0073aa;
	border-radius: 4px;
}

.campaignbridge-repeater-test__result strong {
	display: block;
	margin-bottom: 6px;
	color: #0073aa;
	font-size: 14px;
}

.campaignbridge-repeater-test__result code {
	display: block;
	padding: 8px;
	background: #f6f7f7;
	border-radius: 3px;
	font-family: Consolas, Monaco, monospace;
	font-size: 12px;
	color: #1d2327;
	overflow-x: auto;
	word-break: break-all;
}

.campaignbridge-repeater-test__docs {
	background: #fff3cd;
	border: 1px solid #ffc107;
	border-radius: 6px;
	padding: 20px;
}

.campaignbridge-repeater-test__docs h3 {
	margin: 0 0 12px 0;
	font-size: 14px;
	font-weight: 600;
	color: #856404;
}

.campaignbridge-repeater-test__docs ul {
	margin: 0;
	padding-left: 20px;
}

.campaignbridge-repeater-test__docs li {
	margin-bottom: 8px;
	color: #856404;
	font-size: 13px;
	line-height: 1.5;
}

/* Responsive adjustments */
@media screen and (max-width: 782px) {
	.campaignbridge-repeater-test__content {
		padding: 16px;
	}

	.campaignbridge-repeater-test__header {
		padding: 16px 16px 0 16px;
	}

	.campaignbridge-repeater-test__section {
		margin-bottom: 30px;
		padding-bottom: 30px;
	}
}
</style>
