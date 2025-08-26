<?php
/**
 * Settings Admin Page for CampaignBridge Admin Interface.
 *
 * This class handles the plugin settings configuration page, providing administrators
 * with comprehensive control over CampaignBridge functionality, provider settings,
 * and email campaign configuration. It serves as the central hub for all plugin
 * configuration and integration settings.
 *
 * This page is essential for setting up email campaign functionality
 * and integrating with external email service providers.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\AdminPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Page: handles the plugin settings configuration interface.
 */
class SettingsPage extends AdminPage {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-settings';

	/**
	 * Initialize the Settings page and set up asset management.
	 *
	 * This method sets up the Settings page by registering the necessary WordPress
	 * hooks for conditional asset loading. It ensures that page-specific CSS and
	 * JavaScript files are only loaded when viewing the Settings page, optimizing
	 * performance across the admin interface.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Hook into admin_enqueue_scripts to conditionally load assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings_assets' ) );
	}

	/**
	 * Conditionally enqueue Settings page-specific CSS and JavaScript assets.
	 *
	 * This method ensures that Settings page assets are only loaded when viewing
	 * the Settings page. It uses the PageUtils helper to check if the current
	 * page matches this class's page_slug property.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_settings_assets(): void {
		// Only load assets on the specific Settings page.
		if ( ! \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		// Enqueue settings-specific assets only.
		wp_enqueue_style( 'campaignbridge-settings' );
	}
	/**
	 * Render the complete Settings configuration page with provider management interface.
	 *
	 * This method generates the full Settings page HTML, providing administrators
	 * with comprehensive control over CampaignBridge functionality, provider settings,
	 * and email campaign configuration. It serves as the central hub for all plugin
	 * configuration and integration settings.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		$settings  = self::get_settings();
		$providers = self::get_providers();
		$provider  = ( isset( $settings['provider'] ) && isset( $providers[ $settings['provider'] ] ) ) ? $settings['provider'] : 'mailchimp';

		self::display_messages();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'campaignbridge' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Provider', 'campaignbridge' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::get_option_name() ); ?>[provider]">
								<?php foreach ( $providers as $slug => $obj ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $provider ); ?>><?php echo esc_html( $obj->label() ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Choose which email client or export method to use.', 'campaignbridge' ); ?></p>
						</td>
					</tr>
					<?php
					// Provider-specific fields.
					if ( isset( $providers[ $provider ] ) ) {
						$providers[ $provider ]->render_settings_fields( $settings, self::get_option_name() );
					}
					?>
				</table>
				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the localized page title for the Settings configuration page.
	 *
	 * This method returns the human-readable title that will be displayed
	 * at the top of the Settings page. The title is localized for internationalization
	 * support and provides clear identification of the page's purpose.
	 *
	 * @since 0.1.0
	 * @return string The localized page title "CampaignBridge Settings".
	 */
	public static function get_page_title(): string {
		return __( 'CampaignBridge Settings', 'campaignbridge' );
	}
}
