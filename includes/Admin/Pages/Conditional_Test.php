<?php
/**
 * Conditional Form Test Page
 *
 * Admin page to test and demonstrate conditional form functionality.
 *
 * @package CampaignBridge\Admin\Pages
 */

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_Field_Builder;

/**
 * Conditional Form Test Page
 *
 * @package CampaignBridge\Admin\Pages
 */
class Conditional_Test {

	/**
	 * Page hook suffix
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page(): void {
		$this->hook_suffix = add_menu_page(
			__( 'Conditional Forms Test', 'campaignbridge' ),
			__( 'Conditional Test', 'campaignbridge' ),
			'manage_options',
			'conditional-test',
			array( $this, 'render_page' ),
			'dashicons-forms',
			30
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		// Enqueue form styles
		\CampaignBridge\Admin\Asset_Manager::enqueue_asset( 'campaignbridge-admin-form-styles' );
	}

	/**
	 * Render the test page
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Conditional Forms Test', 'campaignbridge' ); ?></h1>

			<p><?php esc_html_e( 'This page demonstrates the conditional form functionality. Try enabling/disabling options to see fields appear and disappear.', 'campaignbridge' ); ?></p>

			<div class="campaignbridge-form-container" data-conditional data-conditional-engine="v2">
				<?php $this->render_test_form(); ?>
			</div>

			<div class="campaignbridge-test-info" style="margin-top: 40px; padding: 20px; background: #f1f1f1; border-left: 4px solid #0073aa;">
				<h3><?php esc_html_e( 'Test Scenarios', 'campaignbridge' ); ?></h3>
				<ul>
					<li><strong>Basic Show/Hide:</strong> Check "Enable API" to show API-related fields</li>
					<li><strong>Nested Conditions:</strong> Select "REST" to show API key field</li>
					<li><strong>Complex Logic:</strong> Enable advanced features for more options</li>
					<li><strong>Validation:</strong> Required fields are only required when visible</li>
					<li><strong>Real-time Updates:</strong> Changes happen instantly without page reload</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the test form
	 */
	private function render_test_form(): void {
		$form = Form::create(
			'conditional_test_form',
			array(
				'method' => 'POST',
				'action' => admin_url( 'admin-post.php' ),
				'layout' => 'div',
			)
		);

		$form->add_checkbox( 'enable_api' )
			->label( 'Enable API Integration' )
			->description( 'Check this to enable API functionality' );

		$form->add_select( 'api_provider' )
			->label( 'API Provider' )
			->options(
				array(
					'rest'    => 'REST API',
					'soap'    => 'SOAP API',
					'graphql' => 'GraphQL',
				)
			)
			->show_when( 'enable_api' )->is_checked()
			->description( 'Select which type of API you want to configure' );

		$form->add_text( 'api_endpoint' )
			->label( 'API Endpoint URL' )
			->placeholder( 'https://api.example.com/v1/' )
			->show_when( 'enable_api' )->is_checked()
			->description( 'The base URL for API calls' );

		$form->add_text( 'api_key' )
			->label( 'API Key' )
			->required()
			->show_when( 'api_provider' )->equals( 'rest' )
			->description( 'Your REST API key' );

		$form->add_text( 'wsdl_url' )
			->label( 'WSDL URL' )
			->required()
			->show_when( 'api_provider' )->equals( 'soap' )
			->description( 'URL to the SOAP WSDL file' );

		$form->add_textarea( 'graphql_schema' )
			->label( 'GraphQL Schema' )
			->show_when( 'api_provider' )->equals( 'graphql' )
			->description( 'Paste your GraphQL schema here' );

		$form->add_checkbox( 'enable_advanced' )
			->label( 'Enable Advanced Features' )
			->description( 'Show additional configuration options' );

		$form->add_select( 'auth_method' )
			->label( 'Authentication Method' )
			->options(
				array(
					'none'   => 'No Authentication',
					'basic'  => 'Basic Auth',
					'bearer' => 'Bearer Token',
					'oauth'  => 'OAuth 2.0',
				)
			)
			->show_when( 'enable_advanced' )->is_checked()
			->description( 'Choose how to authenticate API requests' );

		$form->add_text( 'username' )
			->label( 'Username' )
			->show_when( 'auth_method' )->equals( 'basic' )
			->required()
			->description( 'Basic auth username' );

		$form->add_encrypted( 'password' )
			->label( 'Password' )
			->show_when( 'auth_method' )->equals( 'basic' )
			->required()
			->description( 'Basic auth password' );

		$form->add_encrypted( 'bearer_token' )
			->label( 'Bearer Token' )
			->show_when( 'auth_method' )->equals( 'bearer' )
			->required()
			->description( 'API bearer token' );

		$form->add_text( 'client_id' )
			->label( 'Client ID' )
			->show_when( 'auth_method' )->equals( 'oauth' )
			->required()
			->description( 'OAuth client ID' );

		$form->add_encrypted( 'client_secret' )
			->label( 'Client Secret' )
			->show_when( 'auth_method' )->equals( 'oauth' )
			->required()
			->description( 'OAuth client secret' );

		$form->add_checkbox( 'enable_caching' )
			->label( 'Enable Response Caching' )
			->show_when( 'enable_advanced' )->is_checked()
			->description( 'Cache API responses for better performance' );

		$form->add_number( 'cache_ttl' )
			->label( 'Cache TTL (seconds)' )
			->default( 300 )
			->min( 60 )
			->max( 3600 )
			->show_when( 'enable_caching' )->is_checked()
			->description( 'How long to cache responses' );

		$form->add_checkbox( 'enable_logging' )
			->label( 'Enable API Logging' )
			->show_when( 'enable_advanced' )->is_checked()
			->description( 'Log all API requests and responses' );

		$form->add_select( 'log_level' )
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
			->show_when( 'enable_logging' )->is_checked()
			->description( 'How detailed should logging be?' );

		// Complex conditional with AND logic
		$form->add_checkbox( 'send_notifications' )
			->label( 'Send Email Notifications' )
			->show_when( 'enable_api' )->is_checked()
			->and(
				function ( $builder ) {
					return $builder->field( 'enable_advanced' )->is_checked();
				}
			)
			->description( 'Send email notifications for API events' );

		$form->add_text( 'notification_email' )
			->label( 'Notification Email' )
			->required()
			->show_when( 'send_notifications' )->is_checked()
			->description( 'Email address for notifications' );

		$form->add_submit_button()
			->text( 'Save Configuration' )
			->type( 'primary' );

		$form->handle_submission();

		if ( $form->is_submitted() && $form->is_valid() ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configuration saved successfully!', 'campaignbridge' ) . '</p></div>';
		}

		$form->render();
	}
}
