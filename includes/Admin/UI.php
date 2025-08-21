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
	private static string $option_name = 'campaignbridge_settings';

	/**
	 * Registered providers map indexed by slug.
	 *
	 * @var array<string,object>
	 */
	private static array $providers = array();

	/**
	 * Initialize shared state.
	 *
	 * @param string $option_name Options key used by the plugin.
	 * @param array  $providers   Registered providers map.
	 * @return void
	 */
	public static function init( string $option_name, array $providers ): void {
		self::$option_name = $option_name;
		self::$providers   = $providers;
	}

	/**
	 * Enqueue admin scripts and styles on the plugin page.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, array( 'toplevel_page_campaignbridge', 'campaignbridge_page_campaignbridge-post-types', 'campaignbridge_page_campaignbridge-settings' ), true ) ) {
			return;
		}

		// Special handling for Template Manager page to load block editor.
		if ( $screen && 'toplevel_page_campaignbridge' === $screen->id ) {
			self::enqueue_block_editor_assets();
			// Don't load the old campaignbridge.js on the Template Manager page.
			return;
		}

		// Load asset metadata for proper dependencies and versioning.
		$script_asset      = array(
			'dependencies' => array(),
			'version'      => '1.0.0',
		);
		$script_asset_path = CB_PATH . 'dist/scripts/campaignbridge.asset.php';
		if ( file_exists( $script_asset_path ) ) {
			$maybe = include $script_asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $maybe ) ) {
				$script_asset = $maybe;
			}
		}

		// Ensure REST globals (wpApiSettings) are present for our REST calls.
		$deps = array_unique( array_merge( (array) $script_asset['dependencies'], array( 'wp-api' ) ) );
		wp_enqueue_script(
			'campaignbridge-admin',
			CB_URL . 'dist/scripts/campaignbridge.js',
			$deps,
			$script_asset['version'],
			true
		);

		$style_version    = '1.0.0';
		$style_asset_path = CB_PATH . 'dist/styles/styles.asset.php';
		if ( file_exists( $style_asset_path ) ) {
			$maybe = include $style_asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $maybe ) && isset( $maybe['version'] ) ) {
				$style_version = (string) $maybe['version'];
			}
		}
		wp_enqueue_style(
			'campaignbridge-admin',
			CB_URL . 'dist/styles/styles.css',
			array(),
			$style_version
		);
	}

	/**
	 * Enqueue WordPress block editor assets for the Template Manager.
	 *
	 * @return void
	 */
	private static function enqueue_block_editor_assets() {
		// Ensure editor environment is available.
		wp_enqueue_editor();

		// Core scripts for native block editor.
		wp_enqueue_script( 'wp-edit-post' );
		wp_enqueue_script( 'wp-editor' );
		wp_enqueue_script( 'wp-blocks' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-core-data' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-block-editor' );
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'wp-block-library' );

		// Core styles for native block editor.
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-format-library' );
		wp_enqueue_style( 'wp-block-editor' );
		wp_enqueue_style( 'wp-block-library' );

		// Unregister the Classic (freeform) block to avoid TinyMCE dependency errors in embedded context.
		wp_add_inline_script(
			'wp-edit-post',
			"wp.domReady(function(){try{if(window.wp&&wp.blocks&&wp.blocks.unregisterBlockType){wp.blocks.unregisterBlockType('core/freeform');}}catch(e){console.warn('CampaignBridge: could not unregister core/freeform',e);}});"
		);

				// Enqueue our template manager script using constants.
		$script_path = CB_PATH . 'dist/scripts/template-manager.js';

		// Debug: Log the paths to see what constants resolve to.
		error_log( 'CampaignBridge: CB_PATH = ' . CB_PATH );
		error_log( 'CampaignBridge: CB_URL = ' . CB_URL );
		error_log( 'CampaignBridge: Script path = ' . $script_path );
		error_log( 'CampaignBridge: Script exists = ' . ( file_exists( $script_path ) ? 'YES' : 'NO' ) );

		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'cb-template-manager',
				CB_URL . 'dist/scripts/template-manager.js',
				array( 'wp-edit-post', 'wp-core-data', 'wp-data', 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-block-editor' ),
				filemtime( $script_path ),
				true
			);
		}

		// Add inline script directly to wp-admin to test JavaScript execution.
		wp_add_inline_script(
			'wp-admin',
			'
			console.log("=== CampaignBridge: INLINE SCRIPT TEST ===");
			console.log("Timestamp:", new Date().toISOString());
			console.log("Location:", window.location.href);
			console.log("jQuery available:", typeof jQuery !== "undefined");
			console.log("wp available:", typeof wp !== "undefined");
			console.log("wp.editPost available:", !!(window.wp && window.wp.editPost));

			// Set up settings
			window.CB_TMPL_SETTINGS=' . wp_json_encode(
				array(
					'cpt'      => 'cb_template',
					'restBase' => 'cb_template',
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'siteUrl'  => site_url(),
				)
			) . ';

			console.log("CB_TMPL_SETTINGS set:", window.CB_TMPL_SETTINGS);
			console.log("=== END INLINE SCRIPT TEST ===");
			'
		);

		// Also add to cb-template-manager if it exists.
		wp_add_inline_script(
			'cb-template-manager',
			'
			console.log("CampaignBridge: Template manager inline script executed");
			'
		);

		// Add inline styles for template manager UI.
		wp_add_inline_style(
			'wp-admin',
			'
			.cb-template-manager { margin: 20px 0; }
			.cb-toolbar {
				margin-bottom: 20px;
				padding: 15px;
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.cb-toolbar .button {
				margin-right: 10px;
				margin-bottom: 5px;
			}
			.cb-template-controls {
				margin-bottom: 20px;
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
				background: #fff;
				padding: 20px;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.cb-template-selector, .cb-template-name {
				display: flex;
				flex-direction: column;
			}
			.cb-label {
				font-weight: 600;
				margin-bottom: 8px;
				color: #1d2327;
			}
			.cb-input-wide {
				width: 100%;
				max-width: 400px;
				padding: 8px 12px;
				border: 1px solid #8c8f94;
				border-radius: 4px;
				font-size: 14px;
				transition: border-color 0.15s ease-in-out;
			}
			.cb-input-wide:focus {
				border-color: #007cba;
				box-shadow: 0 0 0 1px #007cba;
				outline: 2px solid transparent;
			}
			.cb-editor-container {
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				background: #fff;
				min-height: 600px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.cb-editor-container .block-editor {
				min-height: 600px;
			}
			@media (max-width: 782px) {
				.cb-template-controls {
					grid-template-columns: 1fr;
					gap: 15px;
				}
			}
			'
		);
	}

	/**
	 * Render the Template Manager page.
	 *
	 * @return void
	 */
	public static function render_template_manager_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Template Manager', 'campaignbridge' ); ?></h1>



			<div id="cb-template-manager-app" class="cb-template-manager">
				<div class="cb-toolbar">
					<button type="button" class="button button-primary" id="cb-new-template"><?php echo esc_html__( 'New Template', 'campaignbridge' ); ?></button>
					<button type="button" class="button" id="cb-save-template"><?php echo esc_html__( 'Save Template', 'campaignbridge' ); ?></button>
					<button type="button" class="button" id="cb-delete-template"><?php echo esc_html__( 'Delete Template', 'campaignbridge' ); ?></button>
					<button type="button" class="button" id="cb-refresh-templates"><?php echo esc_html__( 'Refresh List', 'campaignbridge' ); ?></button>
				</div>

				<div class="cb-template-controls">
					<div class="cb-template-selector">
						<label for="cb-template-select" class="cb-label"><?php echo esc_html__( 'Select Template:', 'campaignbridge' ); ?></label>
						<select id="cb-template-select" class="cb-input-wide">
							<option value=""><?php echo esc_html__( '— Select a template —', 'campaignbridge' ); ?></option>
							<?php
							$templates = get_posts(
								array(
									'post_type'   => 'cb_template',
									'numberposts' => -1,
									'orderby'     => 'date',
									'order'       => 'DESC',
								)
							);
							foreach ( (array) $templates as $template ) {
								printf(
									'<option value="%d">%s</option>',
									(int) $template->ID,
									esc_html( $template->post_title )
								);
							}
							?>
						</select>
					</div>

					<div class="cb-template-name">
						<label for="cb-template-name" class="cb-label"><?php echo esc_html__( 'Template Name:', 'campaignbridge' ); ?></label>
						<input type="text" id="cb-template-name" class="cb-input-wide" placeholder="<?php echo esc_attr__( 'Enter template name...', 'campaignbridge' ); ?>" />
					</div>
				</div>

				<div id="cb-editor-root" class="cb-editor-container"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Post Types page.
	 *
	 * @return void
	 */
	public static function render_post_types_page() {
		$settings = get_option( self::$option_name );

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'campaignbridge_messages', 'campaignbridge_message', __( 'Settings saved.', 'campaignbridge' ), 'updated' );
		}
		settings_errors( 'campaignbridge_messages' );
		?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Post Types', 'campaignbridge' ); ?></h1>

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
	</div>
		<?php
	}

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		$settings = get_option( self::$option_name );
		$provider = ( isset( $settings['provider'] ) && isset( self::$providers[ $settings['provider'] ] ) ) ? $settings['provider'] : 'mailchimp';

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'campaignbridge_messages', 'campaignbridge_message', __( 'Settings saved.', 'campaignbridge' ), 'updated' );
		}
		settings_errors( 'campaignbridge_messages' );
		?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'CampaignBridge Settings', 'campaignbridge' ); ?></h1>

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
	</div>
		<?php
	}
}
