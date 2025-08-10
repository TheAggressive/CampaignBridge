<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CampaignBridge {
	private $option_name = 'campaignbridge_settings';
	private $providers   = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_posts', array( $this, 'ajax_fetch_posts' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_sections', array( $this, 'ajax_fetch_sections' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_mailchimp_audiences', array( $this, 'ajax_fetch_mailchimp_audiences' ) );
		add_action( 'wp_ajax_campaignbridge_fetch_mailchimp_templates', array( $this, 'ajax_fetch_mailchimp_templates' ) );
		add_action( 'wp_ajax_campaignbridge_verify_mailchimp', array( $this, 'ajax_verify_mailchimp' ) );

		// Register providers.
		require_once __DIR__ . '/providers/interface-campaignbridge-provider.php';
		require_once __DIR__ . '/providers/class-campaignbridge-provider-mailchimp.php';
		require_once __DIR__ . '/providers/class-campaignbridge-provider-html.php';
		$this->providers = array(
			'mailchimp' => new CampaignBridge_Provider_Mailchimp(),
			'html'      => new CampaignBridge_Provider_HTML(),
		);
	}

	public function add_admin_menu() {
		add_menu_page( 'CampaignBridge', 'CampaignBridge', 'manage_options', 'mailchimp-post-blast', array( $this, 'plugin_page' ) );
	}

	public function register_settings() {
		register_setting(
			'campaignbridge',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$clean             = array();
		$previous          = get_option( $this->option_name, array() );
		$clean['provider'] = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : 'mailchimp';
		$posted_api_key    = isset( $input['api_key'] ) ? (string) $input['api_key'] : '';
		if ( $posted_api_key === '' && isset( $previous['api_key'] ) ) {
			$clean['api_key'] = $previous['api_key'];
		} else {
			$clean['api_key'] = sanitize_text_field( $posted_api_key );
		}
		$clean['audience_id'] = isset( $input['audience_id'] ) ? sanitize_text_field( $input['audience_id'] ) : '';
		$clean['template_id'] = isset( $input['template_id'] ) ? absint( $input['template_id'] ) : 0;
		// Convert included_post_types to exclude_post_types for storage.
		$clean['exclude_post_types'] = array();
		if ( isset( $input['included_post_types'] ) && is_array( $input['included_post_types'] ) ) {
			$included = array();
			foreach ( $input['included_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( post_type_exists( $pt ) ) {
					$included[] = $pt;
				}
			}
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			foreach ( $public_types as $pt ) {
				if ( ! in_array( $pt, $included, true ) ) {
					$clean['exclude_post_types'][] = $pt;
				}
			}
		}
		return $clean;
	}

	public function plugin_page() {
		$settings   = get_option( $this->option_name );
		$provider   = isset( $settings['provider'] ) && isset( $this->providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'posts';
		?>
		<div class="wrap">
			<h1>CampaignBridge</h1>
			<h2 class="nav-tab-wrapper" style="margin-bottom: 1rem;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mailchimp-post-blast&tab=posts' ) ); ?>" class="nav-tab <?php echo ( 'posts' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Posts</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mailchimp-post-blast&tab=settings' ) ); ?>" class="nav-tab <?php echo ( 'settings' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Settings</a>
			</h2>

			<?php
			// Show Settings saved/other messages using the Settings API messages pattern.
			if ( 'settings' === $active_tab ) {
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
								<select name="<?php echo esc_attr( $this->option_name ); ?>[provider]">
									<?php foreach ( $this->providers as $slug => $obj ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $provider ); ?>><?php echo esc_html( $obj->label() ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html__( 'Choose which email client or export method to use.', 'campaignbridge' ); ?></p>
							</td>
						</tr>
					<?php
					// Provider-specific fields (e.g., Mailchimp API Key, Audience ID, Template ID) are rendered below.
					?>
						<tr>
							<th scope="row">Enabled post types</th>
							<td>
								<?php
								$public_types = get_post_types( array( 'public' => true ), 'objects' );
								// Split into core vs custom and sort by label in each group
								$core_slugs   = array( 'post', 'page' );
								$core_types   = array();
								$custom_types = array();
								foreach ( $public_types as $obj ) {
									if ( in_array( $obj->name, $core_slugs, true ) ) {
										$core_types[] = $obj;
									} else {
										$custom_types[] = $obj;
									}
								}
								$sort_by_label = function ( $a, $b ) {
									return strcasecmp( (string) $a->labels->singular_name, (string) $b->labels->singular_name );
								};
								usort( $core_types, $sort_by_label );
								usort( $custom_types, $sort_by_label );
								$excluded_types      = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
								$included_type_names = array();
	foreach ( $public_types as $t ) {
		if ( ! in_array( $t->name, $excluded_types, true ) ) {
			$included_type_names[] = $t->name;
		}
	}
								echo '<div class="cb-switches-box">';
	if ( ! empty( $core_types ) ) {
		echo '<div class="cb-switches-group"><div class="cb-switches-group-title">Core types</div><div class="cb-switches-grid">';
		foreach ( $core_types as $type ) {
			$checked = in_array( $type->name, $included_type_names, true ) ? 'checked' : '';
			printf(
				'<label class="cb-switch"><input type="checkbox" name="%1$s[included_post_types][]" value="%2$s" %4$s /><span class="cb-slider"></span><span class="cb-switch-label">%3$s</span></label>',
				esc_attr( $this->option_name ),
				esc_attr( $type->name ),
				esc_html( $type->labels->singular_name ),
				$checked
			);
		}
		echo '</div></div>';
	}
	if ( ! empty( $custom_types ) ) {
		echo '<div class="cb-switches-group"><div class="cb-switches-group-title">Custom types</div><div class="cb-switches-grid">';
		foreach ( $custom_types as $type ) {
			$checked = in_array( $type->name, $included_type_names, true ) ? 'checked' : '';
			printf(
				'<label class="cb-switch"><input type="checkbox" name="%1$s[included_post_types][]" value="%2$s" %4$s /><span class="cb-slider"></span><span class="cb-switch-label">%3$s</span></label>',
				esc_attr( $this->option_name ),
				esc_attr( $type->name ),
				esc_html( $type->labels->singular_name ),
				$checked
			);
		}
		echo '</div></div>';
	}
								echo '</div>';
	?>
								<p class="description">Toggle on to include the post type in the selector.</p>
							</td>
						</tr>
						<?php
						// Allow provider to render its own settings fields after core ones.
						if ( isset( $this->providers[ $provider ] ) ) {
							$this->providers[ $provider ]->render_settings_fields( $settings, $this->option_name );
						}
						?>
					</table>
					<?php submit_button( 'Save Settings' ); ?>
				</form>
			<?php else : ?>
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
							// Group allowed types into core vs custom and sort each group alphabetically
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
			<?php endif; ?>
		</div>
		<?php

		if ( 'posts' === $active_tab && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['campaignbridge_nonce'] ) && wp_verify_nonce( $_POST['campaignbridge_nonce'], 'campaignbridge_send' ) ) {
			$selected_posts = ! empty( $_POST['selected_posts'] ) ? array_map( 'absint', (array) $_POST['selected_posts'] ) : array();
			$sections_map   = array();
			if ( isset( $_POST['sections_map'] ) && is_array( $_POST['sections_map'] ) ) {
				foreach ( $_POST['sections_map'] as $sec_key => $pid ) {
					$sec_key = sanitize_text_field( wp_unslash( $sec_key ) );
					$pid     = absint( $pid );
					if ( '' !== $sec_key && $pid > 0 ) {
						$sections_map[ $sec_key ] = $pid;
					}
				}
			}
			$this->generate_and_send_campaign( $selected_posts, $settings, $sections_map );
		}
	}

	public function ajax_fetch_sections() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		$provider = isset( $settings['provider'] ) && isset( $this->providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $provider ) {
			wp_send_json_error( array( 'message' => 'Only supported for Mailchimp.' ), 400 );
		}
		$sections = $this->providers['mailchimp']->get_section_keys( $settings );
		if ( is_wp_error( $sections ) ) {
			wp_send_json_error( array( 'message' => $sections->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'sections' => $sections ) );
	}

	public function ajax_fetch_mailchimp_audiences() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing API key.' ), 400 );
		}
		$items = $this->providers['mailchimp']->get_audiences( $settings );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	public function ajax_fetch_mailchimp_templates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing API key.' ), 400 );
		}
		$items = $this->providers['mailchimp']->get_templates( $settings );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	public function ajax_verify_mailchimp() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}
		$settings = get_option( $this->option_name );
		$prov     = isset( $settings['provider'] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $prov ) {
			wp_send_json_error( array( 'message' => 'Provider is not Mailchimp.' ), 400 );
		}
		// If an API key is provided in the request, prefer it for verification (unsaved form input)
		if ( isset( $_POST['api_key'] ) ) {
			$settings['api_key'] = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
		}
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing API key.' ), 400 );
		}
		$items = $this->providers['mailchimp']->get_audiences( $settings );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'ok' => true ) );
	}

	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_mailchimp-post-blast' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'campaignbridge-admin',
			plugins_url( 'dist/scripts/campaignbridge.js', __DIR__ ),
			array(),
			'1.0.0',
			true
		);

		wp_enqueue_style(
			'campaignbridge-admin',
			plugins_url( 'dist/styles/styles.css', __DIR__ ),
			array(),
			'1.0.0'
		);

		wp_localize_script(
			'campaignbridge-admin',
			'CampaignBridge',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'campaignbridge_ajax' ),
			)
		);
	}

	public function ajax_fetch_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'campaignbridge_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post';
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post type' ), 400 );
		}

		$settings       = get_option( $this->option_name );
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
		if ( in_array( $post_type, $excluded_types, true ) ) {
			wp_send_json_error( array( 'message' => 'Post type excluded' ), 400 );
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $posts as $p ) {
			$title_raw     = (string) get_post_field( 'post_title', $p->ID );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$items[]       = array(
				'id'    => (int) $p->ID,
				'label' => $title_decoded,
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
	}

	private function generate_and_send_campaign( $post_ids, $settings, $sections_map = array() ) {
		$blocks = array();

		if ( ! empty( $sections_map ) ) {
			// Build blocks using explicit section mapping.
			foreach ( $sections_map as $section_key => $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}
				$img           = get_the_post_thumbnail_url( $post_id, 'medium' );
				$content_html  = apply_filters( 'the_content', $post->post_content );
				$link          = get_permalink( $post_id );
				$title_raw     = (string) get_post_field( 'post_title', $post_id );
				$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
				$title_clean   = preg_replace( '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u', '', $title_decoded );

				$blocks[ $section_key ] = sprintf(
					"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
					$img ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $img ) ) : '',
					esc_html( $title_clean ),
					wp_kses_post( $content_html ),
					esc_url( $link )
				);
			}
			return $this->dispatch_to_provider( $blocks, $settings );
		}

		foreach ( array_slice( $post_ids, 0, 8 ) as $index => $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$img = get_the_post_thumbnail_url( $post_id, 'medium' );
			// Use full post HTML content (rendered) instead of a plain-text excerpt.
			$content_html = apply_filters( 'the_content', $post->post_content );
			$link         = get_permalink( $post_id );

			// Prepare safe title without HTML entities and without image-size suffixes.
			$title_raw     = (string) get_post_field( 'post_title', $post_id );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$title_clean   = preg_replace( '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u', '', $title_decoded );

			$blocks[ 'CONTENT_BLOCK_' . ( $index + 1 ) ] = sprintf(
				"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
				$img ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $img ) ) : '',
				esc_html( $title_clean ),
				wp_kses_post( $content_html ),
				esc_url( $link )
			);
		}

		return $this->dispatch_to_provider( $blocks, $settings );
	}

	private function dispatch_to_provider( $blocks, $settings ) {
		$provider_slug = isset( $settings['provider'] ) && isset( $this->providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		$provider      = isset( $this->providers[ $provider_slug ] ) ? $this->providers[ $provider_slug ] : null;
		if ( ! $provider ) {
			CampaignBridge_Notices::error( esc_html__( 'No valid provider selected.', 'campaignbridge' ) );
			return false;
		}
		return $provider->send_campaign( $blocks, $settings );
	}

	private function send_to_mailchimp( $blocks, $settings ) {
		/* legacy stub, unused after provider refactor */ }
}
