<?php
/**
 * General Settings Tab for CampaignBridge Admin Interface.
 *
 * Handles the general settings tab content including from name and from email configuration.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General Settings Tab class.
 *
 * Handles the general settings tab functionality for basic plugin configuration
 * including sender information and global settings using the tab architecture.
 */
class General_Settings_Tab extends Abstract_Settings_Tab {
	/**
	 * Get the tab slug (used as identifier and URL parameter).
	 *
	 * @since 0.1.0
	 * @return string The tab slug.
	 */
	public static function get_slug(): string {
		return 'general';
	}

	/**
	 * Get the tab label (display name).
	 *
	 * @since 0.1.0
	 * @return string The tab label.
	 */
	public static function get_label(): string {
		return __( 'General', 'campaignbridge' );
	}

	/**
	 * Get the tab description.
	 *
	 * @since 0.1.0
	 * @return string The tab description.
	 */
	public static function get_description(): string {
		return __( 'Basic plugin configuration including sender information.', 'campaignbridge' );
	}

	/**
	 * Register settings sections and fields for this tab.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_settings(): void {
		// General settings section.
		add_settings_section(
			'campaignbridge_general',
			__( 'General Settings', 'campaignbridge' ),
			array( __CLASS__, 'render_general_section' ),
			'campaignbridge_general'
		);

		// General settings fields.
		add_settings_field(
			'from_name',
			__( 'From Name', 'campaignbridge' ),
			array( __CLASS__, 'render_from_name_field' ),
			'campaignbridge_general',
			'campaignbridge_general'
		);

		add_settings_field(
			'from_email',
			__( 'From Email', 'campaignbridge' ),
			array( __CLASS__, 'render_from_email_field' ),
			'campaignbridge_general',
			'campaignbridge_general'
		);
	}

	/**
	 * Render the tab content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		?>
		<div class="tab-content">
			<?php do_settings_sections( 'campaignbridge_general' ); ?>
		</div>
		<?php
	}

	/**
	 * Get the fields that belong to this tab.
	 *
	 * @since 0.1.0
	 * @return array Array of field names.
	 */
	public static function get_tab_fields(): array {
		return array( 'from_name', 'from_email' );
	}

	/**
	 * Validate settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return array Validation errors or empty array if valid.
	 */
	public static function validate_settings( array $settings ): array {
		$errors = array();

		// Validate from name
		if ( isset( $settings['from_name'] ) ) {
			$from_name = trim( $settings['from_name'] );
			if ( empty( $from_name ) ) {
				$errors['from_name'] = __( 'From Name is required.', 'campaignbridge' );
			} elseif ( strlen( $from_name ) > 100 ) {
				$errors['from_name'] = __( 'From Name must be less than 100 characters.', 'campaignbridge' );
			}
		}

		// Validate from email
		if ( isset( $settings['from_email'] ) ) {
			$from_email = trim( $settings['from_email'] );
			if ( empty( $from_email ) ) {
				$errors['from_email'] = __( 'From Email is required.', 'campaignbridge' );
			} elseif ( ! is_email( $from_email ) ) {
				$errors['from_email'] = __( 'Please enter a valid email address.', 'campaignbridge' );
			}
		}

		return $errors;
	}

	/**
	 * Render the general settings section description.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure the default sender information for all campaign emails.', 'campaignbridge' ) . '</p>';
	}

	/**
	 * Render the from name field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_from_name_field(): void {
		self::display_field_errors( 'from_name' );

		self::render_field(
			'from_name',
			'text',
			array(
				'description' => __( 'Default sender name for all emails.', 'campaignbridge' ),
				'placeholder' => __( 'Your Name', 'campaignbridge' ),
			)
		);
	}

	/**
	 * Render the from email field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_from_email_field(): void {
		self::display_field_errors( 'from_email' );

		self::render_field(
			'from_email',
			'email',
			array(
				'description' => __( 'Default sender email address for all emails.', 'campaignbridge' ),
				'placeholder' => __( 'your-email@example.com', 'campaignbridge' ),
			)
		);
	}
}
