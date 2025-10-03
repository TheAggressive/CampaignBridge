<?php // phpcs:ignoreFile WordPress.Files.FileName
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

namespace CampaignBridge\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Template Custom Post Type Manager
 *
 * Handles registration, capabilities, and management of the email template CPT.
 */
class Post_Type_Email_Template {
	/**
	 * Post type name.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'cb_email_template';

	/**
	 * Meta field keys are defined in META_FIELD_CONFIG for consistency.
	 * All field definitions, validation rules, and categories are centralized here.
	 * Use self::get_meta_field_keys() to get all available field keys,
	 * or self::get_meta_field_key($name) to get a specific field key by name.
	 * Use self::get_meta_field_config() to get field configuration details.
	 */

	/**
	 * Meta field configuration for registration.
	 * Defines all meta fields with their types, sanitization, and validation.
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

		// Category field with enum validation.
		'cb_template_category'   => array(
			'type'         => 'string',
			'sanitize'     => array( __CLASS__, 'sanitize_category_field' ),
			'valid_values' => array( 'general', 'newsletter', 'promotional', 'welcome', 'custom' ),
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
		foreach ( self::META_FIELD_CONFIG as $field_key => $config ) {
			$sanitize_callback = $config['sanitize'];

			// Handle callable sanitization functions.
			if ( is_array( $sanitize_callback ) && is_callable( $sanitize_callback ) ) {
				$sanitize_callback = $sanitize_callback;
			}

			register_post_meta(
				self::POST_TYPE,
				$field_key,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => $config['type'],
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Sanitize category field value.
	 *
	 * @param string $value The field value to sanitize.
	 * @return string The sanitized value.
	 */
	private static function sanitize_category_field( string $value ): string {
		$category_config = self::get_meta_field_config( 'cb_template_category' );
		$valid_values    = $category_config['valid_values'] ?? array();
		return in_array( $value, $valid_values, true ) ? $value : 'general';
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
		$post      = get_post();
		$post_type = get_post_type( $post );

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
				'meta_key'       => 'cb_template_category',
				'meta_value'     => $category,
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
		$category_config = self::get_meta_field_config( 'cb_template_category' );
		$valid_values    = $category_config['valid_values'] ?? array();

		$categories = array();
		foreach ( $valid_values as $value ) {
			$categories[ $value ] = ucfirst( str_replace( '_', ' ', $value ) );
		}

		return $categories;
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

	/**
	 * Get meta field configuration.
	 *
	 * @param string|null $field_key Optional field key to get specific config.
	 * @return array|array|null Configuration for specific field or all fields.
	 */
	public static function get_meta_field_config( ?string $field_key = null ): array {
		if ( null === $field_key ) {
			return self::META_FIELD_CONFIG;
		}

		return self::META_FIELD_CONFIG[ $field_key ] ?? array();
	}

	/**
	 * Get a specific meta field key by name.
	 * Provides a stable API for accessing field keys without exposing the config array.
	 *
	 * @param string $field_name The field name (e.g., 'subject', 'preheader').
	 * @return string|null The field key or null if not found.
	 */
	public static function get_meta_field_key( string $field_name ): ?string {
		// Create a reverse lookup from config.
		static $reverse_map = null;

		if ( null === $reverse_map ) {
			$reverse_map = array();
			foreach ( array_keys( self::META_FIELD_CONFIG ) as $field_key ) {
				// Extract the base name from the field key (remove 'cb_' prefix).
				$base_name                 = str_replace( 'cb_', '', $field_key );
				$reverse_map[ $base_name ] = $field_key;
			}
		}

		return isset( $reverse_map[ $field_name ] ) ? $reverse_map[ $field_name ] : null;
	}
}
