<?php
/**
 * Repeater Test Screen
 *
 * Demonstrates repeater functionality and form field repetition.
 *
 * @package CampaignBridge\Admin\Screens
 */

// Include the form system.
use CampaignBridge\Admin\Core\Form;

?>

<div class="wrap campaignbridge-repeater-test">

	<div class="repeater-demo-section">
		<h2><?php esc_html_e( 'Repeater Field Demo', 'campaignbridge' ); ?></h2>
		<p><?php esc_html_e( 'This demonstrates repeater functionality for dynamic form fields.', 'campaignbridge' ); ?></p>

		<?php
		$form = Form::make( 'repeater_test' )
			->text( 'title', 'Form Title' )->required()->end()
			->repeater(
				'features',
				array(
					'newsletter' => 'Subscribe to Newsletter',
					'updates'    => 'Receive Updates',
					'promotions' => 'Receive Promotions',
				)
			)->switch()
			->save_to_options( 'repeater_test_' )
			->success( 'Repeater test saved successfully!' )
			->submit( 'Save Repeater Test' );

		$form->render();
		?>
	</div>

	<div class="repeater-info-section">
		<h2><?php esc_html_e( 'Repeater Information', 'campaignbridge' ); ?></h2>
		<p><?php esc_html_e( 'This screen tests the repeater field functionality.', 'campaignbridge' ); ?></p>
	</div>
</div>
