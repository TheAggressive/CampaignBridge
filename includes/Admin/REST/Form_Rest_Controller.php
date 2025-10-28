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

use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Container;
use WP_REST_Request;
use WP_REST_Response;

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
	 * Form container instance.
	 *
	 * @var Form_Container
	 */
	private Form_Container $container;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->container = new Form_Container();
	}


	/**
	 * Handle AJAX request for conditional evaluation.
	 *
	 * @return void
	 */
	public function handle_ajax_evaluate_conditions(): void {
		try {
			// Security: Verify user is logged in and has permissions.
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'Authentication required', 401 );
				return;
			}

			// Security: Get POST data using filter_input.
			$form_id_input = filter_input( INPUT_POST, 'form_id', FILTER_DEFAULT );
			$nonce_input   = filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT );

			$sanitized_form_id = $form_id_input ? sanitize_text_field( wp_unslash( $form_id_input ) ) : '';
			$sanitized_nonce   = $nonce_input ? sanitize_text_field( wp_unslash( $nonce_input ) ) : '';

			if ( empty( $sanitized_form_id ) || empty( $sanitized_nonce ) ) {
				wp_send_json_error( 'Missing required data', 400 );
				return;
			}

			$form_id = $sanitized_form_id;

			// Security: Verify nonce with specific action.
			$nonce_action = 'campaignbridge_form_' . $form_id;
			if ( ! wp_verify_nonce( $sanitized_nonce, $nonce_action ) ) {
				wp_send_json_error( 'Security check failed', 403 );
				return;
			}

			// Security: Get and validate form data.
			$form_data_input = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$form_data       = $form_data_input ? wp_unslash( $form_data_input ) : array();

			if ( ! is_array( $form_data ) ) {
				$form_data = array();
			}

			// Security: Rate limiting - max 20 requests per minute per user.
			$rate_limit_key = 'conditional_eval_' . get_current_user_id();
			$requests       = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key ) ? \CampaignBridge\Core\Storage::get_transient( $rate_limit_key ) : 0;

			if ( $requests >= 20 ) {
				// Log rate limit violation for security monitoring.
				\CampaignBridge\Core\Error_Handler::warning(
					'Rate limit exceeded for conditional evaluation',
					array(
						'user_id'  => get_current_user_id(),
						'user_ip'  => \CampaignBridge\Admin\Core\Forms\Form_Security::get_client_ip(),
						'requests' => $requests,
					)
				);
				wp_send_json_error( 'Too many requests. Please wait before trying again.', 429 );
				return;
			}

			\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, $requests + 1, 60 ); // 1 minute

			// Security: Validate and sanitize form data.
			$sanitized_data = array();
			foreach ( $form_data as $key => $value ) {
				// Security: Only allow alphanumeric keys with underscores and dashes.
				if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) ) {
					continue; // Skip invalid keys.
				}

				// Security: Sanitize based on expected data types.
				if ( is_array( $value ) ) {
					$sanitized_data[ $key ] = array_map( 'sanitize_text_field', $value );
				} else {
					$sanitized_data[ $key ] = sanitize_text_field( $value );
				}
			}
			$form_data = $sanitized_data;

			// Security: Validate form_id format.
			if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $form_id ) || strlen( $form_id ) > 100 ) {
				wp_send_json_error( 'Invalid form ID', 400 );
				return;
			}

			// Performance: Cache form config lookups.
			$cache_key   = 'form_config_' . $form_id;
			$form_config = \CampaignBridge\Core\Storage::wp_cache_get( $cache_key, 'campaignbridge' );

			if ( false === $form_config ) {
				$form_config = $this->get_form_config( $form_id );
				if ( $form_config ) {
					\CampaignBridge\Core\Storage::wp_cache_set( $cache_key, $form_config, 'campaignbridge', 300 ); // 5 minutes
				}
			}

			if ( ! $form_config ) {
				wp_send_json_error( 'Form not found', 404 );
				return;
			}

			$fields = $form_config->get_fields();

			// Performance: Cache conditional evaluations for identical form data.
			$data_hash      = md5( wp_json_encode( $form_data ) );
			$eval_cache_key = 'conditional_eval_' . $form_id . '_' . $data_hash;
			$cached_result  = \CampaignBridge\Core\Storage::wp_cache_get( $eval_cache_key, 'campaignbridge' );

			if ( false !== $cached_result ) {
				wp_send_json( $cached_result );
				return;
			}

			$conditional_manager = $this->container->create_form_conditional_manager( $fields, $form_data );

			// Evaluate all field visibility and requirements.
			$result = array(
				'success' => true,
				'fields'  => array(),
			);

			foreach ( $fields as $field_id => $field_config ) {
				$result['fields'][ $field_id ] = array(
					'visible'  => $conditional_manager->should_show_field( $field_id ),
					'required' => $conditional_manager->should_require_field( $field_id ),
				);
			}

			// Cache the result for 30 seconds (short cache for dynamic forms).
			\CampaignBridge\Core\Storage::wp_cache_set( $eval_cache_key, $result, 'campaignbridge', 30 );

			// Security: Add security headers.
			if ( ! headers_sent() ) {
				header( 'X-Content-Type-Options: nosniff' );
				header( 'X-Frame-Options: SAMEORIGIN' );
			}

			wp_send_json( $result );

		} catch ( \Throwable $e ) {
			// Log security-relevant errors.
			\CampaignBridge\Core\Error_Handler::error(
				'Conditional evaluation error: ' . $e->getMessage(),
				array(
					'user_id' => get_current_user_id(),
					'user_ip' => \CampaignBridge\Admin\Core\Forms\Form_Security::get_client_ip(),
					'form_id' => $form_id ?? 'unknown',
				)
			);

			wp_send_json_error( 'Server error occurred. Please try again.', 500 );
		}
	}

	/**
	 * Legacy REST API method for testing compatibility.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response REST response.
	 */
	public function evaluate_conditions( WP_REST_Request $request ): void {
		try {
			$form_id   = $request->get_param( 'form_id' );
			$form_data = $request->get_param( 'data' ) ? $request->get_param( 'data' ) : array();

			if ( ! is_array( $form_data ) ) {
				$form_data = array();
			}

			// Security: Rate limiting - max 20 requests per minute per user.
			$rate_limit_key = 'conditional_eval_' . get_current_user_id();
			$requests       = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key ) ? \CampaignBridge\Core\Storage::get_transient( $rate_limit_key ) : 0;

			if ( $requests >= 20 ) {
				wp_send_json_error( 'Too many requests. Please wait before trying again.', 429 );
				return;
			}

			\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, $requests + 1, 60 ); // 1 minute

			// Security: Validate and sanitize form data.
			$sanitized_data = array();

			// Security: Limit form data size to prevent DoS.
			if ( count( $form_data ) > 100 ) {
				wp_send_json_error( 'Too many form fields.', 400 );
				return;
			}

			foreach ( $form_data as $key => $value ) {
				// Security: Only allow alphanumeric keys with underscores and dashes.
				if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) || strlen( $key ) > 100 ) {
					continue;
				}

				// Security: Validate value types and sizes.
				if ( is_string( $value ) ) {
					if ( strlen( $value ) > 10000 ) { // 10KB max per field
						continue; // Skip oversized values.
					}
					$sanitized_data[ $key ] = sanitize_text_field( $value );
				} elseif ( is_numeric( $value ) ) {
					$sanitized_data[ $key ] = $value; // Numbers are safe.
				} elseif ( is_bool( $value ) ) {
					$sanitized_data[ $key ] = $value; // Booleans are safe.
				} elseif ( is_array( $value ) ) {
					// Security: Arrays should be small and simple.
					if ( count( $value ) > 50 ) {
						continue; // Skip oversized arrays.
					}
					$sanitized_data[ $key ] = array_map( 'sanitize_text_field', $value );
				} else {
					continue; // Skip unsupported types.
				}
			}

			$form_data = $sanitized_data;

			// Security: Validate form ID.
			if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $form_id ) || strlen( $form_id ) > 100 ) {
				wp_send_json_error( 'Invalid form ID', 400 );
				return;
			}

			// Performance: Cache form config lookups.
			$cache_key   = 'form_config_' . $form_id;
			$form_config = \CampaignBridge\Core\Storage::wp_cache_get( $cache_key, 'campaignbridge' );

			if ( false === $form_config ) {
				$form_config = $this->get_form_config( $form_id );
				if ( $form_config ) {
					\CampaignBridge\Core\Storage::wp_cache_set( $cache_key, $form_config, 'campaignbridge', 300 ); // 5 minutes
				}
			}

			if ( ! $form_config ) {
				wp_send_json_error( 'Form not found', 404 );
				return;
			}

			$fields = $form_config->get_fields();

			// Performance: Cache conditional evaluations for identical form data.
			$data_hash      = md5( wp_json_encode( $form_data ) );
			$eval_cache_key = 'conditional_eval_' . $form_id . '_' . $data_hash;
			$cached_result  = \CampaignBridge\Core\Storage::wp_cache_get( $eval_cache_key, 'campaignbridge' );

			if ( false !== $cached_result ) {
				wp_send_json( $cached_result );
				return;
			}

			$conditional_manager = $this->container->create_form_conditional_manager( $fields, $form_data );

			// Evaluate all field visibility and requirements..
			$result = array(
				'success' => true,
				'fields'  => array(),
			);

			foreach ( $fields as $field_id => $field_config ) {
				$result['fields'][ $field_id ] = array(
					'visible'  => $conditional_manager->should_show_field( $field_id ),
					'required' => $conditional_manager->should_require_field( $field_id ),
				);
			}

			// Cache the result for 5 minutes (increased from 30 seconds for better performance).
			\CampaignBridge\Core\Storage::wp_cache_set( $eval_cache_key, $result, 'campaignbridge', 300 );

			wp_send_json( $result );

		} catch ( \Throwable $e ) {
			// Log security-relevant errors..
			\CampaignBridge\Core\Error_Handler::error(
				'Conditional evaluation error: ' . $e->getMessage(),
				array(
					'user_id' => get_current_user_id(),
					'user_ip' => \CampaignBridge\Admin\Core\Forms\Form_Security::get_client_ip(),
					'form_id' => $form_id ?? 'unknown',
				)
			);

			wp_send_json_error( 'Server error occurred. Please try again.', 500 );
		}
	}

	/**
	 * Legacy method for testing - validate form ID.
	 *
	 * @param string $form_id Form ID to validate.
	 * @return bool True if valid.
	 */
	public function validate_form_id( string $form_id ): bool {
		return preg_match( '/^[a-zA-Z0-9_-]+$/', $form_id ) && strlen( $form_id ) <= 100;
	}

	/**
	 * Legacy method for testing - validate form data.
	 *
	 * @param mixed $data Form data to validate.
	 * @return bool True if valid.
	 */
	public function validate_form_data( $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) || strlen( $key ) > 100 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Legacy method for testing - sanitize form data.
	 *
	 * @param array $data Form data to sanitize.
	 * @return array Sanitized data.
	 */
	public function sanitize_form_data( array $data ): array {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) && strlen( $key ) <= 100 ) {
				$sanitized[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}
		return $sanitized;
	}

	/**
	 * Legacy method for testing - check if user can access form.
	 *
	 * @param string $form_id Form ID.
	 * @return bool True if can access.
	 */
	public function can_access_form( string $form_id = '' ): bool {
		return is_user_logged_in() && current_user_can( 'read' );
	}

	/**
	 * Get form configuration by ID.
	 *
	 * @param string $form_id Form ID.
	 * @return Form_Config|null Form configuration or null if not found.
	 */
	private function get_form_config( string $form_id ): ?Form_Config {
		return \CampaignBridge\Admin\Core\Form_Registry::get( $form_id );
	}
}
