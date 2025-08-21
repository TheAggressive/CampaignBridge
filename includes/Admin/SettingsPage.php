<?php
/**
 * Settings Admin Page for CampaignBridge
 *
 * Handles the plugin settings configuration page.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Page: handles the plugin settings configuration interface.
 */
class SettingsPage extends AdminPage {
	/**
	 * Render the Settings page.
	 *
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
	 * Get the page title.
	 *
	 * @return string
	 */
	public static function get_page_title(): string {
		return __( 'CampaignBridge Settings', 'campaignbridge' );
	}
}
