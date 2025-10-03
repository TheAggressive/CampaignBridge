<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Status for CampaignBridge Admin Interface.
 *
 * This class handles the System Status page, providing comprehensive debugging
 * information, system health checks, and development insights for the
 * CampaignBridge plugin. It serves as a diagnostic tool for administrators
 * and developers to troubleshoot issues and monitor plugin performance.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\Admin;
use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Post_Types\Post_Type_Email_Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status: provides development debugging and system status information.
 */
class Status extends Admin {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-status';

	/**
	 * Initialize the Status page and set up asset management.
	 *
	 * This method sets up the Status page by registering the necessary WordPress
	 * hooks for conditional asset loading. It ensures that page-specific CSS and
	 * JavaScript files are only loaded when viewing the Status page, optimizing
	 * performance across the admin interface.
	 *
	 * Asset Management:
	 * - Hooks into admin_enqueue_scripts for conditional asset loading
	 * - Only loads status-specific assets on the Status page
	 * - Prevents unnecessary asset loading on other admin pages
	 * - Maintains optimal WordPress admin performance
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Hook into admin_enqueue_scripts to conditionally load assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_status_assets' ) );
	}

	/**
	 * Conditionally enqueue Status page-specific CSS and JavaScript assets.
	 *
	 * This method ensures that Status page assets are only loaded when viewing
	 * the Status page. It uses the Page_Utils helper to check if the current
	 * page matches this class's page_slug property.
	 *
	 * Asset Management:
	 * - Hooks into admin_enqueue_scripts for conditional asset loading
	 * - Only loads status-specific assets on the Status page
	 * - Prevents unnecessary asset loading on other admin pages
	 * - Maintains optimal WordPress admin performance
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_status_assets(): void {
		// Only load assets on the specific Status page.
		if ( ! \CampaignBridge\Admin\Page_Utils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		// Enqueue status-specific assets only.
		wp_enqueue_style( 'campaignbridge-status' );
	}

	/**
	 * Render the complete Status page with comprehensive system information.
	 *
	 * This method generates the full Status page HTML, displaying comprehensive
	 * information about the CampaignBridge plugin, WordPress environment, block
	 * system status, email template system, and plugin configuration. It provides
	 * administrators with a complete overview of system health and functionality.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'campaignbridge' ) );
		}

		self::display_messages();
		?>
		<div class="wrap cb-status">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>
			<hr class="wp-header-end">

			<div class="cb-status__overview">
				<?php self::render_system_status(); ?>
				<?php self::render_block_status(); ?>
				<?php self::render_template_status(); ?>
				<?php self::render_plugin_status(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the localized page title for the Status page.
	 *
	 * This method returns the human-readable title that will be displayed
	 * at the top of the Status page. The title is localized for internationalization
	 * support and provides clear identification of the page's purpose.
	 *
	 * @since 0.1.0
	 * @return string The localized page title "System Status".
	 */
	public static function get_page_title(): string {
		return __( 'System Status', 'campaignbridge' );
	}

	/**
	 * Render the system status overview section with environment information.
	 *
	 * This method generates the system status section that displays comprehensive
	 * information about the WordPress environment, PHP configuration, and plugin
	 * version details. It provides administrators with essential system information
	 * for troubleshooting and environment assessment.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function render_system_status(): void {
		?>
		<div class="cb-status__section">
			<h2><?php esc_html_e( 'System Status', 'campaignbridge' ); ?></h2>
			<div class="cb-status__grid">
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'WordPress Version', 'campaignbridge' ); ?></h3>
					<p class="cb-status__value"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></p>
				</div>
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'PHP Version', 'campaignbridge' ); ?></h3>
					<p class="cb-status__value"><?php echo esc_html( PHP_VERSION ); ?></p>
				</div>
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Plugin Version', 'campaignbridge' ); ?></h3>
					<p class="cb-status__value"><?php echo esc_html( CB_VERSION ); ?></p>
				</div>
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Debug Mode', 'campaignbridge' ); ?></h3>
					<p class="cb-status__value">
						<?php echo WP_DEBUG ? '<span class="cb-status__value--warning">Enabled</span>' : '<span class="cb-status__value--ok">Disabled</span>'; ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render block system status.
	 *
	 * @return void
	 */
	private static function render_block_status(): void {
		?>
		<div class="cb-status__section">
			<h2><?php esc_html_e( 'Block System Status', 'campaignbridge' ); ?></h2>

			<div class="cb-status__grid">
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Blocks Built', 'campaignbridge' ); ?></h3>
					<?php if ( Blocks::blocks_available() ) : ?>
						<p class="cb-status__value cb-status__value--ok">✅ <?php esc_html_e( 'Yes', 'campaignbridge' ); ?></p>
						<p class="cb-status__detail"><?php echo esc_html( CB_PATH . 'dist/blocks/' ); ?></p>
					<?php else : ?>
						<p class="cb-status__value cb-status__value--error">❌ <?php esc_html_e( 'No', 'campaignbridge' ); ?></p>
						<p class="cb-status__detail"><?php esc_html_e( 'Run: pnpm build:blocks', 'campaignbridge' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Registered Blocks', 'campaignbridge' ); ?></h3>
					<?php
					$registered_blocks = Blocks::get_registered_blocks();
					$block_count       = count( $registered_blocks );
					?>
					<p class="cb-status__value"><?php echo esc_html( $block_count ); ?></p>
					<?php if ( $block_count > 0 ) : ?>
						<p class="cb-status__detail"><?php esc_html_e( 'Blocks found and registered', 'campaignbridge' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $registered_blocks ) ) : ?>
				<div class="cb-status__details">
					<h4><?php esc_html_e( 'Registered Block Details', 'campaignbridge' ); ?></h4>
					<div class="cb-status__block-list">
						<?php foreach ( $registered_blocks as $block_name ) : ?>
							<div class="cb-status__block-item">
								<code><?php echo esc_html( $block_name ); ?></code>
								<span class="cb-status__block-status cb-status__value--ok">✅</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render template system status.
	 *
	 * @return void
	 */
	private static function render_template_status(): void {
		?>
		<div class="cb-status__section">
			<h2><?php esc_html_e( 'Template System Status', 'campaignbridge' ); ?></h2>

			<div class="cb-status__grid">
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Total Templates', 'campaignbridge' ); ?></h3>
					<?php
					$total_templates = wp_count_posts( Post_Type_Email_Template::POST_TYPE );
					$total_count     = $total_templates->publish ?? 0;
					?>
					<p class="cb-status__value"><?php echo esc_html( $total_count ); ?></p>
				</div>

				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Email Templates', 'campaignbridge' ); ?></h3>
					<?php
					$templates      = Post_Type_Email_Template::get_templates();
					$template_count = count( $templates );
					?>
					<p class="cb-status__value"><?php echo esc_html( $template_count ); ?></p>
				</div>

				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Template Categories', 'campaignbridge' ); ?></h3>
					<?php
					$categories = Post_Type_Email_Template::get_template_categories();
					$cat_count  = count( $categories );
					?>
					<p class="cb-status__value"><?php echo esc_html( $cat_count ); ?></p>
				</div>
			</div>

			<?php if ( $template_count > 0 ) : ?>
				<div class="cb-status__details">
					<h4><?php esc_html_e( 'Recent Templates', 'campaignbridge' ); ?></h4>
					<div class="cb-status__template-list">
						<?php foreach ( array_slice( $templates, 0, 5 ) as $template ) : ?>
							<div class="cb-status__template-item">
								<strong><?php echo esc_html( $template->post_title ); ?></strong>
								<span class="cb-status__template-meta">
									<?php
									echo esc_html( 'General' );
									?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render plugin configuration status.
	 *
	 * @return void
	 */
	private static function render_plugin_status(): void {
		$settings = self::get_settings();
		?>
		<div class="cb-status__section">
			<h2><?php esc_html_e( 'Plugin Configuration', 'campaignbridge' ); ?></h2>

			<div class="cb-status__grid">
				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Provider', 'campaignbridge' ); ?></h3>
					<p class="cb-status__value"><?php echo esc_html( $settings['provider'] ?? 'html' ); ?></p>
				</div>

				<div class="cb-status__card">
					<h3><?php esc_html_e( 'API Key', 'campaignbridge' ); ?></h3>
					<?php
					$api_key    = $settings['api_key'] ?? '';
					$api_status = ! empty( $api_key ) ? 'cb-status__value--ok' : 'cb-status__value--warning';
					$api_text   = ! empty( $api_key ) ? 'Set' : 'Not Set';
					?>
					<p class="cb-status__value <?php echo esc_attr( $api_status ); ?>">
						<?php echo ! empty( $api_key ) ? '✅' : '⚠️'; ?> <?php echo esc_html( $api_text ); ?>
					</p>
				</div>

				<div class="cb-status__card">
					<h3><?php esc_html_e( 'Post Types', 'campaignbridge' ); ?></h3>
					<?php
					$excluded_types_raw = $settings['exclude_post_types'] ?? array();
					$excluded_types     = is_array( $excluded_types_raw ) ? $excluded_types_raw : array();
					$public_types       = get_post_types( array( 'public' => true ), 'names' );
					$included_count     = count( array_diff( $public_types, $excluded_types ) );
					?>
					<p class="cb-status__value"><?php echo esc_html( $included_count ); ?></p>
					<p class="cb-status__detail"><?php esc_html_e( 'Available for campaigns', 'campaignbridge' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
