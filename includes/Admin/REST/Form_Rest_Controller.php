<?php
/**
 * Form AJAX Controller.
 *
 * Handles AJAX requests for conditional field logic evaluation.
 *
 * @package CampaignBridge\Admin\REST
 */

declare( strict_types=1 );

namespace CampaignBridge\Admin\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form AJAX Controller.
 *
 * Handles AJAX requests for conditional field logic evaluation.
 */
class Form_Rest_Controller {

	/**
	 * Rate limiting: maximum requests per window.
	 */
	private const RATE_LIMIT_REQUESTS = 20;


	/**
	 * Handle AJAX request for conditional evaluation
	 *
	 * @return void
	 */
	public function handle_ajax_evaluate_conditions(): void {
		try {
			// Security: Verify user is logged in.
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You must be logged in to access this feature.', 401 );
				return;
			}

			// Security: Verify nonce.
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( empty( $nonce ) ) {
				wp_send_json_error( 'Security check failed: missing nonce.', 403 );
				return;
			}

			$form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
			if ( empty( $form_id ) ) {
				wp_send_json_error( 'Form ID is required.', 400 );
				return;
			}

			// Security: Verify nonce matches the form.
			$nonce_action = 'campaignbridge_form_' . $form_id;
			if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
				wp_send_json_error( 'Security check failed: invalid nonce.', 403 );
				return;
			}

			// Security: Verify user has access to this form.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'You do not have permission to access this form.', 403 );
				return;
			}

			// Security: Rate limiting (20 requests per minute per user).
			$user_id        = get_current_user_id();
			$rate_limit_key = 'conditional_rate_limit_' . $user_id;
			$requests       = (int) \CampaignBridge\Core\Storage::get_transient( $rate_limit_key );
			if ( $requests >= self::RATE_LIMIT_REQUESTS ) {
				wp_send_json_error( 'Rate limit exceeded. Please wait before making another request.', 429 );
				return;
			}
			\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, $requests + 1, MINUTE_IN_SECONDS );

			// Get form data with size limits.
			$form_data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

			// Security: Validate form data size and depth.
			if ( $this->is_form_data_too_large( $form_data ) ) {
				wp_send_json_error( 'Form data is too large.', 400 );
				return;
			}

			// Get the form configuration.
			$form_config = \CampaignBridge\Admin\Core\Form_Registry::get( $form_id );
			if ( ! $form_config ) {
				wp_send_json_error( 'Form configuration not found.', 404 );
				return;
			}

			// Evaluate conditional logic.
			$conditional_manager = new \CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager(
				$form_config->get_fields(),
				$form_data
			);

			$result = array(
				'success' => true,
				'fields'  => $conditional_manager->evaluate_all_fields( $form_id, get_current_user_id() ),
			);

			wp_send_json( $result );

		} catch ( \Throwable $e ) {
			// Log error for debugging but don't expose sensitive information.
			\CampaignBridge\Core\Error_Handler::error(
				'Conditional evaluation error: ' . $e->getMessage(),
				array(
					'user_id' => get_current_user_id(),
					'form_id' => $form_id ?? 'unknown',
				)
			);
			wp_send_json_error( 'An unexpected error occurred. Please try again.', 500 );
		}
	}

	/**
	 * Validate form data size and depth to prevent DoS attacks.
	 *
	 * @param array $data         Form data to validate.
	 * @param int   $max_size     Maximum array size.
	 * @param int   $max_depth    Maximum nesting depth.
	 * @param int   $current_depth Current recursion depth.
	 * @return bool True if data is too large, false otherwise.
	 */
	private function is_form_data_too_large( array $data, int $max_size = 1000, int $max_depth = 5, int $current_depth = 0 ): bool {
		// Prevent infinite recursion.
		if ( $current_depth > $max_depth ) {
			return true;
		}

		// Check array size.
		if ( count( $data ) > $max_size ) {
			return true;
		}

		// Recursively check nested arrays.
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				if ( $this->is_form_data_too_large( $value, $max_size, $max_depth, $current_depth + 1 ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
