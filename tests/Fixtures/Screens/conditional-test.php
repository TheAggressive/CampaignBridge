<?php
/**
 * Conditional Form Test Screen
 *
 * Auto-discovered admin screen to test and demonstrate conditional form functionality.
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
	<h1><?php esc_html_e( 'Conditional Forms Test', 'campaignbridge' ); ?></h1>

	<p><?php esc_html_e( 'This page demonstrates the conditional form functionality. Try enabling/disabling options to see fields appear and disappear.', 'campaignbridge' ); ?></p>

	<div class="campaignbridge-form-container">
		<?php
		$form = Form::make( 'conditional_test_form' )
			->div() // Layout for admin screens.
			->save_to_options() // Uses default: campaignbridge_conditional_test_form_.
			->success( 'Configuration saved successfully!' ) // Success message.
			->submit(); // Auto-generated: "Save Configuration".

		$form->checkbox( 'enable_api' )
			->label( 'Enable API Integration' )
			->description( 'Check this to enable API functionality' );

		$form->select( 'api_provider' )
			->label( 'API Provider' )
			->options(
				array(
					'rest'    => 'REST API',
					'soap'    => 'SOAP API',
					'graphql' => 'GraphQL',
				)
			)
			->show_when(
				array(
					array(
						'field'    => 'enable_api',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Select which type of API you want to configure' );

		$form->text( 'api_endpoint' )
			->label( 'API Endpoint URL' )
			->placeholder( 'https://api.example.com/v1/' )
			->show_when(
				array(
					array(
						'field'    => 'enable_api',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'The base URL for API calls' );

		$form->encrypted( 'api_key' )
			->label( 'API Key' )
			->required()
			->show_when(
				array(
					array(
						'field'    => 'api_provider',
						'operator' => 'equals',
						'value'    => 'rest',
					),
				)
			)
			->description( 'Your REST API key' );

		$form->text( 'wsdl_url' )
			->label( 'WSDL URL' )
			->required()
			->show_when(
				array(
					array(
						'field'    => 'api_provider',
						'operator' => 'equals',
						'value'    => 'soap',
					),
				)
			)
			->description( 'URL to the SOAP WSDL file' );

		$form->textarea( 'graphql_schema' )
			->label( 'GraphQL Schema' )
			->show_when(
				array(
					array(
						'field'    => 'api_provider',
						'operator' => 'equals',
						'value'    => 'graphql',
					),
				)
			)
			->description( 'Paste your GraphQL schema here' );

		$form->checkbox( 'enable_advanced' )
			->label( 'Enable Advanced Features' )
			->description( 'Show additional configuration options' );

		$form->select( 'auth_method' )
			->label( 'Authentication Method' )
			->options(
				array(
					'none'   => 'No Authentication',
					'basic'  => 'Basic Auth',
					'bearer' => 'Bearer Token',
					'oauth'  => 'OAuth 2.0',
				)
			)
			->show_when(
				array(
					array(
						'field'    => 'enable_advanced',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Choose how to authenticate API requests' );

		$form->text( 'username' )
			->label( 'Username' )
			->show_when(
				array(
					array(
						'field'    => 'auth_method',
						'operator' => 'equals',
						'value'    => 'basic',
					),
				)
			)
			->required()
			->description( 'Basic auth username' );

		$form->encrypted( 'password' )
			->label( 'Password' )
			->show_when(
				array(
					array(
						'field'    => 'auth_method',
						'operator' => 'equals',
						'value'    => 'basic',
					),
				)
			)
			->required()
			->description( 'Basic auth password' );

		$form->encrypted( 'bearer_token' )
			->label( 'Bearer Token' )
			->show_when(
				array(
					array(
						'field'    => 'auth_method',
						'operator' => 'equals',
						'value'    => 'bearer',
					),
				)
			)
			->required()
			->description( 'API bearer token' );

		$form->text( 'client_id' )
			->label( 'Client ID' )
			->show_when(
				array(
					array(
						'field'    => 'auth_method',
						'operator' => 'equals',
						'value'    => 'oauth',
					),
				)
			)
			->required()
			->description( 'OAuth client ID' );

		$form->encrypted( 'client_secret' )
			->label( 'Client Secret' )
			->show_when(
				array(
					array(
						'field'    => 'auth_method',
						'operator' => 'equals',
						'value'    => 'oauth',
					),
				)
			)
			->required()
			->description( 'OAuth client secret' );

		$form->checkbox( 'enable_caching' )
			->label( 'Enable Response Caching' )
			->show_when(
				array(
					array(
						'field'    => 'enable_advanced',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Cache API responses for better performance' );

		$form->number( 'cache_ttl' )
			->label( 'Cache TTL (seconds)' )
			->default( 300 )
			->min( 60 )
			->max( 3600 )
			->show_when(
				array(
					array(
						'field'    => 'enable_caching',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'How long to cache responses' );

		$form->checkbox( 'enable_logging' )
			->label( 'Enable API Logging' )
			->show_when(
				array(
					array(
						'field'    => 'enable_advanced',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Log all API requests and responses' );

		$form->select( 'log_level' )
			->label( 'Log Level' )
			->options(
				array(
					'error'   => 'Errors Only',
					'warning' => 'Warnings & Errors',
					'info'    => 'Info & Above',
					'debug'   => 'Debug (All)',
				)
			)
			->default( 'warning' )
			->show_when(
				array(
					array(
						'field'    => 'enable_logging',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'How detailed should logging be?' );

		$form->checkbox( 'send_notifications' )
			->label( 'Send Email Notifications' )
			->show_when(
				array(
					array(
						'field'    => 'enable_api',
						'operator' => 'is_checked',
					),
					array(
						'field'    => 'enable_advanced',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Send email notifications for API events (requires both API and Advanced features)' );

		$form->text( 'notification_email' )
			->label( 'Notification Email' )
			->required()
			->show_when(
				array(
					array(
						'field'    => 'send_notifications',
						'operator' => 'is_checked',
					),
				)
			)
			->description( 'Email address for notifications' );

		$form->submit( 'Save Configuration', 'primary' );

		$form->render();
		?>
	</div>

	<div class="campaignbridge-test-info" style="margin-top: 40px; padding: 20px; background: #f1f1f1; border-left: 4px solid #0073aa;">
		<h3><?php esc_html_e( 'Test Scenarios', 'campaignbridge' ); ?></h3>
		<ul>
			<li><strong>Basic Show/Hide:</strong> Check "Enable API" to show API-related fields</li>
			<li><strong>Nested Conditions:</strong> Select "REST" to show API key field</li>
			<li><strong>Complex Logic:</strong> Enable advanced features for authentication options</li>
			<li><strong>Validation:</strong> Required fields are only required when visible</li>
			<li><strong>Real-time Updates:</strong> Changes happen instantly without page reload</li>
			<li><strong>Caching & Logging:</strong> Advanced features show caching and logging controls</li>
			<li><strong>AND Logic:</strong> Notifications require both API and advanced features</li>
		</ul>

		<h4><?php esc_html_e( 'How It Works', 'campaignbridge' ); ?></h4>
		<p><?php esc_html_e( 'The conditional logic is evaluated both server-side (for security and initial rendering) and client-side (for real-time UX). Hidden fields are completely excluded from validation and submission.', 'campaignbridge' ); ?></p>
	</div>
</div>
