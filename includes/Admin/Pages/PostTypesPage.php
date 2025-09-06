<?php
/**
 * Post Types Admin Page for CampaignBridge Admin Interface.
 *
 * This class handles the Post Types configuration page, which allows administrators
 * to control which WordPress post types are available for use in CampaignBridge
 * email campaigns. It provides an intuitive interface for managing post type
 * inclusion/exclusion with real-time updates and validation.
 *
 * This page serves as the main configuration interface for determining
 * which content types can be included in email campaigns, making it
 * a critical component for content management workflows.
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
 * Post Types Page: handles the post type configuration interface.
 */
class PostTypesPage extends AdminPage {
	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-post-types';

	/**
	 * Initialize the Post Types page and set up asset management.
	 *
	 * This method sets up the Post Types page by registering the necessary WordPress
	 * hooks for conditional asset loading. It ensures that page-specific CSS and
	 * JavaScript files are only loaded when viewing the Post Types page, optimizing
	 * performance across the admin interface.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Hook into admin_enqueue_scripts to conditionally load assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_post_types_assets' ) );
	}

	/**
	 * Conditionally enqueue Post Types page-specific CSS and JavaScript assets.
	 *
	 * This method ensures that Post Types page assets are only loaded when viewing
	 * the Post Types page. It uses the PageUtils helper to check if the current
	 * page matches this class's page_slug property.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_post_types_assets(): void {
		// Only load assets on the specific Post Types page.
		if ( ! \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		// Enqueue post-types-specific assets only.
		wp_enqueue_style( 'campaignbridge-post-types' );
	}
	/**
	 * Render the complete Post Types configuration page with dynamic form interface.
	 *
	 * This method generates the full Post Types page HTML, providing administrators
	 * with an intuitive interface for configuring which WordPress post types are
	 * available for use in CampaignBridge email campaigns. It displays all public
	 * post types with toggle switches for easy configuration.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		$settings = self::get_settings();
		self::display_messages();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'campaignbridge' );
				wp_nonce_field( 'campaignbridge-options' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Included post types', 'campaignbridge' ); ?></th>
						<td>
							<p class="description"><?php echo esc_html__( 'Select which public post types can be used in CampaignBridge.', 'campaignbridge' ); ?></p>
							<div class="cb-switches-box">
								<div class="cb-switches-group">
									<div class="cb-switches-grid">
										<?php
										$public_types   = get_post_types( array( 'public' => true ), 'objects' );
										$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
										$included_names = array();
										foreach ( $public_types as $obj ) {
											$included_names[] = $obj->name;
										}
										$included_names = array_values( array_diff( $included_names, $excluded_types ) );

										foreach ( $public_types as $obj ) :
											$checked = in_array( $obj->name, $included_names, true );
											?>
											<label class="cb-switch">
												<input type="checkbox" name="<?php echo esc_attr( self::get_option_name() ); ?>[included_post_types][]" value="<?php echo esc_attr( $obj->name ); ?>" <?php checked( $checked ); ?> />
												<span class="cb-slider" aria-hidden="true"></span>
												<span class="cb-switch-label"><?php echo esc_html( $obj->labels->singular_name ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
							<p class="description"><?php echo esc_html__( 'Unchecked types will be unavailable when selecting posts.', 'campaignbridge' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Post Types' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the localized page title for the Post Types configuration page.
	 *
	 * This method returns the human-readable title that will be displayed
	 * at the top of the Post Types page. The title is localized for internationalization
	 * support and provides clear identification of the page's purpose.
	 *
	 * @since 0.1.0
	 * @return string The localized page title "Post Types".
	 */
	public static function get_page_title(): string {
		return __( 'Post Types', 'campaignbridge' );
	}
}
