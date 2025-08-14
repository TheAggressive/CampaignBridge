<?php
/**
 * CampaignBridge Admin UI.
 *
 * Renders the plugin admin tabs and enqueues assets for the admin page.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Core\Dispatcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * Admin UI: menu page rendering and asset enqueues.
 *
 * Renders the plugin admin tabs (Posts/Templates/Settings), provides
 * the post selection and mapping UI, and enqueues built assets with
 * dependencies and versioning via generated asset files.
 */
class UI {
	/**
	 * Option key used to store plugin settings.
	 *
	 * @var string
	 */
	private static $option_name = 'campaignbridge_settings';

	/**
	 * Registered providers map indexed by slug.
	 *
	 * @var array<string,object>
	 */
	private static $providers = array();

	/**
	 * Initialize shared state.
	 *
	 * @param string $option_name Options key used by the plugin.
	 * @param array  $providers   Registered providers map.
	 * @return void
	 */
	public static function init( $option_name, $providers ) {
		self::$option_name = (string) $option_name;
		self::$providers   = is_array( $providers ) ? $providers : array();
	}

	/**
	 * Enqueue admin scripts and styles on the plugin page.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_campaignbridge' !== $screen->id ) {
			return;
		}

		// Load asset metadata for proper dependencies and versioning.
		$script_asset      = array(
			'dependencies' => array(),
			'version'      => '1.0.0',
		);
		$script_asset_path = dirname( __DIR__, 2 ) . '/dist/scripts/campaignbridge.asset.php';
		if ( file_exists( $script_asset_path ) ) {
			$maybe = include $script_asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $maybe ) ) {
				$script_asset = $maybe;
			}
		}

		$base_file = dirname( __DIR__, 2 ) . '/campaignbridge.php';
		// Ensure REST globals (wpApiSettings) are present for our REST calls.
		$deps = array_unique( array_merge( (array) $script_asset['dependencies'], array( 'wp-api' ) ) );
		wp_enqueue_script(
			'campaignbridge-admin',
			plugins_url( 'dist/scripts/campaignbridge.js', $base_file ),
			$deps,
			$script_asset['version'],
			true
		);

		$style_version    = '1.0.0';
		$style_asset_path = dirname( __DIR__, 2 ) . '/dist/styles/styles.asset.php';
		if ( file_exists( $style_asset_path ) ) {
			$maybe = include $style_asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $maybe ) && isset( $maybe['version'] ) ) {
				$style_version = (string) $maybe['version'];
			}
		}
		wp_enqueue_style(
			'campaignbridge-admin',
			plugins_url( 'dist/styles/styles.css', $base_file ),
			array(),
			$style_version
		);
	}

	/**
	 * Render the main plugin page with tab navigation.
	 *
	 * @return void
	 */
	public static function render_page() {
		$settings   = get_option( self::$option_name );
		$provider   = ( isset( $settings['provider'] ) && isset( self::$providers[ $settings['provider'] ] ) ) ? $settings['provider'] : 'mailchimp';
		$nav_nonce  = wp_create_nonce( 'campaignbridge_nav' );
		$active_tab = 'templates';
		if ( isset( $_GET['tab'], $_GET['cbnav'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab_raw = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$cbnav   = sanitize_text_field( wp_unslash( $_GET['cbnav'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( wp_verify_nonce( $cbnav, 'campaignbridge_nav' ) ) {
				$active_tab = $tab_raw;
			}
		}
		?>
	<div class="wrap" data-cbnav="<?php echo esc_attr( $nav_nonce ); ?>">
		<h1>CampaignBridge</h1>
		<h2 class="nav-tab-wrapper cb-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=campaignbridge&tab=templates&cbnav=' . $nav_nonce ) ); ?>" class="nav-tab <?php echo ( 'templates' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Templates</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=campaignbridge&tab=types&cbnav=' . $nav_nonce ) ); ?>" class="nav-tab <?php echo ( 'types' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Post Types</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=campaignbridge&tab=settings&cbnav=' . $nav_nonce ) ); ?>" class="nav-tab <?php echo ( 'settings' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Settings</a>
		</h2>

		<?php
		if ( in_array( $active_tab, array( 'settings', 'types' ), true ) ) {
			if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_settings_error( 'campaignbridge_messages', 'campaignbridge_message', __( 'Settings saved.', 'campaignbridge' ), 'updated' );
			}
			settings_errors( 'campaignbridge_messages' );
		}
		?>

		<?php if ( 'settings' === $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'campaignbridge' ); ?>
			<table class="form-table">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Provider', 'campaignbridge' ); ?></th>
				<td>
				<select name="<?php echo esc_attr( self::$option_name ); ?>[provider]">
					<?php foreach ( self::$providers as $slug => $obj ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $provider ); ?>><?php echo esc_html( $obj->label() ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html__( 'Choose which email client or export method to use.', 'campaignbridge' ); ?></p>
				</td>
			</tr>
			<?php
			// Provider-specific fields.
			if ( isset( self::$providers[ $provider ] ) ) {
				self::$providers[ $provider ]->render_settings_fields( $settings, self::$option_name );
			}
			?>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php elseif ( 'templates' === $active_tab ) : ?>
		<div class="cb-field">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=cb_template' ) ); ?>">Add New Template</a>
		</div>

		<div class="cb-field">
			<label for="campaignbridge-template-select" class="cb-label">Template</label>
			<select id="campaignbridge-template-select" class="cb-input-wide">
				<option value="">— Select a template —</option>
				<?php
				$templates = get_posts(
					array(
						'post_type'   => 'cb_template',
						'numberposts' => -1,
						'orderby'     => 'date',
						'order'       => 'DESC',
					)
				);
				foreach ( (array) $templates as $tpl ) {
					printf(
						'<option value="%1$d" %3$s>%2$s</option>',
						(int) $tpl->ID,
						esc_html( get_the_title( $tpl ) ),
						selected( isset( $_GET['tpl'] ) ? (int) $_GET['tpl'] : 0, (int) $tpl->ID, false ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					);
				}
				?>
			</select>
			<p class="description">Design the email template here. Use Email Post Slot blocks.</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=cb_template' ) ); ?>" target="_blank" class="button"><?php echo esc_html__( 'Open New in Tab', 'campaignbridge' ); ?></a>
					<button type="button" class="button" id="campaignbridge-new-template"><?php echo esc_html__( 'Create New (inline)', 'campaignbridge' ); ?></button>
				</p>
		</div>

		<div class="cb-field cb-two-pane">
			<div class="cb-template-pane">
				<iframe id="campaignbridge-template-iframe" class="cb-iframe" src="<?php echo isset( $_GET['tpl'] ) ? esc_url( admin_url( 'post.php?post=' . (int) $_GET['tpl'] . '&action=edit&cb_iframe=1' ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"></iframe>
			</div>
			<div id="campaignbridge-live-preview" class="cb-preview-pane">
				<div class="cb-preview-toolbar">
					<strong class="cb-grow"><?php echo esc_html__( 'Live Preview', 'campaignbridge' ); ?></strong>
					<label><input type="radio" name="cbPreviewMode" value="rendered" checked> <?php echo esc_html__( 'Rendered', 'campaignbridge' ); ?></label>
					<label><input type="radio" name="cbPreviewMode" value="html"> <?php echo esc_html__( 'HTML', 'campaignbridge' ); ?></label>
					<button type="button" class="button" id="cb-refresh-preview"><?php echo esc_html__( 'Refresh', 'campaignbridge' ); ?></button>
					<button type="button" class="button cb-hidden" id="cb-copy-html"><?php echo esc_html__( 'Copy HTML', 'campaignbridge' ); ?></button>
				</div>
				<div id="cb-preview-rendered-wrap" class="cb-embed-frame">
					<iframe id="cb-preview-frame" class="cb-iframe"></iframe>
				</div>
				<textarea id="cb-preview-html" class="cb-codebox" style="display:none;"></textarea>
			</div>
		</div>

			<?php if ( 'mailchimp' === $provider ) : ?>
		<div class="cb-field">
			<button type="button" class="button" id="campaignbridge-show-sections"><?php echo esc_html__( 'Show Mailchimp Template Sections', 'campaignbridge' ); ?></button>
			<div id="campaignbridge-sections" class="cb-hidden cb-sections-box"></div>
		</div>
		<?php endif; ?>

			<?php /* Mapping section removed: block templates handle content inline. */ ?>
		<?php elseif ( 'types' === $active_tab ) : ?>
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
									<input type="checkbox" name="<?php echo esc_attr( self::$option_name ); ?>[included_post_types][]" value="<?php echo esc_attr( $obj->name ); ?>" <?php checked( $checked ); ?> />
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
		<?php else : ?>
			<?php /* Posts tab removed in favor of block-based templates. */ ?>
		<?php endif; ?>
	</div>
		<?php
	}
}
