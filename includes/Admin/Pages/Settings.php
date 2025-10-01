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
	private const DEFAULT_PROVIDER = 'mailchimp';

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
	}

	/**
	 * Enqueue Settings page assets.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_settings_assets(): void {
		if ( ! \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() ) ) {
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
		$settings  = self::get_settings();
		$providers = self::get_providers();
		$provider  = self::get_selected_provider( $settings, $providers );

		self::display_messages();
		self::render_settings_form( $settings, $providers, $provider );
	}

	/**
	 * Get the selected provider or default to mailchimp.
	 *
	 * @param array $settings  Current settings.
	 * @param array $providers Available providers.
	 * @return string The selected provider slug.
	 */
	private static function get_selected_provider( array $settings, array $providers ): string {
		return ( isset( $settings['provider'] ) && isset( $providers[ $settings['provider'] ] ) )
			? $settings['provider']
			: self::DEFAULT_PROVIDER;
	}

	/**
	 * Render the settings form HTML.
	 *
	 * @param array  $settings  Current settings.
	 * @param array  $providers Available providers.
	 * @param string $provider  Selected provider slug.
	 * @return void
	 */
	private static function render_settings_form( array $settings, array $providers, string $provider ): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_FIELD );
				?>

				<div class="nav-tab-wrapper">
					<a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php echo esc_html__( 'General', 'campaignbridge' ); ?></a>
					<a href="#providers" class="nav-tab" data-tab="providers"><?php echo esc_html__( 'Providers', 'campaignbridge' ); ?></a>
				</div>

				<!-- General Settings Tab -->
				<div id="general" class="tab-content active">
					<table class="form-table cb-settings__form-table">
						<tr>
							<th scope="row"><?php echo esc_html__( 'From Name', 'campaignbridge' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::get_option_name() ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ?? '' ); ?>" size="50" />
								<p class="description"><?php echo esc_html__( 'Default sender name for all emails.', 'campaignbridge' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'From Email', 'campaignbridge' ); ?></th>
							<td>
								<input type="email" name="<?php echo esc_attr( self::get_option_name() ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ?? '' ); ?>" size="50" />
								<p class="description"><?php echo esc_html__( 'Default sender email address for all emails.', 'campaignbridge' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Providers Settings Tab -->
				<div id="providers" class="tab-content">
					<table class="form-table cb-settings__form-table">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Provider', 'campaignbridge' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( self::get_option_name() . '[' . self::PROVIDER_FIELD_NAME . ']' ); ?>">
									<?php foreach ( $providers as $slug => $obj ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $provider ); ?>><?php echo esc_html( $obj->label() ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html__( 'Choose which email client or export method to use.', 'campaignbridge' ); ?></p>
							</td>
						</tr>

						<?php
						// Provider-specific fields (API Key, Audience, etc.).
						if ( isset( $providers[ $provider ] ) ) {
							$providers[ $provider ]->render_settings_fields( $settings, self::get_option_name() );
						}
						?>
					</table>
				</div>

				<?php submit_button( self::SUBMIT_BUTTON_TEXT ); ?>
			</form>
		</div>
		<?php
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
