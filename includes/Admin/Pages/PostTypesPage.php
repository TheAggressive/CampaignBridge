<?php
/**
 * Post Types Admin Page for CampaignBridge Admin Interface.
 *
 * Handles configuration of which WordPress post types are available
 * for use in CampaignBridge email campaigns.
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
	private const SUBMIT_BUTTON_TEXT = 'Save Post Types';

	/**
	 * Form field name for included post types.
	 */
	private const INCLUDED_POST_TYPES_FIELD = 'included_post_types';

	/**
	 * Initialize the Post Types page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_post_types_assets' ) );
	}

	/**
	 * Enqueue Post Types page assets.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_post_types_assets(): void {
		if ( ! \CampaignBridge\Admin\PageUtils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'campaignbridge-post-types' );
	}
	/**
	 * Render the Post Types configuration page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		$settings = self::get_settings();
		self::display_messages();
		self::render_post_types_form( $settings );
	}

	/**
	 * Get the list of post types that should be included.
	 *
	 * @param array $settings Current settings.
	 * @return array List of post type names that are included.
	 */
	private static function get_included_post_types( array $settings ): array {
		$public_types   = get_post_types( array( 'public' => true ), 'objects' );
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] )
			? array_map( 'sanitize_key', $settings['exclude_post_types'] )
			: array();

		$included_names = array();
		foreach ( $public_types as $obj ) {
			$included_names[] = $obj->name;
		}

		return array_values( array_diff( $included_names, $excluded_types ) );
	}

	/**
	 * Render the post types form HTML.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_post_types_form( array $settings ): void {
		$public_types   = get_post_types( array( 'public' => true ), 'objects' );
		$included_types = self::get_included_post_types( $settings );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_FIELD );
				wp_nonce_field( self::NONCE_ACTION );
				?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Included post types', 'campaignbridge' ); ?></th>
						<td>
							<p class="description"><?php echo esc_html__( 'Select which public post types can be used in CampaignBridge.', 'campaignbridge' ); ?></p>
							<div class="cb-post-types__switches-box">
								<div class="cb-post-types__switches-group">
									<div class="cb-post-types__switches-grid">
										<?php foreach ( $public_types as $obj ) : ?>
											<?php $checked = in_array( $obj->name, $included_types, true ); ?>
											<label class="cb-post-types__switch">
												<input type="checkbox" name="<?php echo esc_attr( self::get_option_name() . '[' . self::INCLUDED_POST_TYPES_FIELD . '][]' ); ?>" value="<?php echo esc_attr( $obj->name ); ?>" <?php checked( $checked ); ?> />
												<span class="cb-post-types__slider" aria-hidden="true"></span>
												<span class="cb-post-types__switch-label"><?php echo esc_html( $obj->labels->singular_name ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
							<p class="description"><?php echo esc_html__( 'Unchecked types will be unavailable when selecting posts.', 'campaignbridge' ); ?></p>
						</td>
					</tr>
				</table>

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
		return __( 'Post Types', 'campaignbridge' );
	}
}
