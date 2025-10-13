<?php
/**
 * General Settings Tab - Form System Demo
 *
 * Demonstrates the modern fluent API for form creation
 * Auto-discovered as part of Settings screen
 * Controller: Settings_Controller (auto-discovered)
 */

// Include the Form system
require_once __DIR__ . '/../../Core/Form.php';

// Create the general settings form using the fluent API
$form = \CampaignBridge\Admin\Core\Form::make( 'general_settings' )
	->table()
	->success( 'Settings saved successfully!' )
	->save_to_options( 'cb_' ) // Save to options with prefix
	->text( 'from_name', 'From Name' )
		->default( get_bloginfo( 'name' ) )
		->required()
		->description( 'The name that appears in the "From" field of emails.' )
		->class( 'regular-text' )
		->autocomplete( 'organization' )
		->end()

	->email( 'from_email', 'From Email' )
		->default( get_option( 'admin_email' ) )
		->required()
		->description( 'The email address that appears in the "From" field.' )
		->class( 'regular-text' )
		->autocomplete( 'email' )
		->end()

	->email( 'reply_to', 'Reply-To Email' )
		->default( get_option( 'admin_email' ) )
		->description( 'Optional. Email address where replies should be sent.' )
		->class( 'regular-text' )
		->autocomplete( 'email' )
		->end()

	->before_validate(
		function ( $data ) {
			// Ensure from_email is different from reply_to
			if ( ! empty( $data['reply_to'] ) && $data['from_email'] === $data['reply_to'] ) {
				throw new Exception( __( 'From Email and Reply-To Email should be different.', 'campaignbridge' ) );
			}
			return $data;
		}
	)
	->after_validate(
		function ( $data, $errors ) {
			if ( ! empty( $errors ) ) {
				error_log( 'General settings validation failed: ' . print_r( $errors, true ) );
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
				die();
				wp_cache_delete( 'campaignbridge_email_config', 'campaignbridge' );
				error_log(
					sprintf(
						'General email settings updated by user %d: %s -> %s',
						get_current_user_id(),
						$data['from_name'],
						$data['from_email']
					)
				);
				do_action( 'campaignbridge_general_settings_saved', $data );
			} else {
				error_log( 'Failed to save general settings' );
			}
		}
	);

// Use Screen Context for displaying form messages (automatic)
$form->use_screen_notices( $screen );
?>

<div class="general-settings-tab">
	<h2><?php _e( 'General Email Settings', 'campaignbridge' ); ?></h2>
	<p class="description">
		<?php _e( 'Configure the default sender information for your email campaigns.', 'campaignbridge' ); ?>
	</p>

	<?php $form->render(); ?>

	<div class="form-builder-info">
		<h3><?php _e( '✨ Form Builder Benefits', 'campaignbridge' ); ?></h3>
		<ul>
			<li><?php _e( '✅ Automatic security (nonces, sanitization, validation)', 'campaignbridge' ); ?></li>
			<li><?php _e( '✅ Accessibility compliant (ARIA labels, keyboard navigation)', 'campaignbridge' ); ?></li>
			<li><?php _e( '✅ Lifecycle hooks for extensibility', 'campaignbridge' ); ?></li>
			<li><?php _e( '✅ Automatic data loading and saving', 'campaignbridge' ); ?></li>
			<li><?php _e( '✅ Built-in error handling and user feedback', 'campaignbridge' ); ?></li>
		</ul>
		<p><strong><?php _e( 'Before:', 'campaignbridge' ); ?></strong> <?php _e( '~100 lines of manual form handling code', 'campaignbridge' ); ?></p>
		<p><strong><?php _e( 'After:', 'campaignbridge' ); ?></strong> <?php _e( '~50 lines of declarative configuration', 'campaignbridge' ); ?></p>
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
