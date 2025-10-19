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
	 * Form handler instance
	 *
	 * @var Form_Handler
	 */
	private Form_Handler $handler;

	/**
	 * Security instance
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $config   Form configuration.
	 * @param array<string, mixed> $fields   Form fields.
	 * @param array<string, mixed> $data     Form data.
	 * @param Form_Handler         $handler  Form handler instance.
	 * @param Form_Security        $security Security instance.
	 */
	public function __construct( array $config, array $fields, array $data, Form_Handler $handler, Form_Security $security ) {
		$this->config   = $config;
		$this->fields   = $fields;
		$this->data     = $data;
		$this->handler  = $handler;
		$this->security = $security;
	}

	/**
	 * Enqueue form styles using asset files for proper dependency and version management
	 */
	public function enqueue_form_styles(): void {
		// Load the compiled form styles (includes all components in single optimized file).
		\CampaignBridge\Admin\Asset_Manager::enqueue_asset( 'campaignbridge-forms', 'dist/styles/admin/forms/forms.asset.php' );
	}

	/**
	 * Render messages and errors
	 */
	public function render_messages(): void {
		$messages = $this->handler->get_messages();
		$errors   = $this->handler->get_errors();

		// Only show success messages if there are no errors
		// This prevents confusing UX where both success and error messages appear.
		if ( ! empty( $messages ) && empty( $errors ) ) {
			echo '<div class="notice notice-success is-dismissible" role="status" aria-live="polite" aria-atomic="true">';
			foreach ( $messages as $message ) {
				printf( '<p>%s</p>', \esc_html( $message ) );
			}
			echo '</div>';
		}

		if ( ! empty( $errors ) ) {
			echo '<div class="notice notice-error is-dismissible" role="alert" aria-live="assertive" aria-atomic="true">';
			foreach ( $errors as $error ) {
				printf( '<p>%s</p>', \esc_html( $error ) );
			}
			echo '</div>';
		}
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
	private function render_field( string $field_id, array $field_config ): void {
		$layout = $this->config['layout'];

		// If using render_sequence for custom layouts, render the field normally.
		if ( 'custom' === $layout && isset( $this->config['render_sequence'] ) ) {
			$this->render_div_field( $field_id, $field_config );
			return;
		}

		// Legacy custom layout behavior (no render_sequence).
		if ( 'custom' === $layout ) {
			$this->run_hook( 'render_layout', $field_id, $field_config );
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
		// Note: prefix/suffix are for options storage, not HTML field names.
		$field_name = $field_id;
		$value      = $this->data[ $field_id ] ?? $field_config['default'] ?? '';
		$label      = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $field_id ) );
		$required   = $field_config['required'] ?? false;

		// Get form ID for namespacing the field ID to match the input element.
		$form_id       = $this->config['form_id'] ?? 'form';
		$field_id_attr = $form_id . '_' . $field_name;

		printf( '<tr><th scope="row"><label for="%s">%s%s</label></th><td>', \esc_attr( $field_id_attr ), \esc_html( $label ), $required ? ' <span class="required">*</span>' : '' );

		$this->render_field_input( $field_name, $field_config, $value );

		// Render field-specific errors if any.
		if ( isset( $field_config['errors'] ) && ! empty( $field_config['errors'] ) ) {
			$error_id = $field_id_attr . '_error';
			echo '<div class="field-errors" id="' . esc_attr( $error_id ) . '" role="alert" aria-live="polite">';
			foreach ( $field_config['errors'] as $error ) {
				echo '<span class="field-error">' . esc_html( $error ) . '</span>';
			}
			echo '</div>';
		}

		if ( isset( $field_config['description'] ) ) {
			printf( '<p class="description">%s</p>', \esc_html( $field_config['description'] ) );
		}

		echo '</td></tr>';
	}

	/**
	 * Render field in div layout
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 */
	private function render_div_field( string $field_id, array $field_config ): void {
		// Note: prefix/suffix are for options storage, not HTML field names.
		$field_name = $field_id;
		$value      = $this->data[ $field_id ] ?? $field_config['default'] ?? '';
		$label      = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $field_id ) );
		$required   = $field_config['required'] ?? false;

		// Get form ID for namespacing the field ID to match the input element.
		$form_id       = $this->config['form_id'] ?? 'form';
		$field_id_attr = $form_id . '_' . $field_name;

		printf( '<div class="campaignbridge-form-field"><label for="%s">%s%s</label>', \esc_attr( $field_id_attr ), \esc_html( $label ), $required ? ' <span class="required">*</span>' : '' );

		$this->render_field_input( $field_name, $field_config, $value );

		// Render field-specific errors if any.
		if ( isset( $field_config['errors'] ) && ! empty( $field_config['errors'] ) ) {
			$error_id = $field_id_attr . '_error';
			echo '<div class="field-errors" id="' . esc_attr( $error_id ) . '" role="alert" aria-live="polite">';
			foreach ( $field_config['errors'] as $error ) {
				echo '<span class="field-error">' . esc_html( $error ) . '</span>';
			}
			echo '</div>';
		}

		if ( isset( $field_config['description'] ) ) {
			printf( '<p class="description">%s</p>', \esc_html( $field_config['description'] ) );
		}

		echo '</div>';
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
		} else {
			// Fallback: execute all custom renderers if no sequence exists.
			if ( isset( $this->config['custom_renderers'] ) && is_array( $this->config['custom_renderers'] ) ) {
				foreach ( $this->config['custom_renderers'] as $renderer ) {
					if ( is_callable( $renderer ) ) {
						call_user_func( $renderer );
					}
				}
			}

			// Also run the legacy render_layout hook for backward compatibility.
			$this->run_hook( 'render_layout' );
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
		$field_renderer->render_input();
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

		// Get form ID for namespacing field names and IDs.
		$form_id = $this->config['form_id'] ?? 'form';

		// Prepare config with field name and value.
		$config          = $field_config;
		$config['name']  = $form_id . '[' . $field_name . ']';
		$config['id']    = $form_id . '_' . $field_name;
		$config['value'] = $value;

		// Add field-specific errors if any exist.
		$field_errors = $this->handler->get_field_errors();
		if ( isset( $field_errors[ $field_name ] ) ) {
			$config['errors'] = array( $field_errors[ $field_name ] );
		}

		// Map field types to renderer classes.
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
		);

		$renderer_class = $type_map[ $type ] ?? Form_Field_Input::class;

		return new $renderer_class( $config );
	}

	/**
	 * Render form opening tag
	 */
	public function render_form_open(): void {
		$method  = strtolower( $this->config['method'] );
		$action  = \esc_url( $this->config['action'] ?? '' );
		$enctype = $this->config['enctype'];
		$classes = implode( ' ', $this->config['classes'] );

		$attributes  = $this->config['attributes'];
		$attr_string = '';

		foreach ( $attributes as $key => $value ) {
			$attr_string .= sprintf( ' %s="%s"', \esc_attr( $key ), \esc_attr( $value ) );
		}

		printf(
			'<form method="%s" action="%s" enctype="%s" class="%s"%s>',
			\esc_attr( $method ),
			$action, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			\esc_attr( $enctype ),
			\esc_attr( $classes ),
			$attr_string // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		// Add security nonce.
		$this->security->render_security_fields();

		// Render form description if set.
		$description = $this->config['description'] ?? '';
		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', \esc_html( $description ) );
		}
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
	 */
	public function render_form_close(): void {
		echo '</form>';
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
}
