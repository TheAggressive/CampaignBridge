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
require_once __DIR__ . '/../../Core/Form.php';

// Get current values.
$cb_provider           = $screen->get( 'provider', get_option( 'cb_provider', 'html' ) );
$cb_mailchimp_api_key  = $screen->get( 'mailchimp_api_key', get_option( 'cb_mailchimp_api_key', '' ) );
$cb_mailchimp_audience = $screen->get( 'mailchimp_audience', get_option( 'cb_mailchimp_audience', '' ) );
$cb_is_connected       = $screen->get( 'mailchimp_connected', false );

// Create the form using the Form API.
$form = \CampaignBridge\Admin\Core\Form::make( 'providers' )
	->select( 'provider', 'Email Provider' )
		->options(
			array(
				'html'      => 'HTML Email (Default)',
				'mailchimp' => 'Mailchimp',
			)
		)
		->default( $cb_provider )
		->required()
	->password( 'mailchimp_api_key', 'Mailchimp API Key' )
		->showWhen( 'provider', 'mailchimp' )
		->description( 'Get your API key from <a href="https://admin.mailchimp.com/account/api/" target="_blank">Mailchimp Account Settings</a>' )
	->text( 'mailchimp_audience', 'Default Audience' )
		->showWhen( 'provider', 'mailchimp' )
		->description( 'Optional. Default audience for new campaigns.' )
	->before_save(
		function ( $data ) {
			// Save to options.
			update_option( 'cb_provider', $data['provider'] );

			if ( $data['provider'] === 'mailchimp' ) {
				if ( ! empty( $data['mailchimp_api_key'] ) ) {
					update_option( 'cb_mailchimp_api_key', $data['mailchimp_api_key'] );
				}
				if ( ! empty( $data['mailchimp_audience'] ) ) {
					update_option( 'cb_mailchimp_audience', $data['mailchimp_audience'] );
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
	if ( $cb_provider === 'mailchimp' ) {
		echo '<div class="connection-status" style="margin-top: 20px;">';
		echo '<h3>' . esc_html__( 'Connection Status', 'campaignbridge' ) . '</h3>';

		if ( $cb_is_connected ) {
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
