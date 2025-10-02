<?php
/**
 * Settings for CampaignBridge Admin Interface.
 *
 * Handles plugin settings configuration and email service provider integration.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings: handles the plugin settings configuration interface.
 */
class Settings extends Admin {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-settings';

	/**
	 * Default provider slug.
	 */
	private const DEFAULT_PROVIDER = 'html';

	/**
	 * Settings field name.
	 */
	private const SETTINGS_FIELD = 'campaignbridge';

	/**
	 * Nonce action name.
	 */
	private const NONCE_ACTION = 'campaignbridge-options';

	/**
	 * Submit button text.
	 */
	private const SUBMIT_BUTTON_TEXT = 'Save Settings';

	/**
	 * Provider select name attribute.
	 */
	private const PROVIDER_FIELD_NAME = 'provider';

	/**
	 * Initialize the Settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings_sections' ) );
	}

	/**
	 * Enqueue Settings page assets.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_settings_assets(): void {
		if ( ! \CampaignBridge\Admin\Page_Utils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'campaignbridge-settings' );
		wp_enqueue_script( 'campaignbridge-settings' );
	}
	/**
	 * Render the Settings configuration page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'campaignbridge' ) );
		}

		self::render_settings_form();
	}

	/**
	 * Register settings sections and fields using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function register_settings_sections(): void {
		// Register sections based on current tab.
		$current_tab = self::get_current_tab();

		if ( 'general' === $current_tab ) {
			// General settings section.
			add_settings_section(
				'campaignbridge_general',
				__( 'General Settings', 'campaignbridge' ),
				array( __CLASS__, 'render_general_section' ),
				self::SETTINGS_FIELD . '_general'
			);

			// General settings fields.
			add_settings_field(
				'from_name',
				__( 'From Name', 'campaignbridge' ),
				array( __CLASS__, 'render_from_name_field' ),
				self::SETTINGS_FIELD . '_general',
				'campaignbridge_general'
			);

			add_settings_field(
				'from_email',
				__( 'From Email', 'campaignbridge' ),
				array( __CLASS__, 'render_from_email_field' ),
				self::SETTINGS_FIELD . '_general',
				'campaignbridge_general'
			);
		} elseif ( 'providers' === $current_tab ) {
			// Provider settings section.
			add_settings_section(
				'campaignbridge_providers',
				__( 'Provider Settings', 'campaignbridge' ),
				array( __CLASS__, 'render_provider_section' ),
				self::SETTINGS_FIELD . '_providers'
			);

			// Provider settings fields.
			add_settings_field(
				'provider',
				__( 'Provider', 'campaignbridge' ),
				array( __CLASS__, 'render_provider_field' ),
				self::SETTINGS_FIELD . '_providers',
				'campaignbridge_providers'
			);

			// Provider-specific fields will be added dynamically in render_provider_field.
		}
	}

	/**
	 * Render the general settings section description.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure the default sender information for all campaign emails.', 'campaignbridge' ) . '</p>';
	}

	/**
	 * Render the provider settings section description.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function render_provider_section(): void {
		echo '<p>' . esc_html__( 'Select your email service provider and configure the connection settings.', 'campaignbridge' ) . '</p>';
	}

	/**
	 * Render the from name field.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function render_from_name_field(): void {
		$settings = self::get_settings();
		$value    = $settings['from_name'] ?? '';

		echo '<input type="text" name="' . esc_attr( self::get_option_name() ) . '[from_name]" value="' . esc_attr( $value ) . '" size="50" />';
		echo '<p class="description">' . esc_html__( 'Default sender name for all emails.', 'campaignbridge' ) . '</p>';
	}

	/**
	 * Render the from email field.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function render_from_email_field(): void {
		$settings = self::get_settings();
		$value    = $settings['from_email'] ?? '';

		echo '<input type="email" name="' . esc_attr( self::get_option_name() ) . '[from_email]" value="' . esc_attr( $value ) . '" size="50" />';
		echo '<p class="description">' . esc_html__( 'Default sender email address for all emails.', 'campaignbridge' ) . '</p>';
	}

	/**
	 * Render the provider field.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function render_provider_field(): void {
		$settings  = self::get_settings();
		$providers = self::get_providers();
		$provider  = self::get_selected_provider( $settings, $providers );

		echo '<select name="' . esc_attr( self::get_option_name() . '[' . self::PROVIDER_FIELD_NAME . ']' ) . '">';
		foreach ( $providers as $slug => $obj ) {
			// Skip example provider in production dropdown.
			if ( 'example' === $slug ) {
				continue;
			}
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $slug, $provider, false ) . '>' . esc_html( $obj->label() ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose which email client or export method to use.', 'campaignbridge' ) . '</p>';

		// Render provider-specific fields.
		if ( isset( $providers[ $provider ] ) ) {
			echo '<div class="provider-specific-fields" style="margin-top: 16px;">';
			$providers[ $provider ]->render_settings_fields( $settings, self::get_option_name() );
			echo '</div>';
		}
	}

	/**
	 * Get the selected provider or default to html.
	 *
	 * @param array $settings  Current settings.
	 * @param array $providers Available providers.
	 * @return string The selected provider slug.
	 */
	private static function get_selected_provider( array $settings, array $providers ): string {
		$current_provider = $settings['provider'] ?? self::DEFAULT_PROVIDER;

		return ( isset( $providers[ $current_provider ] ) )
			? $current_provider
			: self::DEFAULT_PROVIDER;
	}

	/**
	 * Render the settings form using WordPress Settings API.
	 *
	 * @return void.
	 */
	private static function render_settings_form(): void {
		$current_tab = self::get_current_tab();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<?php self::display_messages(); ?>

			<form method="post" action="options.php">
				<?php
				$current_tab    = self::get_current_tab();
				$settings_field = ( 'general' === $current_tab ) ? self::SETTINGS_FIELD . '_general' : self::SETTINGS_FIELD . '_providers';
				settings_fields( $settings_field );
				?>

				<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
					<a href="<?php echo esc_url( self::get_tab_url( 'general' ) ); ?>"
						class="nav-tab<?php echo ( 'general' === $current_tab ) ? ' nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'General', 'campaignbridge' ); ?>
					</a>
					<a href="<?php echo esc_url( self::get_tab_url( 'providers' ) ); ?>"
						class="nav-tab<?php echo ( 'providers' === $current_tab ) ? ' nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Providers', 'campaignbridge' ); ?>
					</a>
				</nav>

				<div class="tab-content">
					<?php
					$current_tab    = self::get_current_tab();
					$settings_field = ( 'general' === $current_tab ) ? self::SETTINGS_FIELD . '_general' : self::SETTINGS_FIELD . '_providers';
					do_settings_sections( $settings_field );
					?>
				</div>

				<?php submit_button( self::SUBMIT_BUTTON_TEXT ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the current active tab from URL parameters.
	 *
	 * @since 0.1.0
	 * @return string The current tab slug, defaults to 'general'.
	 */
	private static function get_current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		// Validate tab exists.
		$valid_tabs = array( 'general', 'providers' );
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			$tab = 'general';
		}

		return $tab;
	}

	/**
	 * Get URL for a specific tab.
	 *
	 * @since 0.1.0
	 * @param string $tab The tab slug.
	 * @return string The URL for the tab.
	 */
	private static function get_tab_url( string $tab ): string {
		$base_url = admin_url( 'admin.php?page=' . self::get_page_slug() );
		return add_query_arg( 'tab', $tab, $base_url );
	}

	/**
	 * Get the page title.
	 *
	 * @since 0.1.0
	 * @return string The localized page title.
	 */
	public static function get_page_title(): string {
		return __( 'CampaignBridge Settings', 'campaignbridge' );
	}
}
