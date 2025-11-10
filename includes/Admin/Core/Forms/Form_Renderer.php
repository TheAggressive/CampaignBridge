<?php
/**
 * Form Renderer - Handles form rendering logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Renderer - Handles form rendering logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Renderer {

	/**
	 * Form configuration
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Form fields configuration
	 *
	 * @var array<string, mixed>
	 */
	private array $fields;

	/**
	 * Form data
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Security instance
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Form validator instance
	 *
	 * @var Form_Validator
	 */
	private Form_Validator $validator;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $config   Form configuration.
	 * @param array<string, mixed> $fields   Form fields.
	 * @param array<string, mixed> $data     Form data.
	 * @param Form_Security        $security Security instance.
	 * @param Form_Validator       $validator Form validator instance.
	 */
	public function __construct( array $config, array $fields, array $data, Form_Security $security, Form_Validator $validator ) {
		$this->config    = $config;
		$this->fields    = $fields;
		$this->data      = $data;
		$this->security  = $security;
		$this->validator = $validator;
	}

	/**
	 * Enqueue form styles using asset files for proper dependency and version management
	 */
	public function enqueue_form_styles(): void {
		// Load the compiled form styles (includes all components in single optimized file).
		\CampaignBridge\Admin\Asset_Manager::enqueue_asset( 'campaignbridge-admin-form-styles', 'dist/styles/admin/forms/forms.asset.php' );
	}

	/**
	 * Render all form fields
	 */
	public function render_fields(): void {
		$layout = $this->config['layout'];

		if ( 'custom' === $layout ) {
			$this->render_custom_layout();
			return;
		}

		if ( 'table' === $layout ) {
			echo '<table class="form-table">';
		} elseif ( 'div' === $layout ) {
			echo '<div class="campaignbridge-form-fields">';
		}

		foreach ( $this->fields as $field_id => $field_config ) {
			$this->render_field( $field_id, $field_config );
		}

		if ( 'table' === $layout ) {
			echo '</table>';
		} elseif ( 'div' === $layout ) {
			echo '</div>';
		}
	}

	/**
	 * Render a single field
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 */
	public function render_field( string $field_id, array $field_config ): void {
		$layout = $this->config['layout'];

		// If using render_sequence for custom layouts, render the field normally.
		if ( 'custom' === $layout && isset( $this->config['render_sequence'] ) ) {
			$this->render_div_field( $field_id, $field_config );
			return;
		}

		if ( 'table' === $layout ) {
			$this->render_table_field( $field_id, $field_config );
		} elseif ( 'div' === $layout ) {
			$this->render_div_field( $field_id, $field_config );
		}
	}

	/**
	 * Render field in table layout
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 */
	private function render_table_field( string $field_id, array $field_config ): void {
		$field_data      = $this->prepare_field_data( $field_id, $field_config );
		$wrapper_classes = $this->build_field_wrapper_classes( $field_data['field_name'], $field_config );

		// For table layout, add conditional class to tr for FOUC prevention.
		$row_classes = array( 'campaignbridge-field' );
		if ( isset( $field_config['conditional'] ) ) {
			$row_classes[] = 'campaignbridge-conditional-hidden';
		}

		printf(
			'<tr class="%s"><th scope="row"><label for="%s" class="campaignbridge-field__label">%s%s</label></th><td>',
			\esc_attr( implode( ' ', $row_classes ) ),
			\esc_attr( $field_data['field_id_attr'] ),
			\esc_html( $field_data['label'] ),
			$field_data['required'] ? '<span class="campaignbridge-field__required">*</span>' : ''
		);

		// Wrap field input in validation container with validation state classes.
		printf( '<div class="%s">', esc_attr( implode( ' ', $wrapper_classes ) ) );

		$this->render_field_content( $field_data, $field_config, array(), 'table' );

		echo '</div></td></tr>';
	}

	/**
	 * Render field in div layout
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 */
	private function render_div_field( string $field_id, array $field_config ): void {
		$field_data      = $this->prepare_field_data( $field_id, $field_config );
		$wrapper_classes = $this->build_field_wrapper_classes( $field_data['field_name'], $field_config );

		// Add conditional class to wrapper for FOUC prevention.
		if ( isset( $field_config['conditional'] ) ) {
			$wrapper_classes[] = 'campaignbridge-conditional-hidden';
		}

		// Label.
		$required_indicator = $field_data['required'] ? '<span class="campaignbridge-field__required">*</span>' : '';
		printf(
			'<div class="%s"><label for="%s" class="campaignbridge-field__label">%s%s</label>',
			esc_attr( implode( ' ', $wrapper_classes ) ),
			\esc_attr( $field_data['field_id_attr'] ),
			\esc_html( $field_data['label'] ),
			wp_kses_post( $required_indicator )
		);

		// Render field content.
		$this->render_field_content( $field_data, $field_config, array(), 'div' );

		echo '</div>';
	}

	/**
	 * Prepare field data for rendering
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return array<string, mixed> Prepared field data.
	 */
	private function prepare_field_data( string $field_id, array $field_config ): array {
		// Note: prefix/suffix are for options storage, not HTML field names.
		$field_name = $field_id;
		$value      = $this->data[ $field_id ] ?? $field_config['default'] ?? '';
		$label      = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $field_id ) );
		$required   = $field_config['required'] ?? false;

		// Get form ID for namespacing the field ID to match the input element.
		$form_id       = $this->config['form_id'] ?? 'form';
		$field_id_attr = $form_id . '_' . $field_name;

		return array(
			'field_name'    => $field_name,
			'value'         => $value,
			'label'         => $label,
			'required'      => $required,
			'field_id_attr' => $field_id_attr,
		);
	}

	/**
	 * Build field wrapper classes
	 *
	 * @param string               $field_name   Field name.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return array<string> Field wrapper classes.
	 */
	private function build_field_wrapper_classes( string $field_name, array $field_config ): array {
		$wrapper_classes = array( 'campaignbridge-field-wrapper' );

		// For repeater fields, add layout classes to wrapper if detected in field classes.
		if ( strpos( $field_name, '___' ) !== false && isset( $field_config['class'] ) ) {
			$field_class_string = $field_config['class'];
			if ( strpos( $field_class_string, 'campaignbridge-repeater-horizontal' ) !== false ) {
				$wrapper_classes[] = 'campaignbridge-repeater-horizontal';
			}
			if ( strpos( $field_class_string, 'campaignbridge-repeater-vertical' ) !== false ) {
				$wrapper_classes[] = 'campaignbridge-repeater-vertical';
			}
		}

		return $wrapper_classes;
	}

	/**
	 * Render unified field content (input, errors, description)
	 *
	 * @param array<string, mixed> $field_data     Prepared field data.
	 * @param array<string, mixed> $field_config   Field configuration.
	 * @param array<string>        $wrapper_classes Additional wrapper classes.
	 * @param string               $layout         Layout type ('table' or 'div').
	 */
	private function render_field_content( array $field_data, array $field_config, array $wrapper_classes, string $layout ): void {
		// For table layout, wrapper classes are already applied at the td level.
		if ( 'table' === $layout ) {
			// Input field.
			$this->render_field_input( $field_data['field_name'], $field_config, $field_data['value'] );

			// Error container for validation.
			$this->render_field_errors( $field_data['field_id_attr'], $field_config );

			// Description.
			$this->render_field_description( $field_config );
		} else {
			// For div layout, wrap input in additional div if wrapper classes provided.
			if ( ! empty( $wrapper_classes ) ) {
				printf( '<div class="%s">', esc_attr( implode( ' ', $wrapper_classes ) ) );
			}

			// Input field.
			$this->render_field_input( $field_data['field_name'], $field_config, $field_data['value'] );

			// Error container for validation.
			$this->render_field_errors( $field_data['field_id_attr'], $field_config );

			// Description.
			$this->render_field_description( $field_config );

			if ( ! empty( $wrapper_classes ) ) {
				echo '</div>';
			}
		}
	}

	/**
	 * Render field errors container
	 *
	 * @param string               $field_id_attr Field ID attribute.
	 * @param array<string, mixed> $field_config Field configuration.
	 */
	private function render_field_errors( string $field_id_attr, array $field_config ): void {
		printf( '<div class="campaignbridge-field__errors" id="%s_errors" role="alert" aria-live="polite">', \esc_attr( $field_id_attr ) );

		// Render field-specific errors if any.
		if ( isset( $field_config['errors'] ) && ! empty( $field_config['errors'] ) ) {
			foreach ( $field_config['errors'] as $error ) {
				echo '<div class="campaignbridge-field__error">' . esc_html( $error ) . '</div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render field description
	 *
	 * @param array<string, mixed> $field_config Field configuration.
	 */
	private function render_field_description( array $field_config ): void {
		if ( isset( $field_config['description'] ) ) {
			printf( '<p class="campaignbridge-field__description">%s</p>', \esc_html( $field_config['description'] ) );
		}
	}

	/**
	 * Render custom layout
	 */
	private function render_custom_layout(): void {
		// Execute render sequence (custom HTML mixed with fields).
		if ( isset( $this->config['render_sequence'] ) && is_array( $this->config['render_sequence'] ) ) {
			foreach ( $this->config['render_sequence'] as $item ) {
				if ( 'custom' === $item['type'] && is_callable( $item['renderer'] ) ) {
					call_user_func( $item['renderer'] );
				} elseif ( 'field' === $item['type'] && isset( $this->fields[ $item['name'] ] ) ) {
					$this->render_field( $item['name'], $this->fields[ $item['name'] ] );
				}
			}
		}
	}

	/**
	 * Render field input element
	 *
	 * @param string               $field_name   Field name.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param mixed                $value        Field value.
	 */
	private function render_field_input( string $field_name, array $field_config, $value ): void {
		$field_renderer = $this->create_field_renderer( $field_name, $field_config, $value );

		// For encrypted fields, use render() method instead of render_input().
		if ( $field_renderer instanceof Form_Field_Encrypted ) {
			echo $field_renderer->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encrypted field HTML is properly escaped internally
		} else {
			$field_renderer->render_input();
		}
	}

	/**
	 * Create field renderer instance
	 *
	 * @param string               $field_name   Field name.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param mixed                $value        Field value.
	 * @return Form_Field_Interface Field renderer instance.
	 */
	private function create_field_renderer( string $field_name, array $field_config, $value ): Form_Field_Interface {
		$type = $field_config['type'] ?? 'text';

		// Prepare field configuration for rendering.
		$config = $this->prepare_field_config( $field_name, $field_config, $value );

		// Add conditional field marker.
		$config = $this->mark_conditional_field( $config, $field_config );

		// Assign CSS classes.
		$config = $this->assign_field_classes( $config, $field_config );

		// Get renderer class and instantiate.
		$renderer_class = $this->get_field_renderer_class( $type );

		return new $renderer_class( $config, $this->validator );
	}

	/**
	 * Prepare field configuration for rendering
	 *
	 * @param string               $field_name   Field name.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param mixed                $value        Field value.
	 * @return array<string, mixed> Prepared config.
	 */
	private function prepare_field_config( string $field_name, array $field_config, $value ): array {
		$form_id = $this->config['form_id'] ?? 'form';

		return array(
			'name'  => $form_id . '[' . $field_name . ']',
			'id'    => $form_id . '_' . $field_name,
			'value' => $value,
		) + $field_config;
	}

	/**
	 * Mark conditional fields for client-side processing
	 *
	 * @param array<string, mixed> $config       Field config.
	 * @param array<string, mixed> $field_config Original field configuration.
	 * @return array<string, mixed> Updated config.
	 */
	private function mark_conditional_field( array $config, array $field_config ): array {
		if ( isset( $field_config['conditional'] ) ) {
			$config['data-conditional-field'] = 'true';
		}

		return $config;
	}

	/**
	 * Assign CSS classes to field configuration
	 *
	 * @param array<string, mixed> $config       Field config.
	 * @param array<string, mixed> $field_config Original field configuration.
	 * @return array<string, mixed> Updated config with classes.
	 */
	private function assign_field_classes( array $config, array $field_config ): array {
		$existing_class = $config['class'] ?? '';
		$field_type     = $field_config['type'] ?? 'text';

		$base_class = $this->get_field_base_class( $field_type );
		$type_class = 'campaignbridge-field__' . $field_type;

		$config['class'] = trim( $existing_class . ' ' . $base_class . ' ' . $type_class );

		return $config;
	}

	/**
	 * Get base CSS class for field type
	 *
	 * @param string $field_type Field type.
	 * @return string Base CSS class.
	 */
	private function get_field_base_class( string $field_type ): string {
		$base_class_map = array(
			'text'           => 'campaignbridge-field__input',
			'email'          => 'campaignbridge-field__input',
			'url'            => 'campaignbridge-field__input',
			'password'       => 'campaignbridge-field__input',
			'number'         => 'campaignbridge-field__input',
			'tel'            => 'campaignbridge-field__input',
			'search'         => 'campaignbridge-field__input',
			'date'           => 'campaignbridge-field__input',
			'time'           => 'campaignbridge-field__input',
			'datetime-local' => 'campaignbridge-field__input',
			'color'          => 'campaignbridge-field__input',
			'range'          => 'campaignbridge-field__input',
			'textarea'       => 'campaignbridge-field__textarea',
			'select'         => 'campaignbridge-field__select',
			'radio'          => 'campaignbridge-field__radio',
			'checkbox'       => 'campaignbridge-field__checkbox',
		);

		return $base_class_map[ $field_type ] ?? 'campaignbridge-field__input';
	}

	/**
	 * Get field renderer class for field type
	 *
	 * @param string $field_type Field type.
	 * @return string Renderer class name.
	 */
	private function get_field_renderer_class( string $field_type ): string {
		$type_map = array(
			'text'           => Form_Field_Input::class,
			'email'          => Form_Field_Input::class,
			'url'            => Form_Field_Input::class,
			'password'       => Form_Field_Input::class,
			'number'         => Form_Field_Input::class,
			'tel'            => Form_Field_Input::class,
			'search'         => Form_Field_Input::class,
			'date'           => Form_Field_Input::class,
			'time'           => Form_Field_Input::class,
			'datetime-local' => Form_Field_Input::class,
			'color'          => Form_Field_Input::class,
			'range'          => Form_Field_Input::class,
			'textarea'       => Form_Field_Textarea::class,
			'select'         => Form_Field_Select::class,
			'radio'          => Form_Field_Radio::class,
			'checkbox'       => Form_Field_Checkbox::class,
			'file'           => Form_Field_File::class,
			'wysiwyg'        => Form_Field_Wysiwyg::class,
			'switch'         => Form_Field_Switch::class,
			'toggle'         => Form_Field_Switch::class,
			'encrypted'      => Form_Field_Encrypted::class,
		);

		return $type_map[ $field_type ] ?? Form_Field_Input::class;
	}

	/**
	 * Render form opening tag
	 */
	public function render_form_open(): void {

		// Inject FOUC prevention CSS immediately if form has conditional fields.
		if ( $this->has_conditional_fields() ) {
			$this->inject_conditional_css();
		}

		$method  = strtolower( $this->config['method'] );
		$action  = \esc_url( $this->config['action'] ?? '' );
		$enctype = $this->config['enctype'];
		$classes = implode( ' ', $this->config['classes'] );

		$attributes  = $this->config['attributes'];
		$attr_string = '';

		// Add form ID for JavaScript targeting.
		$form_id = $this->config['form_id'] ?? '';
		if ( ! empty( $form_id ) ) {
			$attr_string .= sprintf( ' id="%s"', \esc_attr( $form_id ) );
		}

		// Handle conditional fields setup.
		if ( $this->has_conditional_fields() ) {
			$attr_string .= ' data-conditional="true"';
			$attr_string .= ' data-conditional-action="campaignbridge_evaluate_conditions"';
			$attr_string .= ' aria-live="polite"';
			$attr_string .= ' aria-atomic="false"';

			// Enqueue conditional JavaScript and CSS.
			\CampaignBridge\Admin\Asset_Manager::enqueue_asset_style(
				'campaignbridge-condition-fields',
				'dist/styles/admin/forms/conditional-fields/index.asset.php'
			);

			\CampaignBridge\Admin\Asset_Manager::enqueue_asset_script(
				'campaignbridge-condition-fields',
				'dist/scripts/admin/forms/conditional-fields/index.asset.php'
			);
		}

		foreach ( $attributes as $key => $value ) {
			$attr_string .= sprintf( ' %s="%s"', \esc_attr( $key ), \esc_attr( $value ) );
		}

		printf(
			'<form method="%s" action="%s" enctype="%s" class="%s"%s>',
			\esc_attr( $method ),
			$action, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $action is built by render_form_open() with proper escaping.
			\esc_attr( $enctype ),
			\esc_attr( $classes ),
			$attr_string // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attr_string is built by render_form_open() with proper escaping.
		);

		// Add security nonce.
		$this->security->render_security_fields();

		// Enqueue form loading script for better UX.
		$this->enqueue_loading_script();

		// Render form description if set.
		$description = $this->config['description'] ?? '';
		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', \esc_html( $description ) );
		}
	}

	/**
	 * Inject critical CSS for FOUC prevention directly into the page head
	 *
	 * This ensures conditional fields are hidden immediately when HTML is parsed,
	 * preventing any flash of content before JavaScript or external CSS loads.
	 */
	private function inject_conditional_css(): void {
		$css = '
		<style id="campaignbridge-conditional-fouc-prevention">
			/* FOUC Prevention: Hide conditional field containers immediately */
			.campaignbridge-conditional-hidden,
			.campaignbridge-conditional-hidden.campaignbridge-field-wrapper,
			.campaignbridge-conditional-hidden.campaignbridge-field {
				display: none !important;
				visibility: hidden !important;
				opacity: 0 !important;
				height: 0 !important;
				width: 0 !important;
				overflow: hidden !important;
				position: absolute !important;
				clip: rect(0, 0, 0, 0) !important;
			}

			/* Hide table rows with conditional fields */
			tr.campaignbridge-conditional-hidden {
				display: none !important;
				visibility: hidden !important;
			}
		</style>';

		// Inject CSS immediately at the current position in HTML.
		echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $css is a safe string literal.
	}

	/**
	 * Enqueue form loading script for submission UX.
	 */
	private function enqueue_loading_script(): void {
		$form_id      = $this->config['form_id'] ?? 'form';
		$loading_text = \__( 'Saving...', 'campaignbridge' );
		$submit_text  = $this->config['submit_button']['text'] ?? \__( 'Save Changes', 'campaignbridge' );

		// Localize script with form-specific data.
		\wp_localize_script(
			'campaignbridge-form-loading',
			'campaignbridgeFormLoading',
			array(
				'formId'      => $form_id,
				'loadingText' => $loading_text,
				'submitText'  => $submit_text,
			)
		);
	}

	/**
	 * Render submit button.
	 */
	public function render_submit_button(): void {
		$form_id       = $this->config['form_id'] ?? 'form';
		$button_config = $this->config['submit_button'];
		$text          = $button_config['text'] ?? \__( 'Save Changes', 'campaignbridge' );
		$type          = $button_config['type'] ?? 'primary';
		$attributes    = $button_config['attributes'] ?? array();

		$attr_string = '';
		foreach ( $attributes as $key => $value ) {
			$attr_string .= sprintf( ' %s="%s"', \esc_attr( $key ), \esc_attr( $value ) );
		}

		$submit_id   = $form_id . '_submit';
		$submit_name = $form_id . '[submit]';

		printf( '<p class="submit"><input type="submit" name="%s" id="%s" class="button button-%s" value="%s" /></p>', \esc_attr( $submit_name ), \esc_attr( $submit_id ), \esc_attr( $type ), \esc_attr( $text ) );
	}


	/**
	 * Render form closing tag
	 *
	 * @return void
	 */
	public function render_form_close(): void {
		echo '</form>';
	}

	/**
	 * Check if form has conditional fields
	 *
	 * @return bool True if form has conditional fields.
	 */
	public function has_conditional_fields(): bool {
		foreach ( $this->fields as $field_config ) {
			if ( isset( $field_config['conditional'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the form fields configuration
	 *
	 * @return array<string, mixed> Form fields configuration.
	 */
	public function get_fields(): array {
		return $this->fields;
	}
}
