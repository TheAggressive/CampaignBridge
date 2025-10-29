<?php
/**
 * Conditional Features Test Screen
 *
 * Comprehensive test page for all conditional form features including:
 * - New fluent API
 * - Security & rate limiting
 * - Caching & performance
 * - Complex conditional logic
 * - Real-time updates
 * - Error handling
 *
 * @package CampaignBridge\Admin\Screens
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the form system.
use CampaignBridge\Admin\Core\Form;

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Conditional Features Test - Simple Version', 'campaignbridge' ); ?></h1>

	<p><?php esc_html_e( 'Testing basic conditional functionality.', 'campaignbridge' ); ?></p>

	<div class="campaignbridge-form-container">
		<?php
		$form = Form::make( 'conditional_features_test' )
			->auto_layout()
			->save_to_options()
			->success( __( 'Test completed!', 'campaignbridge' ) )
			->submit( __( 'Test Conditional Fields', 'campaignbridge' ) );

		// Simple conditional test.
		$form->checkbox( 'enable_test' )
			->label( __( 'Enable Test Field', 'campaignbridge' ) )
			->description( __( 'Check this to show the test field below', 'campaignbridge' ) );

		$form->text( 'test_field' )
			->label( __( 'Conditional Test Field', 'campaignbridge' ) )
			->placeholder( __( 'This field appears when checkbox is checked', 'campaignbridge' ) )
			->show_when(
				array(
					array(
						'field'    => 'enable_test',
						'operator' => 'is_checked',
					),
				)
			)
			->description( __( 'This field is conditionally shown', 'campaignbridge' ) );

		// Render the form.
		$form->render();
		?>
	</div>
</div>
