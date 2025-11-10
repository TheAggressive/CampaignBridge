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
		add_settings_error( 'campaignbridge_form', 'success', wp_kses_post( $message ), 'success' );
	}

	/**
	 * Trigger an error notice
	 *
	 * @param Form_Config          $config Form configuration.
	 * @param array<string, mixed> $errors Form errors.
	 */
	public function trigger_error( Form_Config $config, array $errors ): void {
		if ( empty( $errors ) ) {
			$message = $config->get( 'error_message', \__( 'An error occurred.', 'campaignbridge' ) );
			add_settings_error( 'campaignbridge_form', 'error', wp_kses_post( $message ), 'error' );
			return;
		}

		// Separate validation errors from special error types.
		$validation_errors = array();
		$special_errors    = array();

		foreach ( $errors as $field_id => $error_message ) {
			if ( ! is_string( $error_message ) || empty( $error_message ) ) {
				continue;
			}

			// Handle special error types.
			if ( 'unused_fields' === $field_id ) {
				$special_errors[ $field_id ] = $error_message;
			} else {
				$validation_errors[ $field_id ] = $error_message;
			}
		}

		// Process validation errors as a single combined notice.
		if ( ! empty( $validation_errors ) ) {
			$unique_messages  = array_unique( array_values( $validation_errors ) );
			$combined_message = implode( '<br>', array_map( 'esc_html', $unique_messages ) );

			add_settings_error(
				'campaignbridge_form',
				'form_validation_errors',
				$combined_message,
				'error'
			);
		}

		// Process special errors individually.
		foreach ( $special_errors as $field_id => $error_message ) {
			add_settings_error(
				'campaignbridge_form',
				$field_id,
				wp_kses_post( $error_message ),
				'warning'
			);
		}
	}

	/**
	 * Trigger a warning notice
	 *
	 * @param Form_Config $config  Form configuration.
	 * @param string      $message The warning message to display.
	 */
	public function trigger_warning( Form_Config $config, string $message ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		add_settings_error( 'campaignbridge_form', 'warning', wp_kses_post( $message ), 'warning' );
	}
}
