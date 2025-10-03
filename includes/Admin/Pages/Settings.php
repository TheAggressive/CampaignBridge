<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Settings Entry Point for CampaignBridge Admin Interface.
 *
 * Main entry point for the plugin settings page that handles tab navigation,
 * form submission, and delegates to specific tab implementations.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\Admin;
use CampaignBridge\Admin\Pages\Tabs\Settings_Tab_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings: Main entry point for the plugin settings configuration interface.
 *
 * This class serves as the entry point for the settings page and handles:
 * - Page initialization and asset loading
 * - Form submission and processing
 * - Tab navigation and rendering
 * - Delegation to specific tab implementations
 */
class Settings extends Admin {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-settings';

	/**
	 * Submit button text.
	 */
	private const SUBMIT_BUTTON_TEXT = 'Save Settings';

	/**
	 * Initialize the Settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Initialize default tabs.
		Settings_Tab_Manager::init_default_tabs();

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submission' ) );
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

		self::render_settings_page();
	}

	/**
	 * Handle form submission and validation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function handle_form_submission(): void {
		if ( ! isset( $_POST['submit'] ) ) {
			return;
		}

		// Get current tab for context-aware processing.
		$current_tab = Settings_Tab_Manager::get_current_tab();

		// Get submitted settings.
		$new_settings = $_POST[ Settings_Manager::get_option_name() ] ?? array();

		// Update settings through the manager (which handles nonce verification).
		$success = Settings_Manager::update_settings( $new_settings, $current_tab );

		if ( $success ) {
			// Redirect to avoid form resubmission and preserve current tab.
			$redirect_url = add_query_arg(
				array(
					'page'             => static::get_page_slug(),
					'tab'              => $current_tab,
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			);
			wp_redirect( $redirect_url );
			exit;
		} else {
			// Settings save failed - redirect back to settings page with error and preserve tab.
			$redirect_url = add_query_arg(
				array(
					'page'           => static::get_page_slug(),
					'tab'            => $current_tab,
					'settings-error' => 'true',
				),
				admin_url( 'admin.php' )
			);
			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Register settings sections and fields using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @return void.
	 */
	public static function register_settings_sections(): void {
		// Register sections based on current tab.
		$current_tab = Settings_Tab_Manager::get_current_tab();

		// Get the tab class and register its settings.
		$tab_class = Settings_Tab_Manager::get_tab_class( $current_tab );
		if ( $tab_class && class_exists( $tab_class ) ) {
			$tab_class::register_settings();
		}
	}

	/**
	 * Sanitize settings input.
	 *
	 * This method is a compatibility wrapper that delegates to the Settings_Manager.
	 *
	 * @since 0.1.0
	 * @param array $input Raw input data from the form.
	 * @return array Sanitized settings data.
	 */
	public static function sanitize_settings( array $input ): array {
		// Delegate sanitization to the Settings_Manager.
		return Settings_Manager::sanitize_settings( $input );
	}

	/**
	 * Render the complete settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function render_settings_page(): void {
		$current_tab  = Settings_Tab_Manager::get_current_tab();
		$nonce_action = Settings_Manager::get_nonce_action( $current_tab );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<?php
			// Display validation errors if any.
			Settings_Manager::display_validation_errors();

			// Display success messages.
			self::display_messages();

			// Display save error if present.
			if ( isset( $_GET['settings-error'] ) && 'true' === $_GET['settings-error'] ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Settings could not be saved. Please try again.', 'campaignbridge' ) . '</p></div>';
			}
			?>

			<form method="post" action="">
				<?php wp_nonce_field( $nonce_action, 'campaignbridge_settings_nonce' ); ?>

				<?php
				// Set option_page for compatibility with old Settings_Handler.
				$option_page = 'general' === $current_tab ? 'campaignbridge_general' : 'campaignbridge_providers';
				?>
				<input type="hidden" name="option_page" value="<?php echo esc_attr( $option_page ); ?>" />

				<?php
				// Render tab navigation.
				Settings_Tab_Manager::render_navigation();

				// Render current tab content.
				Settings_Tab_Manager::render_current_tab();
				?>

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
