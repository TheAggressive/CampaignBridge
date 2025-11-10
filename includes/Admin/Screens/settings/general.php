<?php
/**
 * General Settings Tab - Form System Demo
 *
 * Demonstrates the modern fluent API for form creation
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 *
 * @package CampaignBridge
 */

use CampaignBridge\Admin\Core\Form;

// Create the general settings form using the fluent API.
$form = Form::make( 'general_settings' )
	->table()
	->success( 'Settings saved successfully!' )
	->save_to_options( 'campaignbridge_' ) // Save to options with prefix.
	->text( 'from_name', 'From Name' )
		->default( get_bloginfo( 'name' ) )
		->required()
		->description( 'The name that appears in the "From" field of emails.' )
		->class( 'regular-text' )
		->autocomplete( 'organization' )
		->end()

	->email( 'from_email', 'From Email' )
		->default( \CampaignBridge\Core\Storage::get_option( 'admin_email' ) )
		->required()
		->description( 'The email address that appears in the "From" field.' )
		->class( 'regular-text' )
		->autocomplete( 'email' )
		->end()

	->email( 'reply_to', 'Reply-To Email' )
		->default( \CampaignBridge\Core\Storage::get_option( 'admin_email' ) )
		->description( 'Optional. Email address where replies should be sent.' )
		->class( 'regular-text' )
		->autocomplete( 'email' )
		->end()

		->before_validate(
			function ( $data ) {
				// Ensure from_email is different from reply_to.
				if ( ! empty( $data['reply_to'] ) && $data['from_email'] === $data['reply_to'] ) {
					throw new Exception( esc_html__( 'From Email and Reply-To Email should be different.', 'campaignbridge' ) );
				}
				return $data;
			}
		)
	->after_validate(
		function ( $data, $errors ) {
			if ( ! empty( $errors ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\CampaignBridge\Core\Error_Handler::warning( 'General settings validation failed', array( 'errors' => $errors ) );
			}
		}
	)
	->before_save(
		function ( $data ) {
			$data['updated_at'] = current_time( 'mysql' );
			$data['updated_by'] = get_current_user_id();
			return $data;
		}
	)
	->after_save(
		function ( $data, $result ) {
			if ( $result ) {
				\CampaignBridge\Core\Storage::wp_cache_delete( 'campaignbridge_email_config', 'campaignbridge' );
				\CampaignBridge\Core\Error_Handler::error(
					sprintf(
						'General email settings updated by user %d: %s -> %s',
						get_current_user_id(),
						$data['from_name'],
						$data['from_email']
					)
				);
				do_action( 'campaignbridge_general_settings_saved', $data );
			} else {
				\CampaignBridge\Core\Error_Handler::error( 'Failed to save general settings' );
			}
		}
	);
?>

<div class="general-settings-tab">
	<h2>General Email Settings</h2>
	<p class="description">
		Configure the default sender information for your email campaigns.
	</p>

	<?php $form->render(); ?>

	<div class="form-builder-info">
		<h3><?php esc_html_e( '✨ Form Builder Benefits', 'campaignbridge' ); ?></h3>
		<ul>
			<li><?php esc_html_e( '✅ Automatic security (nonces, sanitization, validation)', 'campaignbridge' ); ?></li>
			<li><?php esc_html_e( '✅ Accessibility compliant (ARIA labels, keyboard navigation)', 'campaignbridge' ); ?></li>
			<li><?php esc_html_e( '✅ Lifecycle hooks for extensibility', 'campaignbridge' ); ?></li>
			<li><?php esc_html_e( '✅ Automatic data loading and saving', 'campaignbridge' ); ?></li>
			<li><?php esc_html_e( '✅ Built-in error handling and user feedback', 'campaignbridge' ); ?></li>
		</ul>
		<p><strong><?php esc_html_e( 'Before:', 'campaignbridge' ); ?></strong> <?php esc_html_e( '~100 lines of manual form handling code', 'campaignbridge' ); ?></p>
		<p><strong>After:</strong> <?php esc_html_e( '~50 lines of declarative configuration', 'campaignbridge' ); ?></p>
	</div>
</div>

<style>
.general-settings-tab {
	background: white;
	padding: 20px;
	margin-top: 20px;
	border: 1px solid #ddd;
}

.form-builder-info {
	background: #f8f9fa;
	padding: 20px;
	margin-top: 30px;
	border: 1px solid #dee2e6;
	border-radius: 4px;
}

.form-builder-info h3 {
	margin-top: 0;
	color: #007cba;
}

.form-builder-info ul {
	margin: 15px 0;
	padding-left: 20px;
}

.form-builder-info li {
	margin-bottom: 5px;
}

.form-builder-info p {
	margin: 10px 0;
	font-size: 14px;
}

.form-builder-info strong {
	color: #007cba;
}
</style>
