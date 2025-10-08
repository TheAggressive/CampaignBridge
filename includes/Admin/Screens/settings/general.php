<?php
/**
 * General Settings Tab
 *
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 */

// Get data from controller or options
$cb_from_name  = $screen->get( 'from_name', get_option( 'cb_from_name', get_bloginfo( 'name' ) ) );
$cb_from_email = $screen->get( 'from_email', get_option( 'cb_from_email', get_option( 'admin_email' ) ) );
$cb_reply_to   = $screen->get( 'reply_to', get_option( 'cb_reply_to', '' ) );

$screen->add_message( 'test' );
// Handle form submission
if ( $screen->is_post() && $screen->verify_nonce( 'save_general' ) ) {
	$from_name  = $screen->post( 'from_name' );
	$from_email = $screen->post( 'from_email' );
	$reply_to   = $screen->post( 'reply_to' );

	// Validate
	$errors = [];
	if ( empty( $from_name ) ) {
		$errors[] = __( 'From Name is required', 'campaignbridge' );
	}
	if ( ! is_email( $from_email ) ) {
		$errors[] = __( 'Valid From Email is required', 'campaignbridge' );
	}
	if ( ! empty( $reply_to ) && ! is_email( $reply_to ) ) {
		$errors[] = __( 'Reply-To must be a valid email', 'campaignbridge' );
	}

	if ( empty( $errors ) ) {
		// Save
		update_option( 'cb_from_name', $from_name );
		update_option( 'cb_from_email', $from_email );
		update_option( 'cb_reply_to', $reply_to );

		$screen->add_message( __( 'General settings saved successfully!', 'campaignbridge' ) );
	} else {
		foreach ( $errors as $error ) {
			$screen->add_error( $error );
		}
	}
}
?>

<div class="general-settings-tab">
	<h2><?php _e( 'General Email Settings', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php _e( 'Configure the default sender information for your email campaigns.', 'campaignbridge' ); ?>
	</p>

	<form method="post" action="">
		<?php $screen->nonce_field( 'save_general' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="from_name">
							<?php _e( 'From Name', 'campaignbridge' ); ?>
							<span class="required">*</span>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="from_name"
							name="from_name"
							value="<?php echo esc_attr( $cb_from_name ); ?>"
							class="regular-text"
							required
						>
						<p class="description">
							<?php _e( 'The name that appears in the "From" field of emails.', 'campaignbridge' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="from_email">
							<?php _e( 'From Email', 'campaignbridge' ); ?>
							<span class="required">*</span>
						</label>
					</th>
					<td>
						<input
							type="email"
							id="from_email"
							name="from_email"
							value="<?php echo esc_attr( $cb_from_email ); ?>"
							class="regular-text"
							required
						>
						<p class="description">
							<?php _e( 'The email address that appears in the "From" field.', 'campaignbridge' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="reply_to">
							<?php _e( 'Reply-To Email', 'campaignbridge' ); ?>
						</label>
					</th>
					<td>
						<input
							type="email"
							id="reply_to"
							name="reply_to"
							value="<?php echo esc_attr( $cb_reply_to ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php _e( 'Optional. Email address where replies should be sent.', 'campaignbridge' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save General Settings', 'campaignbridge' ) ); ?>
	</form>

</div>

<style>
	.general-settings-tab {
		background: white;
		padding: 20px;
		margin-top: 20px;
		border: 1px solid #ddd;
	}

	.required {
		color: #d63638;
		font-weight: bold;
	}
</style>
