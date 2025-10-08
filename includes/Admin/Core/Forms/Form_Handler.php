<?php
/**
 * Form Handler - Handles form submission logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form;

/**
 * Form Handler - Handles form submission logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Handler {

	/**
	 * Parent form instance
	 *
	 * @var Form
	 */
	private Form $form;

	/**
	 * Form configuration
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Form fields configuration
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * Security instance
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Validator instance
	 *
	 * @var Form_Validator
	 */
	private Form_Validator $validator;

	/**
	 * Whether form was submitted
	 *
	 * @var bool
	 */
	private bool $is_submitted = false;

	/**
	 * Whether form is valid
	 *
	 * @var bool
	 */
	private bool $is_valid = false;

	/**
	 * Form errors
	 *
	 * @var array
	 */
	private array $errors = [];

	/**
	 * Form messages
	 *
	 * @var array
	 */
	private array $messages = [];

	/**
	 * Constructor
	 *
	 * @param Form           $form     Parent form instance.
	 * @param array          $config   Form configuration.
	 * @param array          $fields   Form fields.
	 * @param Form_Security  $security Security instance.
	 * @param Form_Validator $validator Validator instance.
	 */
	public function __construct(
		Form $form,
		array $config,
		array $fields,
		Form_Security $security,
		Form_Validator $validator
	) {
		$this->form      = $form;
		$this->config    = $config;
		$this->fields    = $fields;
		$this->security  = $security;
		$this->validator = $validator;
	}

	/**
	 * Handle form submission
	 */
	public function handle_submission(): void {
		$method = strtoupper( $this->config['method'] );

		if ( $method !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$this->is_submitted = true;

		// Verify security
		if ( ! $this->security->verify_request() ) {
			$this->errors[] = \__( 'Security check failed. Please try again.', 'campaignbridge' );
			return;
		}

		// Get submitted data
		$form_data = $this->get_submitted_data();

		// Run before validation hook
		$this->run_hook( 'before_validate', $form_data );

		// Validate
		$validation_result = $this->validator->validate( $form_data, $this->fields );

		if ( ! $validation_result['valid'] ) {
			$this->errors = array_merge( $this->errors, $validation_result['errors'] );
			$this->run_hook( 'after_validate', $form_data, $validation_result['errors'] );
			return;
		}

		$this->is_valid = true;

		// Run before save hook
		$this->run_hook( 'before_save', $form_data );

		// Save data
		$save_result = $this->save_data( $form_data );

		// Run after save hook
		$this->run_hook( 'after_save', $form_data, $save_result );

		if ( $save_result ) {
			$this->messages[] = $this->config['success_message'] ?? \__( 'Settings saved successfully!', 'campaignbridge' );

			// Run success hook
			$this->run_hook( 'on_success', $form_data );
		} else {
			$this->errors[] = $this->config['error_message'] ?? \__( 'Failed to save settings.', 'campaignbridge' );

			// Run error hook
			$this->run_hook( 'on_error', $form_data );
		}
	}

	/**
	 * Get submitted form data
	 *
	 * @return array
	 */
	private function get_submitted_data(): array {
		$data    = [];
		$method  = strtoupper( $this->config['method'] );
		$form_id = $this->config['form_id'] ?? 'form';

		// Get form data from the namespaced array
		$form_data = [];
		if ( $method === 'POST' ) {
			$form_data = \wp_unslash( $_POST[ $form_id ] ?? [] );
		} elseif ( $method === 'GET' ) {
			$form_data = \wp_unslash( $_GET[ $form_id ] ?? [] );
		}

		// Extract field values
		foreach ( $this->fields as $field_id => $field_config ) {
			$value = $form_data[ $field_id ] ?? null;

			if ( $value !== null ) {
				$data[ $field_id ] = $this->sanitize_field_value( $value, $field_config );
			}
		}

		return $data;
	}

	/**
	 * Sanitize field value based on type
	 *
	 * @param mixed $value       Raw value.
	 * @param array $field_config Field configuration.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field_value( $value, array $field_config ) {
		$type = $field_config['type'] ?? 'text';

		switch ( $type ) {
			case 'email':
				return \sanitize_email( $value );
			case 'url':
				return \esc_url_raw( $value );
			case 'textarea':
			case 'wysiwyg':
				return \wp_kses_post( $value );
			case 'number':
				return \is_numeric( $value ) ? (float) $value : 0;
			case 'checkbox':
				return \is_array( $value ) ? array_map( '\sanitize_text_field', $value ) : (bool) $value;
			default:
				return \sanitize_text_field( $value );
		}
	}

	/**
	 * Save form data
	 *
	 * @param array $data Form data to save.
	 * @return bool Success status.
	 */
	private function save_data( array $data ): bool {
		$save_method = $this->config['save_method'] ?? 'options';

		switch ( $save_method ) {
			case 'options':
				return $this->save_to_options( $data );
			case 'post_meta':
				return $this->save_to_post_meta( $data );
			case 'custom':
				return $this->save_to_custom( $data );
			default:
				return false;
		}
	}

	/**
	 * Save to WordPress options
	 *
	 * @param array $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_options( array $data ): bool {
		foreach ( $data as $field_id => $value ) {
			$option_key = $this->config['prefix'] . $field_id . $this->config['suffix'];
			\update_option( $option_key, $value );
		}
		return true;
	}

	/**
	 * Save to post meta
	 *
	 * @param array $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_post_meta( array $data ): bool {
		$post_id = $this->config['post_id'] ?? \get_the_ID();

		foreach ( $data as $field_id => $value ) {
			\update_post_meta( $post_id, $field_id, $value );
		}
		return true;
	}

	/**
	 * Save to custom handler
	 *
	 * @param array $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_custom( array $data ): bool {
		if ( isset( $this->config['hooks']['save_data'] ) && is_callable( $this->config['hooks']['save_data'] ) ) {
			return (bool) call_user_func( $this->config['hooks']['save_data'], $data );
		}
		return false;
	}

	/**
	 * Run a hook if it exists
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  ...$args   Arguments to pass to hook.
	 */
	private function run_hook( string $hook_name, ...$args ): void {
		if ( isset( $this->config['hooks'][ $hook_name ] ) && is_callable( $this->config['hooks'][ $hook_name ] ) ) {
			call_user_func( $this->config['hooks'][ $hook_name ], ...$args );
		}
	}

	/**
	 * Check if form was submitted
	 *
	 * @return bool
	 */
	public function is_submitted(): bool {
		return $this->is_submitted;
	}

	/**
	 * Check if form is valid
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return $this->is_valid;
	}

	/**
	 * Get form errors
	 *
	 * @return array
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get form success messages
	 *
	 * @return array
	 */
	public function get_messages(): array {
		return $this->messages;
	}
}
