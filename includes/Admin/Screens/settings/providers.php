<?php
/**
 * Email Providers Settings Tab
 *
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 */

// Get data from controller or options
$provider           = $screen->get( 'provider', get_option( 'cb_provider', 'html' ) );
$mailchimp_api_key  = $screen->get( 'mailchimp_api_key', get_option( 'cb_mailchimp_api_key', '' ) );
$mailchimp_audience = $screen->get( 'mailchimp_audience', get_option( 'cb_mailchimp_audience', '' ) );
$is_connected       = $screen->get( 'mailchimp_connected', false );

// Handle form submission
if ( $screen->is_post() && $screen->verify_nonce( 'save_providers' ) ) {
	$provider           = $screen->post( 'provider' );
	$mailchimp_api_key  = $screen->post( 'mailchimp_api_key' );
	$mailchimp_audience = $screen->post( 'mailchimp_audience' );

	// Validate
	if ( empty( $provider ) ) {
		$screen->add_error( __( 'Provider selection is required', 'campaignbridge' ) );
	} else {
		// Save basic settings
		update_option( 'cb_provider', $provider );

		if ( $provider === 'mailchimp' && ! empty( $mailchimp_api_key ) ) {
			update_option( 'cb_mailchimp_api_key', $mailchimp_api_key );
			update_option( 'cb_mailchimp_audience', $mailchimp_audience );
			$screen->add_message( __( 'Mailchimp settings saved!', 'campaignbridge' ) );
		} else {
			$screen->add_message( __( 'Provider settings saved!', 'campaignbridge' ) );
		}
	}
}
?>

<div class="providers-settings-tab">
	<h2><?php _e( 'Email Provider Integration', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php _e( 'Configure your email service provider settings and API connections.', 'campaignbridge' ); ?>
	</p>

	<form method="post" action="">
		<?php $screen->nonce_field( 'save_providers' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="provider">
							<?php _e( 'Email Provider', 'campaignbridge' ); ?>
							<span class="required">*</span>
						</label>
					</th>
					<td>
						<select id="provider" name="provider" class="regular-text" required>
							<option value="html" <?php selected( $provider, 'html' ); ?>>
								<?php _e( 'HTML Email (Default)', 'campaignbridge' ); ?>
							</option>
							<option value="mailchimp" <?php selected( $provider, 'mailchimp' ); ?>>
								<?php _e( 'Mailchimp', 'campaignbridge' ); ?>
							</option>
						</select>
						<p class="description">
							<?php _e( 'Select your email service provider.', 'campaignbridge' ); ?>
						</p>
					</td>
				</tr>

				<?php if ( $provider === 'mailchimp' ) : ?>
					<tr class="mailchimp-fields">
						<th scope="row">
							<label for="mailchimp_api_key">
								<?php _e( 'Mailchimp API Key', 'campaignbridge' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="mailchimp_api_key"
								name="mailchimp_api_key"
								value="<?php echo esc_attr( $mailchimp_api_key ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter your Mailchimp API key', 'campaignbridge' ); ?>"
							>
							<p class="description">
								<?php
								printf(
									__( 'Get your API key from <a href="%s" target="_blank">Mailchimp Account Settings</a>', 'campaignbridge' ),
									'https://admin.mailchimp.com/account/api/'
								);
								?>
							</p>
						</td>
					</tr>

					<tr class="mailchimp-fields">
						<th scope="row">
							<label for="mailchimp_audience">
								<?php _e( 'Default Audience', 'campaignbridge' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="mailchimp_audience"
								name="mailchimp_audience"
								value="<?php echo esc_attr( $mailchimp_audience ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter audience/list ID', 'campaignbridge' ); ?>"
							>
							<p class="description">
								<?php _e( 'Optional. Default audience for new campaigns.', 'campaignbridge' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php _e( 'Connection Status', 'campaignbridge' ); ?>
						</th>
						<td>
							<?php if ( $is_connected ) : ?>
								<span class="status-badge connected">
									<span class="dashicons dashicons-yes-alt"></span>
									<strong><?php _e( 'Connected', 'campaignbridge' ); ?></strong>
								</span>
							<?php else : ?>
								<span class="status-badge disconnected">
									<span class="dashicons dashicons-dismiss"></span>
									<strong><?php _e( 'Not Connected', 'campaignbridge' ); ?></strong>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Provider Settings', 'campaignbridge' ) ); ?>
	</form>

</div>

<style>
	.providers-settings-tab {
		background: white;
		padding: 20px;
		margin-top: 20px;
		border: 1px solid #ddd;
	}

	.mailchimp-fields {
		display: none;
	}

	.mailchimp-fields.show {
		display: table-row;
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

	.required {
		color: #d63638;
		font-weight: bold;
	}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const providerSelect = document.getElementById('provider');
	const mailchimpFields = document.querySelectorAll('.mailchimp-fields');

	function toggleMailchimpFields() {
		if (providerSelect.value === 'mailchimp') {
			mailchimpFields.forEach(field => field.classList.add('show'));
		} else {
			mailchimpFields.forEach(field => field.classList.remove('show'));
		}
	}

	providerSelect.addEventListener('change', toggleMailchimpFields);
	toggleMailchimpFields(); // Initial state
});
</script>
