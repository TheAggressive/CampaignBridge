<?php
/**
 * Post Types Admin Page for CampaignBridge
 *
 * Handles the Post Types configuration page.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post Types Page: handles the post type configuration interface.
 */
class PostTypesPage extends AdminPage {
	/**
	 * Render the Post Types page.
	 *
	 * @return void
	 */
	public static function render(): void {
		$settings = self::get_settings();
		self::display_messages();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'campaignbridge' ); ?>
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
	 * Get the page title.
	 *
	 * @return string
	 */
	public static function get_page_title(): string {
		return __( 'Post Types', 'campaignbridge' );
	}
}
