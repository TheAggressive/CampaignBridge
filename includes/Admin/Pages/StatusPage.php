<?php
/**
 * Status Admin Page for CampaignBridge
 *
 * Provides development debugging and system status information.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\AdminPage;
use CampaignBridge\Admin\PageUtils;
use CampaignBridge\Blocks\Blocks;
use CampaignBridge\PostTypes\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status Page: provides development debugging and system status information.
 */
class StatusPage extends AdminPage {
	/**
	 * Initialize the Status page.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Hook into admin_enqueue_scripts to conditionally load assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_status_assets' ) );
	}

	/**
	 * Enqueue status page assets.
	 *
	 * @return void
	 */
	public static function enqueue_status_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! PageUtils::is_campaignbridge_page( $screen->id ) ) {
			return;
		}

		// Enqueue status-specific assets only.
		wp_enqueue_style( 'campaignbridge-status' );
		wp_enqueue_script( 'campaignbridge-status' );
	}

	/**
	 * Render the Status page.
	 *
	 * @return void
	 */
	public static function render(): void {
		self::display_messages();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>
			<hr class="wp-header-end">

			<div class="cb-status-overview">
				<?php self::render_system_status(); ?>
				<?php self::render_block_status(); ?>
				<?php self::render_template_status(); ?>
				<?php self::render_plugin_status(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the page title.
	 *
	 * @return string
	 */
	public static function get_page_title(): string {
		return __( 'System Status', 'campaignbridge' );
	}

	/**
	 * Render system status overview.
	 *
	 * @return void
	 */
	private static function render_system_status(): void {
		?>
		<div class="cb-status-section">
			<h2><?php esc_html_e( 'System Status', 'campaignbridge' ); ?></h2>
			<div class="cb-status-grid">
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'WordPress Version', 'campaignbridge' ); ?></h3>
					<p class="cb-status-value"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></p>
				</div>
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'PHP Version', 'campaignbridge' ); ?></h3>
					<p class="cb-status-value"><?php echo esc_html( PHP_VERSION ); ?></p>
				</div>
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Plugin Version', 'campaignbridge' ); ?></h3>
					<p class="cb-status-value"><?php echo esc_html( CB_VERSION ); ?></p>
				</div>
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Debug Mode', 'campaignbridge' ); ?></h3>
					<p class="cb-status-value">
						<?php echo WP_DEBUG ? '<span class="cb-status-warning">Enabled</span>' : '<span class="cb-status-ok">Disabled</span>'; ?>
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
		<div class="cb-status-section">
			<h2><?php esc_html_e( 'Block System Status', 'campaignbridge' ); ?></h2>

			<div class="cb-status-grid">
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Blocks Built', 'campaignbridge' ); ?></h3>
					<?php if ( Blocks::blocks_available() ) : ?>
						<p class="cb-status-value cb-status-ok">✅ <?php esc_html_e( 'Yes', 'campaignbridge' ); ?></p>
						<p class="cb-status-detail"><?php echo esc_html( CB_PATH . 'dist/blocks/' ); ?></p>
					<?php else : ?>
						<p class="cb-status-value cb-status-error">❌ <?php esc_html_e( 'No', 'campaignbridge' ); ?></p>
						<p class="cb-status-detail"><?php esc_html_e( 'Run: pnpm build:blocks', 'campaignbridge' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Registered Blocks', 'campaignbridge' ); ?></h3>
					<?php
					$registered_blocks = Blocks::get_registered_blocks();
					$block_count       = count( $registered_blocks );
					?>
					<p class="cb-status-value"><?php echo esc_html( $block_count ); ?></p>
					<?php if ( $block_count > 0 ) : ?>
						<p class="cb-status-detail"><?php esc_html_e( 'Blocks found and registered', 'campaignbridge' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $registered_blocks ) ) : ?>
				<div class="cb-status-details">
					<h4><?php esc_html_e( 'Registered Block Details', 'campaignbridge' ); ?></h4>
					<div class="cb-block-list">
						<?php foreach ( $registered_blocks as $block_name ) : ?>
							<div class="cb-block-item">
								<code><?php echo esc_html( $block_name ); ?></code>
								<span class="cb-block-status cb-status-ok">✅</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			// Check specific blocks
			$specific_blocks = array(
				'campaignbridge/email-template',
				'campaignbridge/email-post-slot',
				'campaignbridge/email-post-title',
				'campaignbridge/email-post-excerpt',
				'campaignbridge/email-post-image',
				'campaignbridge/email-post-button',
			);
			?>
			<div class="cb-status-details">
				<h4><?php esc_html_e( 'Required Block Status', 'campaignbridge' ); ?></h4>
				<div class="cb-block-list">
					<?php foreach ( $specific_blocks as $block_name ) : ?>
						<?php
						$is_registered = Blocks::is_block_registered( $block_name );
						$status_class  = $is_registered ? 'cb-status-ok' : 'cb-status-error';
						$status_icon   = $is_registered ? '✅' : '❌';
						?>
						<div class="cb-block-item">
							<code><?php echo esc_html( $block_name ); ?></code>
							<span class="cb-block-status <?php echo esc_attr( $status_class ); ?>"><?php echo $status_icon; ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
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
		<div class="cb-status-section">
			<h2><?php esc_html_e( 'Template System Status', 'campaignbridge' ); ?></h2>

			<div class="cb-status-grid">
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Total Templates', 'campaignbridge' ); ?></h3>
					<?php
					$total_templates = wp_count_posts( EmailTemplate::POST_TYPE );
					$total_count     = $total_templates->publish ?? 0;
					?>
					<p class="cb-status-value"><?php echo esc_html( $total_count ); ?></p>
				</div>

				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Active Templates', 'campaignbridge' ); ?></h3>
					<?php
					$active_templates = EmailTemplate::get_active_templates();
					$active_count     = count( $active_templates );
					?>
					<p class="cb-status-value"><?php echo esc_html( $active_count ); ?></p>
				</div>

				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Template Categories', 'campaignbridge' ); ?></h3>
					<?php
					$categories = EmailTemplate::get_template_categories();
					$cat_count  = count( $categories );
					?>
					<p class="cb-status-value"><?php echo esc_html( $cat_count ); ?></p>
				</div>
			</div>

			<?php if ( $active_count > 0 ) : ?>
				<div class="cb-status-details">
					<h4><?php esc_html_e( 'Recent Active Templates', 'campaignbridge' ); ?></h4>
					<div class="cb-template-list">
						<?php foreach ( array_slice( $active_templates, 0, 5 ) as $template ) : ?>
							<div class="cb-template-item">
								<strong><?php echo esc_html( $template->post_title ); ?></strong>
								<span class="cb-template-meta">
									<?php
									$category = get_post_meta( $template->ID, '_cb_template_category', true ) ?: 'general';
									echo esc_html( $categories[ $category ] ?? $category );
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
		<div class="cb-status-section">
			<h2><?php esc_html_e( 'Plugin Configuration', 'campaignbridge' ); ?></h2>

			<div class="cb-status-grid">
				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Provider', 'campaignbridge' ); ?></h3>
					<p class="cb-status-value"><?php echo esc_html( $settings['provider'] ?? 'mailchimp' ); ?></p>
				</div>

				<div class="cb-status-card">
					<h3><?php esc_html_e( 'API Key', 'campaignbridge' ); ?></h3>
					<?php
					$api_key    = $settings['api_key'] ?? '';
					$api_status = ! empty( $api_key ) ? 'cb-status-ok' : 'cb-status-warning';
					$api_text   = ! empty( $api_key ) ? 'Set' : 'Not Set';
					?>
					<p class="cb-status-value <?php echo esc_attr( $api_status ); ?>">
						<?php echo ! empty( $api_key ) ? '✅' : '⚠️'; ?> <?php echo esc_html( $api_text ); ?>
					</p>
				</div>

				<div class="cb-status-card">
					<h3><?php esc_html_e( 'Post Types', 'campaignbridge' ); ?></h3>
					<?php
					$excluded_types = $settings['exclude_post_types'] ?? array();
					$public_types   = get_post_types( array( 'public' => true ), 'names' );
					$included_count = count( array_diff( $public_types, $excluded_types ) );
					?>
					<p class="cb-status-value"><?php echo esc_html( $included_count ); ?></p>
					<p class="cb-status-detail"><?php esc_html_e( 'Available for campaigns', 'campaignbridge' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
