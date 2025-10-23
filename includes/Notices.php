<?php
/**
 * CampaignBridge Per-User Notice System
 *
 * Simple, immediate notice display with optional per-user persistence.
 *
 * Usage:
 *   Notices::init();                    // Initialize hooks
 *   Notices::success('Saved!');         // Immediate display
 *   Notices::error('Error!');           // Immediate display
 *   Notices::warning('Warning', ['persist' => true]); // Persist for current user
 *   Notices::clear();                   // Clear all notices for current user
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notices {

	// Notice types
	public const SUCCESS = 'success';
	public const ERROR   = 'error';
	public const WARNING = 'warning';
	public const INFO    = 'info';

	// User meta key for per-user notice persistence (private, prefixed with underscore)
	private const USER_META_KEY = '_campaignbridge_notices';

	// Current request notices (for immediate display)
	private static array $current = array();

	// Whether we've rendered this request
	private static bool $rendered = false;

	/**
	 * Initialize the notice system.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_dismiss_script' ) );
		add_action( 'wp_ajax_dismiss_persistent_notice', array( __CLASS__, 'handle_dismiss_ajax' ) );
	}

	/**
	 * Add a notice.
	 *
	 * @param string $message The message.
	 * @param string $type    The type (success|error|warning|info).
	 * @param array  $options Options: persist (bool) - persist globally.
	 */
	public static function add( string $message, string $type = self::INFO, array $options = array() ): void {
		$type = self::validate_type( $type );
		if ( ! $type ) {
			return;
		}

		$message = self::sanitize( $message );
		if ( ! $message ) {
			return;
		}

		$notice = array(
			'message' => $message,
			'type'    => $type,
			'persist' => ! empty( $options['persist'] ),
		);

		self::$current[] = $notice;

		// Immediately save persistent notices to ensure they survive page loads
		if ( $notice['persist'] ) {
			self::save_persistent_notice( $notice );
		}
	}

	// Helper methods
	public static function success( string $msg, array $opts = array() ): void {
		self::add( $msg, self::SUCCESS, $opts ); }
	public static function error( string $msg, array $opts = array() ): void {
		self::add( $msg, self::ERROR, $opts ); }
	public static function warning( string $msg, array $opts = array() ): void {
		self::add( $msg, self::WARNING, $opts ); }
	public static function info( string $msg, array $opts = array() ): void {
		self::add( $msg, self::INFO, $opts ); }

	/**
	 * Render all current notices.
	 */
	public static function render(): void {
		// Load persisted notices and merge with current
		$persisted = self::load_persisted();
		$notices   = array_merge( $persisted, self::$current );

		if ( empty( $notices ) ) {
			return;
		}

		// Clear current notices after rendering (they've been displayed)
		$notices_to_render = $notices;
		self::$current     = array();

		// Track which notices we've rendered in this call to avoid duplicates
		$rendered_in_this_call = array();

		foreach ( $notices_to_render as $notice ) {
			$notice_key = md5( $notice['type'] . '|' . $notice['message'] );

			// Skip if we've already rendered this exact notice in this call
			if ( in_array( $notice_key, $rendered_in_this_call, true ) ) {
				continue;
			}

			$rendered_in_this_call[] = $notice_key;

			$class = 'notice notice-' . $notice['type'] . ' is-dismissible';

			// Add data attribute for dismissible persistent notices
			$data_attr = '';
			if ( ! empty( $notice['persist'] ) ) {
				$data_attr = ' data-notice-key="' . esc_attr( $notice_key ) . '" data-persistent-notice="1"';
			}

			printf(
				'<div class="%s"%s><p>%s</p></div>',
				esc_attr( $class ),
				$data_attr,
				wp_kses_post( $notice['message'] )
			);
		}
	}

	/**
	 * Load persisted notices from user meta.
	 */
	private static function load_persisted(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array(); // No user context
		}

		$notices = get_user_meta( $user_id, self::USER_META_KEY, true );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Save a persistent notice immediately.
	 */
	private static function save_persistent_notice( array $notice ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return; // No user context, can't save
		}

		$existing = self::load_persisted();

		// Add the new notice to existing persisted notices
		$existing[] = $notice;

		// Remove duplicates based on message and type
		$unique = array();
		foreach ( $existing as $n ) {
			$key = $n['type'] . '|' . $n['message'];
			if ( ! isset( $unique[ $key ] ) ) {
				$unique[ $key ] = $n;
			}
		}

		$existing = array_values( $unique );

		// Save to user meta (persists until cleared)
		update_user_meta( $user_id, self::USER_META_KEY, $existing );
	}


	/**
	 * Validate notice type.
	 */
	private static function validate_type( string $type ): string {
		$type = strtolower( trim( $type ) );
		return in_array( $type, array( self::SUCCESS, self::ERROR, self::WARNING, self::INFO ), true ) ? $type : '';
	}

	/**
	 * Sanitize message.
	 */
	private static function sanitize( string $message ): string {
		$message = trim( $message );
		return strlen( $message ) > 2000 ? substr( $message, 0, 2000 ) . '...' : $message;
	}

	/**
	 * Enqueue JavaScript for dismissing persistent notices.
	 */
	public static function enqueue_dismiss_script(): void {
		wp_enqueue_script( 'jquery' ); // Ensure jQuery is loaded

		$script = "
		jQuery(document).ready(function($) {
			// Handle dismiss clicks on persistent notices
			$(document).on('click', '.notice[data-persistent-notice] .notice-dismiss', function() {
				var \$notice = $(this).closest('.notice');
				var noticeKey = \$notice.data('notice-key');

				if (noticeKey) {
					// Send AJAX request to dismiss the notice
					$.post(ajaxurl, {
						action: 'dismiss_persistent_notice',
						notice_key: noticeKey,
						nonce: '" . wp_create_nonce( 'dismiss_persistent_notice' ) . "'
					}, function(response) {
						if (response.success) {
							// Notice dismissed successfully, fade it out
							\$notice.fadeOut(300, function() {
								\$notice.remove();
							});
						}
					});
				}
			});
		});
		";

		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * Handle AJAX request to dismiss a persistent notice.
	 */
	public static function handle_dismiss_ajax(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dismiss_persistent_notice' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$notice_key = sanitize_text_field( $_POST['notice_key'] ?? '' );

		if ( empty( $notice_key ) ) {
			wp_send_json_error( 'Invalid notice key' );
		}

		// Dismiss the notice
		self::dismiss_persistent_notice( $notice_key );

		wp_send_json_success( 'Notice dismissed' );
	}

	/**
	 * Dismiss a persistent notice by key.
	 *
	 * @param string $notice_key The notice key to dismiss.
	 */
	public static function dismiss_persistent_notice( string $notice_key ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$existing = self::load_persisted();
		$filtered = array();

		foreach ( $existing as $notice ) {
			$key = md5( $notice['type'] . '|' . $notice['message'] );
			if ( $key !== $notice_key ) {
				$filtered[] = $notice;
			}
		}

		update_user_meta( $user_id, self::USER_META_KEY, $filtered );
	}

	/**
	 * Clear all notices (current + persisted).
	 */
	public static function clear(): void {
		self::$current  = array();
		self::$rendered = false;

		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, self::USER_META_KEY );
		}
	}

	/**
	 * Get count of current notices.
	 */
	public static function count(): int {
		return count( array_merge( self::load_persisted(), self::$current ) );
	}

	/**
	 * Check if there are notices.
	 */
	public static function has_notices(): bool {
		return self::count() > 0;
	}

	/**
	 * Get all notices.
	 */
	public static function get_all(): array {
		return array_merge( self::load_persisted(), self::$current );
	}
}
