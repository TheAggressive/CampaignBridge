<?php
/**
 * Form Handler - Handles form submission logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form;
use CampaignBridge\Admin\Core\Forms\Form_Field_File;

/**
 * Form Handler - Handles form submission logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Handler {

	/**
	 * Parent form instance
	 *
	 * @var Form|null
	 */
	private ?Form $form;

	/**
	 * Form configuration
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

	/**
	 * Form fields configuration
	 *
	 * @var array<string, mixed>
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
	 * Notice handler
	 *
	 * @var Form_Notice_Handler
	 */
	private Form_Notice_Handler $notice_handler;

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
	 * @var array<int|string, mixed>
	 */
	public array $errors = array();

	/**
	 * Field-specific errors
	 *
	 * @var array<int|string, mixed>
	 */
	private array $field_errors = array();

	/**
	 * Form messages
	 *
	 * @var array<int|string, mixed>
	 */
	public array $messages = array();

	/**
	 * Constructor
	 *
	 * @param Form|null            $form           Parent form instance.
	 * @param Form_Config          $config         Form configuration.
	 * @param array<string, mixed> $fields Form fields configuration.
	 * @param Form_Security        $security       Security handler.
	 * @param Form_Validator       $validator      Form validator.
	 * @param Form_Notice_Handler  $notice_handler Notice handler.
	 */
	public function __construct(
		?Form $form,
		Form_Config $config,
		array $fields,
		Form_Security $security,
		Form_Validator $validator,
		Form_Notice_Handler $notice_handler
	) {
		$this->form           = $form;
		$this->config         = $config;
		$this->fields         = $fields;
		$this->security       = $security;
		$this->validator      = $validator;
		$this->notice_handler = $notice_handler;
	}

	/**
	 * Handle form submission
	 *
	 * Processes form submission, validates data, and saves if valid.
	 * Only processes if the correct form was submitted via the expected method.
	 */
	public function handle_submission(): void {
		$form_id        = $this->config->get( 'form_id', 'form' );
		$method         = strtoupper( $this->config->get( 'method', 'POST' ) );
		$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );

		if ( $request_method !== $method ) {
			return;
		}

		// Check if this specific form was submitted by checking for its nonce field.
		$nonce_name = $form_id . '_wpnonce';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately after this check.
		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			// This form was not submitted, skip processing.
			return;
		}

		$this->is_submitted = true;

		// Verify security.
		if ( ! $this->security->verify_request() ) {
			$this->errors[] = \__( 'Security check failed. Please try again.', 'campaignbridge' );
			return;
		}

		// Get submitted data.
		$form_data = $this->get_submitted_data();

		// Run before validation hook.
		$this->run_hook( 'before_validate', $form_data );

		// Validate.
		$validation_result = $this->validator->validate( $form_data, $this->fields );

		if ( ! $validation_result['valid'] ) {
			$this->errors       = array_merge( $this->errors, $validation_result['errors'] );
			$this->field_errors = $validation_result['errors']; // Field-specific errors keyed by field ID.
			$this->run_hook( 'after_validate', $form_data, $validation_result['errors'] );
			return;
		}

		$this->is_valid = true;

		// Run before save hook.
		$this->run_hook( 'before_save', $form_data );

		// Save data.
		$save_result = $this->save_data( $form_data );

		// Run after save hook.
		$this->run_hook( 'after_save', $form_data, $save_result );

		if ( $save_result ) {
			// Reload form data to reflect saved changes immediately.
			if ( $this->form ) {
				$this->form->reload_data();
			}

			$success_message  = $this->config->get( 'success_message', \__( 'Saved successfully!', 'campaignbridge' ) );
			$this->messages[] = $success_message;

			// Auto-trigger Screen_Context notice.
			$this->notice_handler->trigger_success( $this->config, $form_data );

			// Run success hook.
			$this->run_hook( 'on_success', $form_data );
		} else {
			$error_message  = $this->config->get( 'error_message', \__( 'An error occurred.', 'campaignbridge' ) );
			$this->errors[] = $error_message;

			// Auto-trigger Screen_Context notice.
			$this->notice_handler->trigger_error( $this->config, $form_data );

			// Run error hook.
			$this->run_hook( 'on_error', $form_data );
		}
	}

	/**
	 * Get submitted form data
	 *
	 * @return array<string, mixed>
	 */
	private function get_submitted_data(): array {
		$data    = array();
		$method  = strtoupper( $this->config->get( 'method', 'POST' ) );
		$form_id = $this->config->get( 'form_id', 'form' );

		// Get form data from the namespaced array.

    // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce already verified in handle_submission(), data sanitized per field via sanitize_field_value().
		$form_data = array();
		if ( 'POST' === $method ) {
			$form_data = \wp_unslash( $_POST[ $form_id ] ?? array() );
		} elseif ( 'GET' === $method ) {
			$form_data = \wp_unslash( $_GET[ $form_id ] ?? array() );
		}

		// Extract field values, handling array-style field names.
		$data = array();

		foreach ( $this->fields as $field_id => $field_config ) {
			$value = $form_data[ $field_id ] ?? null;

			// Handle file uploads first (they don't have POST values, but may have form data).
			$field_type = $field_config['type'] ?? 'text';
			if ( 'file' === $field_type ) {
				$value = $this->process_file_upload( $field_id, $field_config );

				// Store the processed file value.
				if ( isset( $value ) ) {
					$data[ $field_id ] = $value;
				}
			} elseif ( null !== $value ) {
				// Handle repeater field names with ___ separator (field_id___key).
				if ( strpos( $field_id, '___' ) !== false ) {
					list( $base_name, $key ) = explode( '___', $field_id, 2 );

					// Initialize array if not exists.
					if ( ! isset( $data[ $base_name ] ) ) {
						$data[ $base_name ] = array();
					}

					// For multiple selections, only include checked values.
					if ( $this->is_multiple_field_checked( $value, $field_config ) ) {
						// For multiple fields, store the key (e.g., 'post') not the value ('1').
						$data[ $base_name ][] = sanitize_key( $key );
					}
				} else {
					// Regular field.
					$data[ $field_id ] = $this->sanitize_field_value( $value, $field_config );
				}
			}
		}

		return $data;
	}

	/**
	 * Process file upload for a field
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return mixed Processed file data or null.
	 */
	private function process_file_upload( string $field_id, array $field_config ) {
		// Security: This method should only be called after nonce verification in handle_submission().
		if ( ! $this->is_submitted ) {
			return null;
		}

		// Get form ID to handle nested $_FILES structure.
		$form_id = $this->config->get( 'form_id', 'form' );

		// Strip [] from field name for $_FILES lookup.
		$files_field_id = rtrim( $field_id, '[]' );

		$file_data = null;

		// Handle nested $_FILES structure (form_id[field_name]).
		if ( isset( $_FILES[ $form_id ] ) && isset( $_FILES[ $form_id ]['name'][ $files_field_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission() before calling this method

			// Extract the nested file data.
			$nested_data = $_FILES[ $form_id ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission() before calling this method
			$file_data   = array(
				'name'     => $nested_data['name'][ $files_field_id ] ?? '',
				'type'     => $nested_data['type'][ $files_field_id ] ?? '',
				'tmp_name' => $nested_data['tmp_name'][ $files_field_id ] ?? '',
				'error'    => $nested_data['error'][ $files_field_id ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $nested_data['size'][ $files_field_id ] ?? 0,
			);
		} elseif ( isset( $_FILES[ $files_field_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission() before calling this method
			$file_data = $_FILES[ $files_field_id ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission() before calling this method
		} else {
			return null;
		}

		// Check if files were uploaded.
		if ( empty( $file_data ) || empty( $file_data['name'] ) || UPLOAD_ERR_NO_FILE === $file_data['error'] ) {
			return null;
		}

		// Handle multiple files.
		if ( is_array( $file_data['name'] ) ) {
			$file_uploader = new Form_File_Uploader();
			$upload_result = $file_uploader->process_multiple_uploads( $file_data, $field_config );

			if ( is_wp_error( $upload_result ) ) {
				$this->errors[] = $upload_result->get_error_message();
				return null;
			}

			return $upload_result;
		} else {
			// Single file.
			$file_uploader = new Form_File_Uploader();
			$upload_result = $file_uploader->process_upload( $file_data, $field_config );

			if ( is_wp_error( $upload_result ) ) {
				$this->errors[] = $upload_result->get_error_message();
				return null;
			}

			// Store upload data in field for later access.
			$field_instance = $this->create_field_instance( $field_id, $field_config, $upload_result );
			if ( $field_instance instanceof Form_Field_File ) {
				$field_instance->set_upload_data( $upload_result );
			}

			return $upload_result;
		}
	}

	/**
	 * Create field instance for processing
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param mixed                $value        Field value.
	 * @return Form_Field_Interface|null Field instance.
	 */
	private function create_field_instance( string $field_id, array $field_config, $value ): ?Form_Field_Interface {
		try {
			$factory = new Form_Field_Factory();
			return $factory->create_field( $field_id, $field_config, $value );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Check if a multiple field value should be included.
	 *
	 * @param mixed                $value       Field value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return bool Whether to include this value.
	 */
	private function is_multiple_field_checked( $value, array $field_config ): bool {
		$type = $field_config['type'] ?? 'text';

		// For switches and checkboxes, only include if checked.
		if ( in_array( $type, array( 'switch', 'checkbox' ), true ) ) {
			return '1' === $value || 1 === $value || true === $value;
		}

		// For other types, include if not empty.
		return ! empty( $value );
	}

	/**
	 * Sanitize field value based on type
	 *
	 * @param mixed                $value       Raw value.
	 * @param array<string, mixed> $field_config Field configuration.
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
	 * @param array<string, mixed> $data Form data to save.
	 * @return bool Success status.
	 */
	private function save_data( array $data ): bool {
		$save_method = $this->config->get( 'save_method', 'options' );

		switch ( $save_method ) {
			case 'options':
				return $this->save_to_options( $data );
			case 'post_meta':
				return $this->save_to_post_meta( $data );
			case 'settings':
				return $this->save_to_settings( $data );
			case 'custom':
				return $this->save_to_custom( $data );
			default:
				return false;
		}
	}

	/**
	 * Save to WordPress options
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_options( array $data ): bool {
		foreach ( $data as $field_id => $value ) {
			$option_key = $this->config->get( 'prefix', '' ) . $field_id . $this->config->get( 'suffix', '' );
			\CampaignBridge\Core\Storage::update_option( $option_key, $value );

			// Clear cache for this specific option.
			\CampaignBridge\Core\Storage::wp_cache_delete( $option_key, 'options' );
		}

		return true;
	}

	/**
	 * Save using WordPress Settings API
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_settings( array $data ): bool {
		$settings_group = $this->config->get( 'settings_group', $this->config->get( 'form_id', 'settings' ) );

		// Register the setting if not already registered.
		if ( ! get_registered_settings()[ $settings_group ] ) {
			\register_setting(
				$settings_group,
				$settings_group,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize_settings_data' ),
					'default'           => array(),
				)
			);
		}

		// For Settings API, we save the entire data array as one option.
		// This mimics how WordPress Settings API typically works.
		$result = \CampaignBridge\Core\Storage::update_option( $settings_group, $data );

		// Clear cache for this settings group.
		\CampaignBridge\Core\Storage::wp_cache_delete( $settings_group, 'options' );

		return $result;
	}

	/**
	 * Sanitize settings data
	 *
	 * @param array<string, mixed> $data Raw settings data.
	 * @return array<string, mixed> Sanitized settings data.
	 */
	public function sanitize_settings_data( array $data ): array {

		$sanitized = array();
		foreach ( $data as $key => $value ) {
			$field_config      = $this->fields[ $key ] ?? array();
			$sanitized[ $key ] = $this->sanitize_field_value( $value, $field_config );
		}

		return $sanitized;
	}

	/**
	 * Save to post meta
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_post_meta( array $data ): bool {
		$post_id = $this->config->get( 'post_id', \get_the_ID() );

		foreach ( $data as $field_id => $value ) {
			\CampaignBridge\Core\Storage::update_post_meta( $post_id, $field_id, $value );

			// Clear cache for this post meta.
			\CampaignBridge\Core\Storage::wp_cache_delete( $post_id, 'post_meta' );
		}
		return true;
	}

	/**
	 * Save to custom handler
	 *
	 * @param array<string, mixed> $data Data to save.
	 * @return bool Success.
	 */
	private function save_to_custom( array $data ): bool {
		$hooks = $this->config->get( 'hooks', array() );
		if ( isset( $hooks['save_data'] ) && is_callable( $hooks['save_data'] ) ) {
			return (bool) call_user_func( $hooks['save_data'], $data );
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
		$hooks = $this->config->get( 'hooks', array() );
		if ( isset( $hooks[ $hook_name ] ) && is_callable( $hooks[ $hook_name ] ) ) {
			call_user_func( $hooks[ $hook_name ], ...$args );
		}
	}

	/**
	 * Check if form was submitted
	 *
	 * @return bool True if form was submitted.
	 */
	public function is_submitted(): bool {
		return $this->is_submitted;
	}

	/**
	 * Check if form is valid
	 *
	 * @return bool True if form validation passed.
	 */
	public function is_valid(): bool {
		return $this->is_valid;
	}

	/**
	 * Get form errors
	 *
	 * @return array<int|string, mixed> Array of validation errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get field-specific errors
	 *
	 * @return array<int|string, mixed> Array of field-specific errors.
	 */
	public function get_field_errors(): array {
		return $this->field_errors;
	}

	/**
	 * Get form success messages
	 *
	 * @return array<int|string, mixed> Array of success messages.
	 */
	public function get_messages(): array {
		return $this->messages;
	}
}
