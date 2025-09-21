<?php
/**
 * Email Template Custom Post Type Manager for CampaignBridge.
 *
 * Manages the email template CPT with meta boxes, custom columns,
 * and template organization for email campaign creation.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Template Custom Post Type Manager
 *
 * Handles registration, capabilities, and management of the email template CPT.
 */
class EmailTemplate {
	/**
	 * Post type name.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'cb_email_template';

	/**
	 * Meta field names
	 */
	private const META_WIDTH    = '_cb_template_width';
	private const META_CATEGORY = '_cb_template_category';
	private const META_ACTIVE   = '_cb_template_active';

	/**
	 * Default values
	 */
	private const DEFAULT_WIDTH    = 600;
	private const DEFAULT_CATEGORY = 'general';
	private const DEFAULT_ACTIVE   = true;

	/**
	 * Nonce constants
	 */
	private const NONCE_ACTION = 'cb_email_template_settings';
	private const NONCE_FIELD  = 'cb_email_template_nonce';

	/**
	 * Custom column names
	 */
	private const COL_CATEGORY = 'cb_template_category';
	private const COL_WIDTH    = 'cb_template_width';
	private const COL_STATUS   = 'cb_template_status';

	/**
	 * Meta box IDs
	 */
	private const META_BOX_SETTINGS = 'cb-email-template-settings';
	private const META_BOX_PREVIEW  = 'cb-email-template-preview';

	/**
	 * Initialize the Email Template custom post type.
	 *
	 * Registers the CPT, meta boxes, custom columns, and WordPress hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'custom_post_messages' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'populate_custom_columns' ), 10, 2 );
	}

	/**
	 * Get post type labels.
	 *
	 * @return array Post type labels array.
	 */
	private static function get_post_type_labels(): array {
		return array(
			'name'                  => _x( 'Email Templates', 'Post type general name', 'campaignbridge' ),
			'singular_name'         => _x( 'Email Template', 'Post type singular name', 'campaignbridge' ),
			'menu_name'             => _x( 'Email Templates', 'Admin Menu text', 'campaignbridge' ),
			'name_admin_bar'        => _x( 'Email Template', 'Add New on Toolbar', 'campaignbridge' ),
			'add_new'               => __( 'Add New', 'campaignbridge' ),
			'add_new_item'          => __( 'Add New Email Template', 'campaignbridge' ),
			'new_item'              => __( 'New Email Template', 'campaignbridge' ),
			'edit_item'             => __( 'Edit Email Template', 'campaignbridge' ),
			'view_item'             => __( 'View Email Template', 'campaignbridge' ),
			'all_items'             => __( 'All Email Templates', 'campaignbridge' ),
			'search_items'          => __( 'Search Email Templates', 'campaignbridge' ),
			'parent_item_colon'     => __( 'Parent Email Templates:', 'campaignbridge' ),
			'not_found'             => __( 'No email templates found.', 'campaignbridge' ),
			'not_found_in_trash'    => __( 'No email templates found in Trash.', 'campaignbridge' ),
			'featured_image'        => _x( 'Template Cover Image', 'Overrides the "Featured Image" phrase for this post type.', 'campaignbridge' ),
			'set_featured_image'    => _x( 'Set template cover image', 'Overrides the "Set featured image" phrase for this post type.', 'campaignbridge' ),
			'remove_featured_image' => _x( 'Remove template cover image', 'Overrides the "Remove featured image" phrase for this post type.', 'campaignbridge' ),
			'use_featured_image'    => _x( 'Use as template cover image', 'Overrides the "Use as featured image" phrase for this post type.', 'campaignbridge' ),
			'archives'              => _x( 'Email template archives', 'The post type archive label used in nav menus.', 'campaignbridge' ),
			'insert_into_item'      => _x( 'Insert into email template', 'Overrides the "Insert into post" phrase (used when inserting media into a post).', 'campaignbridge' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this email template', 'Overrides the "Uploaded to this post" phrase (used when viewing media attached to a post).', 'campaignbridge' ),
			'filter_items_list'     => _x( 'Filter email templates list', 'Screen reader text for the filter links.', 'campaignbridge' ),
			'items_list_navigation' => _x( 'Email templates list navigation', 'Screen reader text for the pagination.', 'campaignbridge' ),
			'items_list'            => _x( 'Email templates list', 'Screen reader text for the items list.', 'campaignbridge' ),
		);
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		$labels = self::get_post_type_labels();
		$args   = self::get_post_type_args( $labels );

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get post type arguments.
	 *
	 * @param array $labels Post type labels.
	 * @return array Post type arguments array.
	 */
	private static function get_post_type_args( array $labels ): array {
		return array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'campaignbridge',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields',
				'revisions',
			),
			'rewrite'             => false,
			'query_var'           => false,
		);
	}

	/**
	 * Add meta boxes for template settings.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			self::META_BOX_SETTINGS,
			__( 'Template Settings', 'campaignbridge' ),
			array( __CLASS__, 'render_settings_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			self::META_BOX_PREVIEW,
			__( 'Email Preview', 'campaignbridge' ),
			array( __CLASS__, 'render_preview_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the settings meta box.
	 *
	 * @param \WP_Post $post The post object.
	 * @return void
	 */
	public static function render_settings_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$template_width = get_post_meta( $post->ID, self::META_WIDTH, true );
		if ( empty( $template_width ) ) {
			$template_width = self::DEFAULT_WIDTH;
		}
		$template_width = (int) $template_width;

		$template_category = get_post_meta( $post->ID, self::META_CATEGORY, true );
		if ( empty( $template_category ) ) {
			$template_category = self::DEFAULT_CATEGORY;
		}
		$template_category = (string) $template_category;

		$is_active = get_post_meta( $post->ID, self::META_ACTIVE, true );
		if ( empty( $is_active ) ) {
			$is_active = self::DEFAULT_ACTIVE;
		}
		$is_active = (bool) $is_active;

		echo self::render_settings_form( $template_width, $template_category, $is_active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render the settings form HTML.
	 *
	 * @param int    $width     Template width.
	 * @param string $category Template category.
	 * @param bool   $is_active  Template active status.
	 * @return string HTML form.
	 */
	private static function render_settings_form( int $width, string $category, bool $is_active ): string {
		ob_start();
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="cb_template_width"><?php esc_html_e( 'Email Width (px)', 'campaignbridge' ); ?></label>
				</th>
				<td>
					<input type="number" id="cb_template_width" name="cb_template_width" value="<?php echo esc_attr( $width ); ?>" min="300" max="800" step="10" />
					<p class="description"><?php esc_html_e( 'Standard email width is 600px for best compatibility.', 'campaignbridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cb_template_category"><?php esc_html_e( 'Category', 'campaignbridge' ); ?></label>
				</th>
				<td>
					<select id="cb_template_category" name="cb_template_category">
						<?php foreach ( self::get_template_categories() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $category, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cb_template_active"><?php esc_html_e( 'Active', 'campaignbridge' ); ?></label>
				</th>
				<td>
					<input type="checkbox" id="cb_template_active" name="cb_template_active" value="1" <?php checked( $is_active, true ); ?> />
					<label for="cb_template_active"><?php esc_html_e( 'Enable this template for use', 'campaignbridge' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
		return ob_get_clean();
	}

		/**
		 * Render the preview meta box.
		 *
		 * @param \WP_Post $post The post object.
		 * @return void
		 */
	public static function render_preview_meta_box( \WP_Post $post ): void {
		$template_width = get_post_meta( $post->ID, self::META_WIDTH, true );
		if ( empty( $template_width ) ) {
			$template_width = self::DEFAULT_WIDTH;
		}
		$template_width = (int) $template_width;

		echo self::render_preview_frame( $post, $template_width ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

		/**
		 * Render the preview frame HTML.
		 *
		 * @param \WP_Post $post   The post object.
		 * @param int      $width  Template width.
		 * @return string HTML preview.
		 */
	private static function render_preview_frame( \WP_Post $post, int $width ): string {
		ob_start();
		?>
		<div class="cb-email-preview-container">
			<div class="cb-email-preview-frame" style="width: <?php echo esc_attr( $width ); ?>px; max-width: 100%; margin: 0 auto; border: 1px solid #ddd; background: #fff;">
				<div class="cb-email-preview-header" style="background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd; text-align: center; font-size: 12px; color: #666;">
				<?php esc_html_e( 'Email Preview', 'campaignbridge' ); ?> (<?php echo esc_html( $width ); ?>px)
				</div>
				<div class="cb-email-preview-content" style="padding: 20px;">
				<?php echo wp_kses_post( $post->post_content ); ?>
				</div>
			</div>
			<p class="description">
			<?php esc_html_e( 'This preview shows how your email template will look. Use the block editor above to design your template.', 'campaignbridge' ); ?>
			</p>
		</div>
			<?php
			return ob_get_clean();
	}

		/**
		 * Save meta box data.
		 *
		 * @param int $post_id The post ID.
		 * @return void
		 */
	public static function save_meta_boxes( int $post_id ): void {
		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save template width.
		if ( isset( $_POST['cb_template_width'] ) ) {
			$width = absint( $_POST['cb_template_width'] );
			if ( $width >= 300 && $width <= 800 ) {
				update_post_meta( $post_id, self::META_WIDTH, $width );
			}
		}

		// Save template category.
		if ( isset( $_POST['cb_template_category'] ) ) {
			$category = sanitize_key( $_POST['cb_template_category'] );
			update_post_meta( $post_id, self::META_CATEGORY, $category );
		}

		// Save template active status.
		$is_active = isset( $_POST['cb_template_active'] ) ? true : false;
		update_post_meta( $post_id, self::META_ACTIVE, $is_active );
	}

		/**
		 * Add custom columns to the admin list.
		 *
		 * @param array $columns The existing columns.
		 * @return array
		 */
	public static function add_custom_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Insert our custom columns after the title.
			if ( 'title' === $key ) {
				$new_columns[ self::COL_CATEGORY ] = __( 'Category', 'campaignbridge' );
				$new_columns[ self::COL_WIDTH ]    = __( 'Width', 'campaignbridge' );
				$new_columns[ self::COL_STATUS ]   = __( 'Status', 'campaignbridge' );
			}
		}

		return $new_columns;
	}

		/**
		 * Populate custom columns with data.
		 *
		 * @param string $column  The column name.
		 * @param int    $post_id The post ID.
		 * @return void
		 */
	public static function populate_custom_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case self::COL_CATEGORY:
				$category = get_post_meta( $post_id, self::META_CATEGORY, true );
				if ( empty( $category ) ) {
					$category = self::DEFAULT_CATEGORY;
				}
				$category   = (string) $category;
				$categories = self::get_template_categories();
				echo esc_html( $categories[ $category ] ?? $category );
				break;

			case self::COL_WIDTH:
				$width = get_post_meta( $post_id, self::META_WIDTH, true );
				if ( empty( $width ) ) {
					$width = self::DEFAULT_WIDTH;
				}
				$width = (int) $width;
				echo esc_html( $width . 'px' );
				break;

			case self::COL_STATUS:
				$is_active = get_post_meta( $post_id, self::META_ACTIVE, true );
				$is_active = (bool) $is_active;
				if ( $is_active ) {
					echo '<span class="cb-status-active" style="color: #00a32a; font-weight: 600;">' . esc_html__( 'Active', 'campaignbridge' ) . '</span>';
				} else {
					echo '<span class="cb-status-inactive" style="color: #d63638;">' . esc_html__( 'Inactive', 'campaignbridge' ) . '</span>';
				}
				break;
		}
	}

		/**
		 * Customize post update messages.
		 *
		 * @param array $messages The existing messages.
		 * @return array
		 */
	public static function custom_post_messages( array $messages ): array {
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		if ( self::POST_TYPE !== $post_type ) {
			return $messages;
		}

		$messages[ self::POST_TYPE ] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Email template updated.', 'campaignbridge' ),
			2  => __( 'Custom field updated.', 'campaignbridge' ),
			3  => __( 'Custom field deleted.', 'campaignbridge' ),
			4  => __( 'Email template updated.', 'campaignbridge' ),
			5  => isset( $_GET['revision'] ) ? sprintf( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// translators: %s is the revision date.
				__( 'Email template restored to revision from %s', 'campaignbridge' ),
				wp_post_revision_title( (int) $_GET['revision'], false ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			) : false,
			6  => __( 'Email template published.', 'campaignbridge' ),
			7  => __( 'Email template saved.', 'campaignbridge' ),
			8  => __( 'Email template submitted.', 'campaignbridge' ),
			9  => sprintf(
				// translators: %1$s is the scheduled date and time.
				__( 'Email template scheduled for: <strong>%1$s</strong>.', 'campaignbridge' ),
				date_i18n(
					// translators: Date format for scheduled post (e.g., "Jan 15, 2024 @ 14:30").
					__( 'M j, Y @ G:i', 'campaignbridge' ),
					strtotime( $post->post_date )
				)
			),
			10 => __( 'Email template draft updated.', 'campaignbridge' ),
		);

		return $messages;
	}

		/**
		 * Get all active email templates.
		 *
		 * @return array Array of template posts.
		 */
	public static function get_active_templates(): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'meta_key'       => self::META_ACTIVE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => self::DEFAULT_ACTIVE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

		/**
		 * Get templates by category.
		 *
		 * @param string $category The template category.
		 * @return array Array of template posts.
		 */
	public static function get_templates_by_category( string $category ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'meta_key'       => self::META_CATEGORY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $category, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

		/**
		 * Get template by ID with meta data.
		 *
		 * @param int $template_id The template post ID.
		 * @return array|null Template data or null if not found.
		 */
	public static function get_template_data( int $template_id ): ?array {
		$post = get_post( $template_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$width     = get_post_meta( $post->ID, self::META_WIDTH, true ) ?: self::DEFAULT_WIDTH;
		$category  = get_post_meta( $post->ID, self::META_CATEGORY, true ) ?: self::DEFAULT_CATEGORY;
		$is_active = get_post_meta( $post->ID, self::META_ACTIVE, true ) ?: self::DEFAULT_ACTIVE;

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'width'     => (int) $width,
			'category'  => (string) $category,
			'is_active' => (bool) $is_active,
			'created'   => $post->post_date,
			'modified'  => $post->post_modified,
		);
	}

		/**
		 * Create a new email template.
		 *
		 * @param array $template_data Template data.
		 * @return int|WP_Error Template ID on success, WP_Error on failure.
		 */
	public static function create_template( array $template_data ) {
		$post_data = array(
			'post_title'   => $template_data['title'] ?? '',
			'post_content' => $template_data['content'] ?? '',
			'post_excerpt' => $template_data['excerpt'] ?? '',
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
		);

		$template_id = wp_insert_post( $post_data );

		if ( is_wp_error( $template_id ) ) {
			return $template_id;
		}

		// Save meta data.
		if ( isset( $template_data['width'] ) ) {
			update_post_meta( $template_id, self::META_WIDTH, absint( $template_data['width'] ) );
		}

		if ( isset( $template_data['category'] ) ) {
			update_post_meta( $template_id, self::META_CATEGORY, sanitize_key( $template_data['category'] ) );
		}

		if ( isset( $template_data['is_active'] ) ) {
			update_post_meta( $template_id, self::META_ACTIVE, (bool) $template_data['is_active'] );
		}

		return $template_id;
	}

		/**
		 * Duplicate an existing template.
		 *
		 * @param int $template_id The template ID to duplicate.
		 * @return int|WP_Error New template ID on success, WP_Error on failure.
		 */
	public static function duplicate_template( int $template_id ) {
		$original = self::get_template_data( $template_id );
		if ( ! $original ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'campaignbridge' ) );
		}

		$duplicate_data = array(
			'title'     => $original['title'] . ' - ' . __( 'Copy', 'campaignbridge' ),
			'content'   => $original['content'],
			'excerpt'   => $original['excerpt'],
			'width'     => $original['width'],
			'category'  => $original['category'],
			'is_active' => false, // Duplicates start as inactive.
		);

		return self::create_template( $duplicate_data );
	}

		/**
		 * Get template categories.
		 *
		 * @return array Array of category labels.
		 */
	public static function get_template_categories(): array {
		return array(
			'general'     => __( 'General', 'campaignbridge' ),
			'newsletter'  => __( 'Newsletter', 'campaignbridge' ),
			'promotional' => __( 'Promotional', 'campaignbridge' ),
			'welcome'     => __( 'Welcome', 'campaignbridge' ),
			'custom'      => __( 'Custom', 'campaignbridge' ),
		);
	}
}
