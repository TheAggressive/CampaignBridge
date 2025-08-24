<?php
/**
 * Settings Admin Page for CampaignBridge Admin Interface.
 *
 * This class handles the plugin settings configuration page, providing administrators
 * with comprehensive control over CampaignBridge functionality, provider settings,
 * and email campaign configuration. It serves as the central hub for all plugin
 * configuration and integration settings.
 *
 * Key Features:
 * - Provider selection and configuration (Mailchimp, HTML export)
 * - API key management and validation
 * - Audience and template selection for email campaigns
 * - Dynamic settings fields based on selected provider
 * - Real-time API validation and feedback
 * - Persistent settings storage and retrieval
 * - Asset management for page-specific scripts and styles
 *
 * Provider Support:
 * - Mailchimp integration with API key validation
 * - HTML export functionality for manual email handling
 * - Extensible architecture for additional providers
 * - Provider-specific settings and configuration options
 * - Automatic provider switching and validation
 *
 * Configuration Management:
 * - WordPress options API integration
 * - Settings validation and sanitization
 * - Nonce verification for security
 * - Error handling and user feedback
 * - Settings persistence across sessions
 *
 * User Experience:
 * - Clean, organized settings interface
 * - Contextual help and descriptions
 * - Real-time validation feedback
 * - Intuitive provider selection
 * - Consistent with WordPress admin patterns
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
	 * Initialize the Settings page and set up asset management.
	 *
	 * This method sets up the Settings page by registering the necessary WordPress
	 * hooks for conditional asset loading. It ensures that page-specific CSS and
	 * JavaScript files are only loaded when viewing the Settings page, optimizing
	 * performance across the admin interface.
	 *
	 * Asset Management:
	 * - Hooks into admin_enqueue_scripts for conditional asset loading
	 * - Only loads settings-specific assets on the Settings page
	 * - Prevents unnecessary asset loading on other admin pages
	 * - Maintains optimal WordPress admin performance
	 *
	 * Page Functionality:
	 * - Plugin settings and provider configuration interface
	 * - API key management and validation
	 * - Provider selection and customization
	 * - Professional admin interface for configuration management
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
	 * the Settings page. It uses WordPress's admin_enqueue_scripts hook to detect
	 * the current admin screen and conditionally load the appropriate assets.
	 *
	 * Asset Loading Logic:
	 * - Detects current admin screen using get_current_screen()
	 * - Verifies if current page is a CampaignBridge page
	 * - Only enqueues settings-specific CSS and JavaScript
	 * - Prevents asset loading on unrelated admin pages
	 *
	 * Assets Loaded:
	 * - campaignbridge-settings.css: Settings page styling and layout
	 * - campaignbridge-settings.js: Settings page functionality and interactions
	 * - Ensures consistent styling and behavior on Settings page
	 *
	 * Performance Benefits:
	 * - Conditional asset loading prevents unnecessary resource usage
	 * - Optimizes WordPress admin performance
	 * - Reduces memory usage and page load times
	 * - Maintains clean separation of concerns
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_settings_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! \CampaignBridge\Admin\PageUtils::is_campaignbridge_page( $screen->id ) ) {
			return;
		}

		// Enqueue settings-specific assets only.
		wp_enqueue_style( 'campaignbridge-settings' );
		wp_enqueue_script( 'campaignbridge-settings' );
	}
	/**
	 * Render the complete Settings configuration page with provider management interface.
	 *
	 * This method generates the full Settings page HTML, providing administrators
	 * with comprehensive control over CampaignBridge functionality, provider settings,
	 * and email campaign configuration. It serves as the central hub for all plugin
	 * configuration and integration settings.
	 *
	 * Page Content Features:
	 * - Provider selection and configuration interface
	 * - API key management and validation
	 * - Audience and template selection for email campaigns
	 * - Dynamic settings fields based on selected provider
	 * - Professional form interface with WordPress standards
	 *
	 * Provider Support:
	 * - Mailchimp integration with API key validation
	 * - HTML export functionality for manual email handling
	 * - Extensible architecture for additional providers
	 * - Provider-specific settings and configuration options
	 * - Automatic provider switching and validation
	 *
	 * Form Functionality:
	 * - WordPress settings API integration
	 * - Nonce verification for security
	 * - Form validation and error handling
	 * - Settings persistence and retrieval
	 * - Real-time configuration updates
	 *
	 * User Experience:
	 * - Clean, organized settings interface
	 * - Contextual help and descriptions
	 * - Real-time validation feedback
	 * - Intuitive provider selection
	 * - Consistent with WordPress admin patterns
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
	 * Title Features:
	 * - Localized text for internationalization support
	 * - Clear identification of page purpose (CampaignBridge Settings)
	 * - Consistent with WordPress admin naming conventions
	 * - Professional appearance in admin interface
	 *
	 * Usage:
	 * - Displayed as the main page heading
	 * - Used in browser tab titles
	 * - Referenced in navigation and breadcrumbs
	 * - Consistent with WordPress admin standards
	 *
	 * @since 0.1.0
	 * @return string The localized page title "CampaignBridge Settings".
	 */
	public static function get_page_title(): string {
		return __( 'CampaignBridge Settings', 'campaignbridge' );
	}
}
