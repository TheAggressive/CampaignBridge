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
	 * Meta field constants.
	 */
	public const META_CATEGORY            = 'cb_template_category';
	public const META_SUBJECT             = 'cb_subject';
	public const META_PREHEADER           = 'cb_preheader';
	public const META_VIEW_ONLINE_ENABLED = 'cb_view_online_enabled';
	public const META_VIEW_ONLINE_URL     = 'cb_view_online_url';
	public const META_ADDRESS_HTML        = 'cb_address_html';
	public const META_UNSUBSCRIBE_URL     = 'cb_unsubscribe_url';
	public const META_SENDER_NAME         = 'cb_sender_name';
	public const META_SENDER_EMAIL        = 'cb_sender_email';
	public const META_UTM_ENABLED         = 'cb_utm_enabled';
	public const META_UTM_TEMPLATE        = 'cb_utm_template';
	public const META_AUDIENCE_TAGS       = 'cb_audience_tags';
	public const META_FOOTER_ENABLED      = 'cb_footer_enabled';
	public const META_FOOTER_PATTERN      = 'cb_footer_pattern';

	/**
	 * Meta field configuration for registration.
	 * Defines all meta fields with their types and sanitization.
	 */
	private const META_FIELD_CONFIG = array(
		// String fields.
		'cb_subject'             => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_preheader'           => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_sender_name'         => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_sender_email'        => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_email',
		),
		'cb_view_online_url'     => array(
			'type'     => 'string',
			'sanitize' => 'esc_url_raw',
		),
		'cb_unsubscribe_url'     => array(
			'type'     => 'string',
			'sanitize' => 'esc_url_raw',
		),
		'cb_utm_template'        => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_audience_tags'       => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_footer_pattern'      => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),

		// Boolean fields.
		'cb_view_online_enabled' => array(
			'type'     => 'boolean',
			'sanitize' => 'wp_validate_boolean',
		),
		'cb_utm_enabled'         => array(
			'type'     => 'boolean',
			'sanitize' => 'wp_validate_boolean',
		),
		'cb_footer_enabled'      => array(
			'type'     => 'boolean',
			'sanitize' => 'wp_validate_boolean',
		),

		// HTML field.
		'cb_address_html'        => array(
			'type'     => 'string',
			'sanitize' => 'wp_kses_post',
		),

		// Category field with custom validation.
		'cb_template_category'   => array(
			'type'     => 'string',
			'sanitize' => array( __CLASS__, 'sanitize_category_field' ),
		),
	);

	/**
	 * Initialize the Email Template custom post type.
	 *
	 * Registers the CPT and WordPress hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta_fields' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_custom_fields_meta_box' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'custom_post_messages' ) );
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
	 * Add custom fields meta box to the post editor.
	 *
	 * @return void
	 */
	public static function add_custom_fields_meta_box(): void {
		// Enable the built-in custom fields meta box.
		add_post_type_support( self::POST_TYPE, 'custom-fields' );

		// Force show custom fields meta box in block editor.
		add_filter(
			'get_user_option_meta-box-hidden_' . self::POST_TYPE,
			function ( $hidden ) {
				if ( ! is_array( $hidden ) ) {
					$hidden = array();
				}
				// Remove 'postcustom' from hidden meta boxes to ensure it shows.
				return array_filter(
					$hidden,
					function ( $box ) {
						return 'postcustom' !== $box;
					}
				);
			},
			999
		);

		// Ensure custom fields appear in the meta box order.
		add_filter(
			'get_user_option_meta-box-order_' . self::POST_TYPE,
			function ( $order ) {
				if ( ! $order ) {
					$order = array(
						'normal'   => 'postcustom,slugdiv,postimagediv',
						'side'     => 'submitdiv,formatdiv,categorydiv,tagsdiv-campaignbridge_category',
						'advanced' => 'postexcerpt,trackbacksdiv,commentstatusdiv,commentsdiv,authordiv,revisionsdiv',
					);
				}
				return $order;
			}
		);
	}

	/**
	 * Register meta fields for the email template post type.
	 *
	 * @return void
	 */
	public static function register_meta_fields(): void {
		// String fields.
		$string_fields = array(
			self::META_SUBJECT,
			self::META_PREHEADER,
			self::META_SENDER_NAME,
			self::META_SENDER_EMAIL,
			self::META_VIEW_ONLINE_URL,
			self::META_UNSUBSCRIBE_URL,
			self::META_UTM_TEMPLATE,
			self::META_AUDIENCE_TAGS,
			self::META_FOOTER_PATTERN,
		);

		foreach ( $string_fields as $field ) {
			register_post_meta(
				self::POST_TYPE,
				$field,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		// Boolean fields.
		$boolean_fields = array(
			self::META_VIEW_ONLINE_ENABLED,
			self::META_UTM_ENABLED,
			self::META_FOOTER_ENABLED,
		);

		foreach ( $boolean_fields as $field ) {
			register_post_meta(
				self::POST_TYPE,
				$field,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'boolean',
					'sanitize_callback' => 'wp_validate_boolean',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		// HTML field.
		register_post_meta(
			self::POST_TYPE,
			self::META_ADDRESS_HTML,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Category field with enum validation.
		register_post_meta(
			self::POST_TYPE,
			self::META_CATEGORY,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$valid_categories = array_keys( self::get_template_categories() );
					return in_array( $value, $valid_categories, true ) ? $value : 'general';
				},
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
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
			2  => __( 'Email template updated.', 'campaignbridge' ),
			3  => __( 'Email template deleted.', 'campaignbridge' ),
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
		 * Get all email templates.
		 *
		 * @return array Array of template posts.
		 */
	public static function get_templates(): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
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

		return wp_insert_post( $post_data );
	}

	/**
	 * Duplicate an existing template.
	 *
	 * @param int $template_id The template ID to duplicate.
	 * @return int|WP_Error New template ID on success, WP_Error on failure.
	 */
	public static function duplicate_template( int $template_id ) {
		$original = get_post( $template_id );
		if ( ! $original || self::POST_TYPE !== $original->post_type ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'campaignbridge' ) );
		}

		$duplicate_data = array(
			'title'   => $original->post_title . ' - ' . __( 'Copy', 'campaignbridge' ),
			'content' => $original->post_content,
			'excerpt' => $original->post_excerpt,
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


	/**
	 * Get meta value with default fallback.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $meta_key The meta key.
	 * @param mixed  $fallback The default value.
	 * @return mixed The meta value or fallback.
	 */
	public static function get_meta( int $post_id, string $meta_key, $fallback = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $value ) ? $value : $fallback;
	}

	/**
	 * Get all available meta field keys for block binding.
	 *
	 * @return array Array of meta field keys.
	 */
	public static function get_meta_field_keys(): array {
		return array_keys( self::META_FIELD_CONFIG );
	}
}
