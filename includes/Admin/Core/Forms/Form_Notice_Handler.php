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
		add_settings_error( 'campaignbridge_form', 'success', wp_kses( $message, array() ), 'success' );
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
					// Sanitize error message to prevent XSS.
					$sanitized_message = wp_kses(
						$error_message,
						array(
							'strong' => array(),
							'em'     => array(),
							'code'   => array(),
						)
					);

					if ( 'unused_fields' === $field_id ) {
						add_settings_error( 'campaignbridge_form', $field_id, $sanitized_message, 'warning' );
					} else {
						add_settings_error( 'campaignbridge_form', $field_id, $sanitized_message, 'error' );
					}
				}
			}
		} else {
			$message = $config->get( 'error_message', \__( 'An error occurred.', 'campaignbridge' ) );
			add_settings_error( 'campaignbridge_form', 'error', wp_kses( $message, array() ), 'error' );
		}
	}

	/**
	 * Trigger a warning notice
	 *
	 * @param Form_Config $config  Form configuration.
	 * @param string      $message The warning message to display.
	 */
	public function trigger_warning( Form_Config $config, string $message ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Sanitize warning message to prevent XSS.
		$sanitized_message = wp_kses(
			$message,
			array(
				'strong' => array(),
				'em'     => array(),
				'code'   => array(),
			)
		);

		add_settings_error( 'campaignbridge_form', 'warning', $sanitized_message, 'warning' );
	}
}
