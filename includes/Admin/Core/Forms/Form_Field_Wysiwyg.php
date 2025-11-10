<?php
/**
 * Form Field WYSIWYG
 *
 * Handles rich text editor input fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field WYSIWYG Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Wysiwyg extends Form_Field_Base {

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		$value      = $this->get_value();
		$attributes = $this->render_common_attributes();

		// Generate unique ID for the editor.
		$editor_id = $this->config['id'] . '_editor';

		// Set up editor settings.
		$editor_settings = wp_parse_args(
			$this->config['editor_settings'] ?? array(),
			array(
				'textarea_name' => $this->config['name'],
				'textarea_rows' => 10,
				'media_buttons' => true,
				'tinymce'       => array(
					'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
					'toolbar2' => 'formatselect,underline,alignjustify,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
				),
				'quicktags'     => true,
			)
		);

		// Output the textarea.
		printf(
			'<textarea id="%s" %s>%s</textarea>',
			esc_attr( $editor_id ),
			$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
			esc_textarea( $value )
		);

		// Initialize WordPress editor.
		$this->enqueue_editor_scripts( $editor_id, $editor_settings );
	}

	/**
	 * Enqueue editor scripts and initialize
	 *
	 * @param string               $editor_id      Editor ID.
	 * @param array<string, mixed> $editor_settings Editor settings.
	 */
	private function enqueue_editor_scripts( string $editor_id, array $editor_settings ): void {
		// Ensure WordPress editor scripts are loaded.
		if ( ! \did_action( 'wp_enqueue_editor' ) ) {
			\wp_enqueue_editor();
		}

		// Add inline script to initialize the editor.
		$init_script = sprintf(
			'jQuery(document).ready(function($) {
				wp.editor.initialize("%s", %s);
			});',
			$editor_id,
			wp_json_encode( $editor_settings )
		);

		wp_add_inline_script( 'editor', $init_script );
	}

	/**
	 * Validate field value
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate( $value ) {
		// First run parent validation.
		$parent_validation = parent::validate( $value );
		if ( is_wp_error( $parent_validation ) ) {
			return $parent_validation;
		}

		// WYSIWYG validation - allow HTML but sanitize.
		if ( ! empty( $value ) ) {
			$sanitized = wp_kses_post( $value );

			// Check if sanitization removed important content.
			if ( strlen( $sanitized ) < strlen( $value ) * 0.8 ) {
				return new \WP_Error(
					'invalid_html',
					__( 'Content contains invalid HTML that was removed.', 'campaignbridge' )
				);
			}
		}

		return true;
	}
}
