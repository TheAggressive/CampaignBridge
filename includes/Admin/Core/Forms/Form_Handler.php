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
	 * Conditional manager instance
	 *
	 * @var Form_Conditional_Manager|null
	 */
	private ?Form_Conditional_Manager $conditional_manager = null;

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
		if ( ! $this->is_form_submitted() ) {
			return;
		}

		$this->is_submitted = true;

		if ( ! $this->verify_security() ) {
			return;
		}

		try {
			$form_data = $this->process_form_data();

			if ( ! $this->validate_form_data( $form_data ) ) {
				return;
			}

			$this->is_valid = true;

			$form_data = $this->prepare_data_for_saving( $form_data );

			if ( $this->save_form_data( $form_data ) ) {
				$this->handle_successful_save( $form_data );
			} else {
				$this->handle_failed_save( $form_data );
			}
		} catch ( \Exception $e ) {
			$this->handle_processing_error( $e );
		}
	}

	/**
	 * Check if this form was submitted
	 *
	 * @return bool True if form was submitted.
	 */
	private function is_form_submitted(): bool {
		$form_id        = $this->config->get( 'form_id', 'form' );
		$method         = strtoupper( $this->config->get( 'method', 'POST' ) );
		$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );

		if ( $request_method !== $method ) {
			return false;
		}

		// Check if this specific form was submitted by checking for its nonce field.
		$nonce_name = $form_id . '_wpnonce';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput -- Nonce presence check before verification.
		return isset( $_POST[ $nonce_name ] );
	}

	/**
	 * Verify form security
	 *
	 * @return bool True if security checks pass.
	 */
	private function verify_security(): bool { // phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput -- Security verification is handled by Form_Security with proper sanitization.
		if ( ! $this->security->verify_request() ) {
			$this->notice_handler->trigger_error(
				$this->config,
				array(
					'security' => \__( 'Security check failed. Please try again.', 'campaignbridge' ),
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Process submitted form data
	 *
	 * @return array<string, mixed> Processed form data.
	 */
	private function process_form_data(): array {
		$form_data = $this->get_submitted_data();

		// Update conditional manager with submitted data for accurate evaluation.
		if ( $this->conditional_manager ) {
			$this->conditional_manager->with_form_data( $form_data );
		}

		return $this->filter_conditional_field_data( $form_data );
	}

	/**
	 * Validate form data
	 *
	 * @param array<string, mixed> $form_data Form data to validate.
	 * @return bool True if validation passes.
	 */
	private function validate_form_data( array $form_data ): bool {
		// Run before validation hook.
		try {
			$form_data = $this->run_hook( 'before_validate', $form_data );
		} catch ( \Exception $e ) {
			$this->notice_handler->trigger_error(
				$this->config,
				array( 'validation' => $e->getMessage() )
			);
			return false;
		}

		// Validate all configured fields.
		$rendered_fields   = $this->form ? $this->form->get_rendered_fields() : array();
		$validation_result = $this->validator->validate_form( $form_data, $this->fields, $rendered_fields );

		if ( ! $validation_result['valid'] ) {
			$this->handle_validation_errors( $validation_result['errors'], $form_data );
			return false;
		}

		return true;
	}

	/**
	 * Handle validation errors
	 *
	 * @param array<string, mixed> $errors    Validation errors.
	 * @param array<string, mixed> $form_data Form data.
	 */
	private function handle_validation_errors( array $errors, array $form_data ): void {
		foreach ( $errors as $field_id => $error_message ) {
			if ( is_string( $error_message ) ) {
				// Handle special case for unused fields error - use warning notice.
				if ( 'unused_fields' === $field_id ) {
					$this->notice_handler->trigger_warning( $this->config, $error_message );
				} else {
					$this->notice_handler->trigger_error( $this->config, array( $field_id => $error_message ) );
				}
			}
		}

		$this->run_hook( 'after_validate', $form_data, $errors );
	}

	/**
	 * Prepare data for saving
	 *
	 * @param array<string, mixed> $form_data Form data.
	 * @return array<string, mixed> Data prepared for saving.
	 */
	private function prepare_data_for_saving( array $form_data ): array {
		// For partial form submissions, merge with existing data.
		$form_data = $this->merge_with_existing_data( $form_data );

		// Run before save hook.
		try {
			$form_data = $this->run_hook( 'before_save', $form_data );
		} catch ( \Exception $e ) {
			throw new \RuntimeException( 'Save preparation failed: ' . esc_html( $e->getMessage() ) );
		}

		return $form_data;
	}

	/**
	 * Save form data
	 *
	 * @param array<string, mixed> $form_data Data to save.
	 * @return bool True if save successful.
	 */
	private function save_form_data( array $form_data ): bool {
		$save_result = $this->save_data( $form_data );
		$this->run_hook( 'after_save', $form_data, $save_result );
		return $save_result;
	}

	/**
	 * Handle successful save
	 *
	 * @param array<string, mixed> $form_data Saved data.
	 */
	private function handle_successful_save( array $form_data ): void {
		// Reload form data to reflect saved changes immediately.
		if ( $this->form ) {
			$this->form->reload_data();
		}

		$this->notice_handler->trigger_success( $this->config, $form_data );
		$this->run_hook( 'on_success', $form_data );
	}

	/**
	 * Handle failed save
	 *
	 * @param array<string, mixed> $form_data Data that failed to save.
	 */
	private function handle_failed_save( array $form_data ): void {
		$this->notice_handler->trigger_error( $this->config, $form_data );
		$this->run_hook( 'on_error', $form_data );
	}

	/**
	 * Handle processing errors
	 *
	 * @param \Exception $e The exception that occurred.
	 */
	private function handle_processing_error( \Exception $e ): void {
		$this->notice_handler->trigger_error(
			$this->config,
			array( 'processing' => $e->getMessage() )
		);
	}

	/**
	 * Merge submitted data with existing data to preserve unchanged fields
	 *
	 * @param array<string, mixed> $submitted_data Submitted form data.
	 * @return array<string, mixed> Merged data.
	 */
	private function merge_with_existing_data( array $submitted_data ): array {
		// Get existing data from the data manager.
		if ( $this->form && method_exists( $this->form, 'data' ) ) {
			// Ensure form data is loaded before trying to access it.
			if ( method_exists( $this->form, 'reload_data' ) ) {
				$this->form->reload_data();
			}

			$existing_data = $this->form->data();

			// Merge submitted data with existing data using field-specific logic.
			$merged_data = array();

			foreach ( $this->fields as $field_id => $field_config ) {
				$submitted_value = $submitted_data[ $field_id ] ?? null;
				$existing_value  = $existing_data[ $field_id ] ?? null;

				// Use field-specific merge logic based on field type.
				$merged_data[ $field_id ] = $this->merge_field_values( $field_id, $submitted_value, $existing_value, $field_config );
			}

			return $merged_data;
		}

		// If no existing data available, just return submitted data.
		return $submitted_data;
	}

	/**
	 * Merge field values using type-specific logic
	 *
	 * @param string               $field_id       Field ID.
	 * @param mixed                $submitted_value Submitted value (null if not submitted).
	 * @param mixed                $existing_value Existing value.
	 * @param array<string, mixed> $field_config   Field configuration.
	 * @return mixed Merged value.
	 */
	private function merge_field_values( string $field_id, $submitted_value, $existing_value, array $field_config ) {
		$field_type = $field_config['type'] ?? 'text';

		// Use static merge methods based on field type.
		switch ( $field_type ) {
			case 'encrypted':
				return $this->merge_encrypted_field_value( $submitted_value, $existing_value );
			case 'checkbox':
			case 'switch':
				return $this->merge_checkbox_field_value( $submitted_value, $existing_value );
			default:
				// Default behavior: submitted value takes priority, fallback to existing.
				return $submitted_value ?? $existing_value;
		}
	}

	/**
	 * Merge encrypted field values
	 *
	 * @param mixed $submitted_value Submitted value.
	 * @param mixed $existing_value  Existing value.
	 * @return mixed Merged value.
	 */
	private function merge_encrypted_field_value( $submitted_value, $existing_value ) {
		// Preserve existing encrypted values if submitted value is empty.
		if ( empty( $submitted_value ) && ! empty( $existing_value ) && \CampaignBridge\Core\Encryption::is_encrypted_value( $existing_value ) ) {
			return $existing_value;
		}

		// Otherwise use default behavior.
		return $submitted_value ?? $existing_value;
	}

	/**
	 * Merge checkbox/switch field values
	 *
	 * @param mixed $submitted_value Submitted value.
	 * @param mixed $existing_value  Existing value.
	 * @return mixed Merged value.
	 */
	private function merge_checkbox_field_value( $submitted_value, $existing_value ) {
		// If checkbox/switch was not submitted, it was unchecked.
		if ( null === $submitted_value ) {
			return false;
		}

		// Otherwise use default behavior.
		return $submitted_value ?? $existing_value;
	}

	/**
	 * Get submitted form data
	 *
	 * @return array<string, mixed>
	 */
	private function get_submitted_data(): array {
		$form_data = $this->get_raw_form_data();
		return $this->process_raw_form_data( $form_data );
	}

	/**
	 * Get raw form data from superglobals
	 *
	 * @return array<string, mixed> Raw form data.
	 *
	 * @phpcs:disable CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput
	 * @SuppressWarnings(PHPMD.Superglobals)
	 */
	private function get_raw_form_data(): array {
		$method  = strtoupper( $this->config->get( 'method', 'POST' ) );
		$form_id = $this->config->get( 'form_id', 'form' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce already verified in handle_submission(), data sanitized per field via sanitize_field_value().
		if ( 'POST' === $method ) {
			return \wp_unslash( $_POST[ $form_id ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing, CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput -- Data sanitized per field in sanitize_field_value(), nonce verified in handle_submission().
		} elseif ( 'GET' === $method ) {
			return \wp_unslash( $_GET[ $form_id ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing, CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput -- Data sanitized per field in sanitize_field_value(), nonce verified in handle_submission().
		}

		return array();
	}

	/**
	 * Process raw form data into structured data
	 *
	 * @param array<string, mixed> $form_data Raw form data.
	 * @return array<string, mixed> Processed form data.
	 */
	private function process_raw_form_data( array $form_data ): array {
		$data = array();

		foreach ( $this->fields as $field_id => $field_config ) { // phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput -- Form data already sanitized per field in sanitize_field_value().
			$value = $form_data[ $field_id ] ?? null;

			if ( $this->is_file_field( $field_config ) ) {
				$data[ $field_id ] = $this->process_file_field( $field_id, $field_config );
			} elseif ( null !== $value ) {
				if ( $this->is_repeater_field( $field_id ) ) {
					$this->process_repeater_field( $field_id, $value, $field_config, $data );
				} else {
					$data[ $field_id ] = $this->sanitize_field_value( $value, $field_config );
				}
			}
		}

		return $data;
	}

	/**
	 * Check if field is a file field
	 *
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return bool True if file field.
	 */
	private function is_file_field( array $field_config ): bool {
		return ( $field_config['type'] ?? 'text' ) === 'file';
	}

	/**
	 * Check if field is a repeater field
	 *
	 * @param string $field_id Field ID.
	 * @return bool True if repeater field.
	 */
	private function is_repeater_field( string $field_id ): bool {
		return strpos( $field_id, '___' ) !== false;
	}

	/**
	 * Process file field
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return mixed Processed file value or null.
	 */
	private function process_file_field( string $field_id, array $field_config ) {
		$value = $this->process_file_upload( $field_id, $field_config );
		return isset( $value ) ? $value : null;
	}

	/**
	 * Process repeater field
	 *
	 * @param string               $field_id     Field ID.
	 * @param mixed                $value        Field value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param array<string, mixed> &$data        Data array to modify.
	 */
	private function process_repeater_field( string $field_id, $value, array $field_config, array &$data ): void {
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
				$this->notice_handler->trigger_error(
					$this->config,
					array(
						'file_upload' => $upload_result->get_error_message(),
					)
				);
				return null;
			}

			return $upload_result;
		} else {
			// Single file.
			$file_uploader = new Form_File_Uploader();
			$upload_result = $file_uploader->process_upload( $file_data, $field_config );

			if ( is_wp_error( $upload_result ) ) {
				$this->notice_handler->trigger_error(
					$this->config,
					array(
						'file_upload' => $upload_result->get_error_message(),
					)
				);
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
			$factory = new Form_Field_Factory( $this->validator );
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
			case 'switch':
				return \is_array( $value ) ? array_map( '\sanitize_text_field', $value ) : (bool) $value;
			case 'encrypted':
				// Encrypt sensitive data before saving to database.
				if ( ! empty( $value ) && ! \CampaignBridge\Core\Encryption::is_encrypted_value( $value ) ) {
					// Security: Reject oversized input to prevent DoS.
					if ( strlen( $value ) > 1000 ) {
						\CampaignBridge\Core\Error_Handler::warning(
							'CampaignBridge: Rejected oversized encrypted field input',
							array( 'input_length' => strlen( $value ) )
						);
						return '';
					}

					try {
						return \CampaignBridge\Core\Encryption::encrypt( $value );
					} catch ( \RuntimeException $e ) {
						// Log error but don't expose details to user.
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							\CampaignBridge\Core\Error_Handler::error(
								'CampaignBridge: Failed to encrypt form field',
								array( 'error' => $e->getMessage() )
							);
						}
						// Return empty string rather than unencrypted data.
						return '';
					}
				}
				// If already encrypted or empty, return as-is.
				return $value;
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
			\CampaignBridge\Core\Storage::update_option( $option_key, $value ); // phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification

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
		$result = \CampaignBridge\Core\Storage::update_option( $settings_group, $data ); // phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Nonce verification handled at form submission level.

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
	 * @return mixed The result of the hook call, or the first argument if no hook.
	 */
	private function run_hook( string $hook_name, ...$args ) {
		$hooks = $this->config->get( 'hooks', array() );
		if ( isset( $hooks[ $hook_name ] ) && is_callable( $hooks[ $hook_name ] ) ) {
			return call_user_func( $hooks[ $hook_name ], ...$args );
		}
		return $args[0] ?? null;
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
	 * Set the conditional manager instance
	 *
	 * @param Form_Conditional_Manager $conditional_manager Conditional manager instance.
	 * @return void
	 */
	public function set_conditional_manager( Form_Conditional_Manager $conditional_manager ): void {
		$this->conditional_manager = $conditional_manager;
	}

	/**
	 * Filter out data from hidden conditional fields for security and data integrity
	 *
	 * @param array<string, mixed> $form_data Submitted form data.
	 * @return array<string, mixed> Filtered form data with hidden conditional fields removed.
	 */
	private function filter_conditional_field_data( array $form_data ): array {
		// If we don't have a conditional manager, return data as-is.
		if ( ! $this->conditional_manager ) {
			return $form_data;
		}

		$filtered_data = array();

		foreach ( $form_data as $field_id => $value ) {
			// Skip data from hidden conditional fields.
			if ( isset( $this->fields[ $field_id ] ) && isset( $this->fields[ $field_id ]['conditional'] ) ) {
				if ( ! $this->conditional_manager->should_show_field( $field_id ) ) {
					// Field is conditionally hidden, don't include its data.
					continue;
				}
			}

			$filtered_data[ $field_id ] = $value;
		}

		return $filtered_data;
	}

	/**
	 * Get the conditional manager instance.
	 *
	 * @return Form_Conditional_Manager|null The conditional manager or null if not set.
	 */
	public function get_conditional_manager(): ?Form_Conditional_Manager {
		return $this->conditional_manager;
	}
}
