<?php
/**
 * Manual Form Rendering Example
 *
 * Demonstrates the new manual rendering API with form_start(), render_field(), etc.
 * This gives developers full control over HTML structure while maintaining form functionality.
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the form system.
use CampaignBridge\Admin\Core\Form;

// ============================================================================
// MANUAL RENDERING API EXAMPLE
// ============================================================================

$form = Form::make( 'manual_example' )
	->save_to_options( 'manual_example_' )
	->success( 'Form saved successfully!' )
	->error( 'Please correct the errors below.' )

// Configure all fields upfront
	->text( 'username', 'Username' )->required()->placeholder( 'Enter username' )
	->email( 'email', 'Email Address' )->required()
	->password( 'password', 'Password' )->required()
	->checkbox( 'newsletter', 'Subscribe to newsletter' )
	->select(
		'role',
		'User Role',
		array(
			'user'   => 'User',
			'editor' => 'Editor',
			'admin'  => 'Administrator',
		)
	)
	->textarea( 'bio', 'Biography' )->rows( 3 )->placeholder( 'Tell us about yourself' );

// ============================================================================
// MANUAL HTML RENDERING
// ============================================================================

$form->form_start();
?>

<div class="manual-form-container" style="max-width: 600px; margin: 0 auto; padding: 2rem; background: #f9fafb; border-radius: 8px;">
	<h2 style="color: #1f2937; margin-bottom: 1.5rem;">Create Account</h2>

	<div class="form-section" style="background: white; padding: 1.5rem; border-radius: 6px; margin-bottom: 1rem;">
		<h3 style="margin-top: 0; color: #374151;">Account Details</h3>
		<div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
			<?php $form->render_field( 'username' ); ?>
			<?php $form->render_field( 'email' ); ?>
		</div>
		<?php $form->render_field( 'password' ); ?>
	</div>

	<div class="form-section" style="background: white; padding: 1.5rem; border-radius: 6px; margin-bottom: 1rem;">
		<h3 style="margin-top: 0; color: #374151;">Preferences</h3>
		<div class="form-row" style="margin-bottom: 1rem;">
			<?php $form->render_field( 'role' ); ?>
		</div>
		<div class="form-row">
			<?php $form->render_field( 'newsletter' ); ?>
		</div>
	</div>

	<div class="form-section" style="background: white; padding: 1.5rem; border-radius: 6px; margin-bottom: 2rem;">
		<h3 style="margin-top: 0; color: #374151;">About You</h3>
		<?php $form->render_field( 'bio' ); ?>
	</div>

	<div class="form-actions" style="text-align: center;">
		<?php $form->render_submit(); ?>
	</div>
</div>

<?php
$form->form_end();

// ============================================================================
// DEBUG OUTPUT
// ============================================================================

if ( isset( $_GET['debug'] ) && current_user_can( 'manage_options' ) ) {
	echo '<h3>Debug: Form Data</h3>';
	echo '<pre>' . esc_html( print_r( get_option( 'manual_example_data' ), true ) ) . '</pre>';
}
