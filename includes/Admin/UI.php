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
		$active_tab = 'posts';
		if ( isset( $_GET['tab'], $_GET['cbnav'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab_raw = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$cbnav   = sanitize_text_field( wp_unslash( $_GET['cbnav'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( wp_verify_nonce( $cbnav, 'campaignbridge_nav' ) ) {
				$active_tab = $tab_raw;
			}
		}
		?>
	<div class="wrap">
		<h1>CampaignBridge</h1>
		<h2 class="nav-tab-wrapper" style="margin-bottom: 1rem;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=campaignbridge&tab=posts&cbnav=' . $nav_nonce ) ); ?>" class="nav-tab <?php echo ( 'posts' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Posts</a>
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
			<?php
			$templates = get_posts(
				array(
					'post_type'   => 'cb_template',
					'numberposts' => -1,
				)
			);
			if ( empty( $templates ) ) {
				echo '<p>No templates yet.</p>';
			} else {
				echo '<table class="widefat striped"><thead><tr><th>Title</th><th style="width:220px;">Actions</th></tr></thead><tbody>';
				foreach ( $templates as $tpl ) {
					$edit = get_edit_post_link( $tpl->ID );
					$use  = admin_url( 'admin.php?page=campaignbridge&tab=posts&cbnav=' . $nav_nonce . '&tpl=' . (int) $tpl->ID );
					echo '<tr><td>' . esc_html( get_the_title( $tpl ) ) . '</td><td><a class="button" href="' . esc_url( $edit ) . '">Edit</a> <a class="button" href="' . esc_url( $use ) . '">Use</a></td></tr>';
				}
				echo '</tbody></table>';
			}
			?>
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
			<?php self::render_posts_tab( $settings, $provider ); ?>
		<?php endif; ?>
	</div>
		<?php
	}

	/**
	 * Render the Posts tab (post type picker, posts multi-select, mapping UI, submit).
	 *
	 * @param array  $settings Current plugin settings.
	 * @param string $provider Active provider slug.
	 * @return void
	 */
	private static function render_posts_tab( $settings, $provider ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'campaignbridge_send', 'campaignbridge_nonce' ); ?>
			<div class="cb-field">
				<label for="campaignbridge-post-type" class="cb-label">Post type</label>
				<select id="campaignbridge-post-type" class="cb-input-wide">
					<?php
					$post_types_all = get_post_types( array( 'public' => true ), 'objects' );
					$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
					$allowed_types  = array();
					foreach ( $post_types_all as $type ) {
						if ( in_array( $type->name, $excluded_types, true ) ) {
							continue;
						}
						$allowed_types[] = $type;
					}
					$default_pt    = 'post';
					$allowed_names = array_map(
						function ( $t ) {
							return $t->name;
						},
						$allowed_types
					);
					if ( empty( $allowed_types ) ) {
						$default_pt = '';
					} elseif ( ! in_array( $default_pt, $allowed_names, true ) ) {
						$default_pt = $allowed_types[0]->name;
					}
					$core_slugs     = array( 'post', 'page' );
					$core_allowed   = array();
					$custom_allowed = array();
					foreach ( $allowed_types as $obj ) {
						if ( in_array( $obj->name, $core_slugs, true ) ) {
							$core_allowed[] = $obj;
						} else {
							$custom_allowed[] = $obj;
						}
					}
					$sort_by_label = function ( $a, $b ) {
						return strcasecmp( (string) $a->labels->singular_name, (string) $b->labels->singular_name );
					};
					usort( $core_allowed, $sort_by_label );
					usort( $custom_allowed, $sort_by_label );

		if ( ! empty( $core_allowed ) ) {
			echo '<optgroup label="Core types">';
			foreach ( $core_allowed as $type ) {
				printf(
					'<option value="%1$s" %3$s>%2$s</option>',
					esc_attr( $type->name ),
					esc_html( $type->labels->singular_name ),
					selected( $type->name, $default_pt, false )
				);
			}
			echo '</optgroup>';
		}
		if ( ! empty( $custom_allowed ) ) {
			echo '<optgroup label="Custom types">';
			foreach ( $custom_allowed as $type ) {
				printf(
					'<option value="%1$s" %3$s>%2$s</option>',
					esc_attr( $type->name ),
					esc_html( $type->labels->singular_name ),
					selected( $type->name, $default_pt, false )
				);
			}
			echo '</optgroup>';
		}
		?>
				</select>
			</div>

			<div class="cb-field">
				<label for="campaignbridge-posts" class="cb-label">Posts</label>
				<select id="campaignbridge-posts" class="cb-input-wide" name="selected_posts[]" multiple size="12"></select>
				<p class="description">Select up to 8 posts.</p>
			</div>

			<?php if ( 'mailchimp' === $provider ) : ?>
				<p>
					<button type="button" class="button" id="campaignbridge-show-sections">Show Mailchimp Template Sections</button>
				</p>
				<div id="campaignbridge-sections" class="cb-hidden"></div>
				<div id="campaignbridge-mapping" class="cb-hidden">
					<h3 class="cb-mapping-title">Section Mapping</h3>
					<p class="description">Assign a post to each Mailchimp section. If left empty, that section will not be filled.</p>
					<table class="widefat striped cb-mapping-table">
						<thead>
							<tr><th style="width:50%;">Section key</th><th>Post</th></tr>
						</thead>
						<tbody id="campaignbridge-mapping-body"></tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php submit_button( 'Generate and Send Email' ); ?>
		</form>
		<?php
		// Handle submission.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$nonce_value    = isset( $_POST['campaignbridge_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['campaignbridge_nonce'] ) ) : '';
		if ( 'POST' === $request_method && '' !== $nonce_value && wp_verify_nonce( $nonce_value, 'campaignbridge_send' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'campaignbridge' ) );
			}
			$selected_posts   = ! empty( $_POST['selected_posts'] ) ? array_map( 'absint', (array) $_POST['selected_posts'] ) : array();
			$sections_map     = array();
			$raw_sections_map = filter_input( INPUT_POST, 'sections_map', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( is_array( $raw_sections_map ) ) {
				foreach ( $raw_sections_map as $sec_key => $pid ) {
					$sec_key = sanitize_text_field( wp_unslash( $sec_key ) );
					$pid     = absint( $pid );
					if ( '' !== $sec_key && $pid > 0 ) {
						$sections_map[ $sec_key ] = $pid;
					}
				}
			}
			$settings_current = get_option( self::$option_name );
			Dispatcher::generate_and_send_campaign( $selected_posts, $settings_current, $sections_map, self::$providers );
		}
	}
}
