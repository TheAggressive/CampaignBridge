<?php
/**
 * Form Renderer - Handles form rendering logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form;

/**
 * Form Renderer - Handles form rendering logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Renderer {

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
	 * Form data
	 *
	 * @var array
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
	 * @param Form          $form     Parent form instance.
	 * @param array         $config   Form configuration.
	 * @param array         $fields   Form fields.
	 * @param array         $data     Form data.
	 * @param Form_Handler  $handler  Form handler instance.
	 * @param Form_Security $security Security instance.
	 */
	public function __construct( Form $form, array $config, array $fields, array $data, Form_Handler $handler, Form_Security $security ) {
		$this->form     = $form;
		$this->config   = $config;
		$this->fields   = $fields;
		$this->data     = $data;
		$this->handler  = $handler;
		$this->security = $security;
	}

	/**
	 * Render switch styles
	 */
	public function render_switch_styles(): void {
		// Only output styles if we have switch fields.
		$has_switch = false;
		foreach ( $this->fields as $field_config ) {
			if ( isset( $field_config['type'] ) && in_array( $field_config['type'], array( 'switch', 'toggle' ), true ) ) {
				$has_switch = true;
				break;
			}
		}

		if ( ! $has_switch ) {
			return;
		}

		echo '<style>
		.campaignbridge-switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 24px;
		}
		.campaignbridge-switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}
		.campaignbridge-switch__label {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
		}
		.campaignbridge-switch__slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: .4s;
			border-radius: 24px;
		}
		.campaignbridge-switch__slider:before {
			position: absolute;
			content: "";
			height: 18px;
			width: 18px;
			left: 3px;
			bottom: 3px;
			background-color: white;
			transition: .4s;
			border-radius: 50%;
		}
		input:checked + .campaignbridge-switch__label .campaignbridge-switch__slider {
			background-color: #007cba;
		}
		input:checked + .campaignbridge-switch__label .campaignbridge-switch__slider:before {
			transform: translateX(26px);
		}
		</style>';
	}

	/**
	 * Render messages and errors
	 */
	public function render_messages(): void {
		$messages = $this->handler->get_messages();
		$errors   = $this->handler->get_errors();

		if ( ! empty( $messages ) ) {
			echo '<div class="notice notice-success is-dismissible">';
			foreach ( $messages as $message ) {
				printf( '<p>%s</p>', \esc_html( $message ) );
			}
			echo '</div>';
		}

		if ( ! empty( $errors ) ) {
			echo '<div class="notice notice-error is-dismissible">';
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
	 * @param string $field_id     Field ID.
	 * @param array  $field_config Field configuration.
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
	 * @param string $field_id     Field ID.
	 * @param array  $field_config Field configuration.
	 */
	private function render_table_field( string $field_id, array $field_config ): void {
		$field_name = $this->config['prefix'] . $field_id . $this->config['suffix'];
		$value      = $this->data[ $field_id ] ?? $field_config['default'] ?? '';
		$label      = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $field_id ) );
		$required   = $field_config['required'] ?? false;

		printf( '<tr><th scope="row"><label for="%s">%s%s</label></th><td>', \esc_attr( $field_name ), \esc_html( $label ), $required ? ' <span class="required">*</span>' : '' );

		$this->render_field_input( $field_name, $field_config, $value );

		if ( isset( $field_config['description'] ) ) {
			printf( '<p class="description">%s</p>', \esc_html( $field_config['description'] ) );
		}

		echo '</td></tr>';
	}

	/**
	 * Render field in div layout
	 *
	 * @param string $field_id     Field ID.
	 * @param array  $field_config Field configuration.
	 */
	private function render_div_field( string $field_id, array $field_config ): void {
		$field_name = $this->config['prefix'] . $field_id . $this->config['suffix'];
		$value      = $this->data[ $field_id ] ?? $field_config['default'] ?? '';
		$label      = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $field_id ) );
		$required   = $field_config['required'] ?? false;

		printf( '<div class="campaignbridge-form-field"><label for="%s">%s%s</label>', \esc_attr( $field_name ), \esc_html( $label ), $required ? ' <span class="required">*</span>' : '' );

		$this->render_field_input( $field_name, $field_config, $value );

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
	 * @param string $field_name   Field name.
	 * @param array  $field_config Field configuration.
	 * @param mixed  $value        Field value.
	 */
	private function render_field_input( string $field_name, array $field_config, $value ): void {
		$field_renderer = $this->create_field_renderer( $field_name, $field_config, $value );
		$field_renderer->render_input();
	}

	/**
	 * Create field renderer instance
	 *
	 * @param string $field_name   Field name.
	 * @param array  $field_config Field configuration.
	 * @param mixed  $value        Field value.
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
		$action  = \esc_url( $this->config['action'] ?: '' );
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

		printf( '<p class="submit"><input type="submit" name="%s" id="%s" class="button button-%s" value="%s"%s /></p>', \esc_attr( $submit_name ), \esc_attr( $submit_id ), \esc_attr( $type ), \esc_attr( $text ), $attr_string ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
