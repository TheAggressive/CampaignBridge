<?php
/**
 * Advanced HTML Template Demo
 *
 * Shows conditional sections, tabs, and complex layouts with natural HTML.
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the form system.
use CampaignBridge\Admin\Core\Form;

// ============================================================================
// ADVANCED NATURAL HTML LAYOUT
// ============================================================================

$form = Form::make( 'advanced_profile' )
	->save_to_options( 'advanced_profile_' )
	->success( 'Profile updated successfully!' )
	->error( 'Please correct the errors below.' )
	->before_validate(
		function ( $data ) {
			$errors = array();

			// Test custom validation error
			if ( ! empty( $data['first_name'] ) && strlen( $data['first_name'] ) < 2 ) {
					$errors['first_name'] = 'First name must be at least 2 characters long.';
			}

			// Test another custom validation error
			if ( ! empty( $data['email'] ) && strpos( $data['email'], 'test' ) !== false ) {
				$errors['email'] = 'Test email addresses are not allowed.';
			}

			// If we have errors, throw exception to trigger validation error
			if ( ! empty( $errors ) ) {
				throw new \Exception( 'Custom validation failed: ' . implode( ', ', $errors ) );
			}

			return $data;
		}
	)

// Personal Info Tab.
	->text( 'first_name', 'First Name' )->required()
	->text( 'last_name', 'Last Name' )->required()
	->email( 'email', 'Email Address' )->required()
	->text( 'phone', 'Phone Number' )->required()->pattern( '/^\(\d{3}\) \d{3}-\d{4}$/', 'Please enter phone in format (123) 456-7890' )
	->select(
		'gender',
		'Gender',
		array(
			''                  => 'Select Gender',
			'male'              => 'Male',
			'female'            => 'Female',
			'other'             => 'Other',
			'prefer_not_to_say' => 'Prefer not to say',
		)
	)

// Contact Info Tab.
	->tel( 'phone', 'Phone Number' )
	->text( 'address', 'Street Address' )
	->text( 'city', 'City' )
	->text( 'state', 'State' )
	->text( 'zip', 'ZIP Code' )

// Missing fields for the advanced template.
	->date( 'birth_date', 'Birth Date' )
	->number( 'age', 'Age' )->required()->min( 18 )->max( 120 )

// Preferences Tab.
	->checkbox( 'newsletter', 'Subscribe to newsletter' )
	->checkbox( 'marketing', 'Receive marketing emails' )
	->select(
		'theme',
		'Preferred Theme',
		array(
			'light' => 'Light',
			'dark'  => 'Dark',
			'auto'  => 'Auto',
		)
	)
	->textarea( 'bio', 'Biography' )->rows( 4 )
	// Add a field that will always fail validation to guarantee an error notice
	->text( 'test_error_field', 'Test Error Field' )->required()->pattern( '/^this-will-never-match$/', 'This validation always fails to demonstrate error notices.' );

?>

<?php $form->form_start(); ?>

<div class="advanced-profile">
	<!-- Tab Navigation -->

	<h3 style="color: #1f2937; margin-bottom: 1.5rem; font-size: 1.5rem;">Personal Information</h3>

	<div class="form-grid" style="display: grid; gap: 1.5rem;">
<!-- Name Row -->
	<div class="name-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
		<?php $form->render_field( 'first_name' ); ?>
		<?php $form->render_field( 'last_name' ); ?>
	</div>

	<!-- Contact Info Row -->
	<div class="contact-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
		<?php $form->render_field( 'email' ); ?>
		<?php $form->render_field( 'phone' ); ?>
	</div>

	<!-- Age and Gender Row -->
	<div class="details-row" style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
		<?php $form->render_field( 'age' ); ?>
		<?php $form->render_field( 'gender' ); ?>
	</div>

	<!-- Test Error Field (will always show error) -->
	<div class="error-test-row" style="margin-top: 1rem;">
		<?php $form->render_field( 'test_error_field' ); ?>
	</div>

	</div>

<!-- Form Actions -->
	<div class="form-actions" style="margin-top: 2rem; text-align: center; display: flex; gap: 1rem; justify-content: center;">
	<?php $form->render_submit(); ?>
	</div>
</div>

<?php $form->form_end(); ?>
