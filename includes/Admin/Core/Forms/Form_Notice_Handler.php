<?php
/**
 * Form Notice Handler - Handles form success/error notices
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Notice Handler - Delegates to the global Notices system
 *
 * This class provides a bridge between form-specific notice handling and the
 * global CampaignBridge Notices system for consistent user feedback.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Notice_Handler {

	/**
	 * Trigger a success notice
	 *
	 * @param Form_Config          $config Form configuration.
	 * @param array<string, mixed> $data   Form data.
	 */
	public function trigger_success( Form_Config $config, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$message = $config->get( 'success_message', \__( 'Saved successfully!', 'campaignbridge' ) );
		add_settings_error( 'campaignbridge_form', 'success', $message, 'success' );
	}

	/**
	 * Trigger an error notice
	 *
	 * @param Form_Config          $config Form configuration.
	 * @param array<string, mixed> $errors Form errors.
	 */
	public function trigger_error( Form_Config $config, array $errors ): void {
		if ( is_array( $errors ) ) {
			foreach ( $errors as $field_id => $error_message ) {
				if ( is_string( $error_message ) ) {
					if ( 'unused_fields' === $field_id ) {
						add_settings_error( 'campaignbridge_form', $field_id, $error_message, 'warning' );
					} else {
						add_settings_error( 'campaignbridge_form', $field_id, $error_message, 'error' );
					}
				}
			}
		} else {
			$message = $config->get( 'error_message', \__( 'An error occurred.', 'campaignbridge' ) );
			add_settings_error( 'campaignbridge_form', 'error', $message, 'error' );
		}
	}

	/**
	 * Trigger a warning notice
	 *
	 * @param string $message The warning message to display.
	 */
	public function trigger_warning( string $message ): void {
		\add_settings_error( 'campaignbridge_form', 'warning', $message, 'warning' );
	}
}
