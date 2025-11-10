<?php
/**
 * Email Providers Settings Tab.
 *
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 *
 * @package CampaignBridge\Admin\Screens\settings
 */

// Include the Form API.
use CampaignBridge\Admin\Core\Form;

// Get current values.
global $screen;
if ( ! isset( $screen ) ) {
	$screen = null; // Fallback for PHPStan.
}
$campaignbridge_provider           = $screen ? $screen->get( 'provider', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_provider', 'html' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_provider', 'html' );
$campaignbridge_mailchimp_api_key  = $screen ? $screen->get( 'mailchimp_api_key', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_api_key', '' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_api_key', '' );
$campaignbridge_mailchimp_audience = $screen ? $screen->get( 'mailchimp_audience', \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_audience', '' ) ) : \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_audience', '' );
$campaignbridge_is_connected       = $screen ? $screen->get( 'mailchimp_connected', false ) : false;

// Create the form using the Form API.
$form = Form::make( 'providers' )
	->select( 'provider', 'Email Provider' )
		->options(
			array(
				'html'      => 'HTML Email (Default)',
				'mailchimp' => 'Mailchimp',
			)
		)
		->default( $campaignbridge_provider )
		->required()
	->password( 'mailchimp_api_key', 'Mailchimp API Key' )
		->description( 'Get your API key from <a href="https://admin.mailchimp.com/account/api/" target="_blank">Mailchimp Account Settings</a>' )
	->text( 'mailchimp_audience', 'Default Audience' )
		->description( 'Optional. Default audience for new campaigns.' )
	->before_save(
		function ( $data ) {
			// Save to options.
					\CampaignBridge\Core\Storage::update_option( 'campaignbridge_provider', $data['provider'] );

			if ( 'mailchimp' === $data['provider'] ) {
				if ( ! empty( $data['mailchimp_api_key'] ) ) {
					\CampaignBridge\Core\Storage::update_option( 'campaignbridge_mailchimp_api_key', $data['mailchimp_api_key'] );
				}
				if ( ! empty( $data['mailchimp_audience'] ) ) {
					\CampaignBridge\Core\Storage::update_option( 'campaignbridge_mailchimp_audience', $data['mailchimp_audience'] );
				}
			}

			// Return modified data if needed.
			return $data;
		}
	)
	->success( 'Provider settings saved successfully!' )
	->submit( 'Save Provider Settings' );
?>

<div class="providers-settings-tab">
	<h2><?php esc_html_e( 'Email Provider Integration', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure your email service provider settings and API connections.', 'campaignbridge' ); ?>
	</p>

	<?php
	// Render the form - handles all HTML, validation, and conditional logic.
	$form->render();

	// Show connection status if Mailchimp is selected.
	if ( 'mailchimp' === $campaignbridge_provider ) {
		echo '<div class="connection-status" style="margin-top: 20px;">';
		echo '<h3>' . esc_html__( 'Connection Status', 'campaignbridge' ) . '</h3>';

		if ( $campaignbridge_is_connected ) {
			echo '<span class="status-badge connected">';
			echo '<span class="dashicons dashicons-yes-alt"></span> ';
			echo '<strong>' . esc_html__( 'Connected', 'campaignbridge' ) . '</strong>';
			echo '</span>';
		} else {
			echo '<span class="status-badge disconnected">';
			echo '<span class="dashicons dashicons-dismiss"></span> ';
			echo '<strong>' . esc_html__( 'Not Connected', 'campaignbridge' ) . '</strong>';
			echo '</span>';
		}

		echo '</div>';
	}
	?>

</div>

<style>
	.providers-settings-tab {
		background: white;
		padding: 20px;
		margin-top: 20px;
		border: 1px solid #ddd;
	}

	.connection-status {
		margin-top: 20px;
		padding: 15px;
		background: #f8f9fa;
		border-radius: 4px;
	}

	.status-badge {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		padding: 5px 10px;
		border-radius: 3px;
	}

	.status-badge.connected {
		background: #d4edda;
		color: #155724;
	}

	.status-badge.disconnected {
		background: #f8d7da;
		color: #721c24;
	}
</style>
