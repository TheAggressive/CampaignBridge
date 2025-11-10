<?php
/**
 * Notices System
 *
 * A simple, immediate notice display with optional per-user persistence and expiration.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge;

use CampaignBridge\Core\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CampaignBridge Per-User Notice System
 *
 * Simple, immediate notice display with optional per-user persistence and expiration.
 *
 * Usage:
 *   Notices::init();                    // Initialize hooks
 *   Notices::success('Saved!');         // Immediate display
 *   Notices::error('Error!');           // Immediate display
 *   Notices::warning('Warning', ['persist' => true]); // Persist for current user (1 hour default)
 *   Notices::warning('Alert', ['persist' => true, 'ttl' => 1800]); // Persist for 30 minutes
 *   Notices::debug('Debug info');       // Only shown when WP_DEBUG is true
 *   Notices::clear();                   // Clear all notices for current user
 *
 * @package CampaignBridge
 */
final class Notices {

	// Notice types.
	public const SUCCESS = 'success';
	public const ERROR   = 'error';
	public const WARNING = 'warning';
	public const INFO    = 'info';
	public const DEBUG   = 'debug';

	// Default TTL for persistent notices (1 hour).
	private const DEFAULT_TTL = 3600; // 1 hour in seconds

	// User meta key for per-user notice persistence (private, prefixed with underscore).
	private const USER_META_KEY = '_campaignbridge_notices';

	/**
	 * Current request notices (for immediate display).
	 *
	 * @var array<array<string, mixed>>
	 */
	private static array $current = array();

	/**
	 * Initialize the notice system.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_dismiss_script' ) );
		add_action( 'wp_ajax_dismiss_persistent_notice', array( __CLASS__, 'handle_dismiss_ajax' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_server_dismiss' ) );
	}

	/**
	 * Add a notice.
	 *
	 * @param string               $message The message.
	 * @param string               $type    The type (success|error|warning|info|debug).
	 * @param array<string, mixed> $options Options: persist (bool), ttl (int seconds).
	 */
	public static function add( string $message, string $type = self::INFO, array $options = array() ): void {
		$type = self::validate_type( $type );
		if ( ! $type ) {
			return;
		}

		// Skip debug notices if WP_DEBUG is not enabled.
		if ( self::DEBUG === $type && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
			return;
		}

		$message = self::sanitize( $message );
		if ( ! $message ) {
			return;
		}

		$persist = ! empty( $options['persist'] );
		$ttl     = $options['ttl'] ?? ( $persist ? self::DEFAULT_TTL : 0 );

		$notice = array(
			'message' => $message,
			'type'    => $type,
			'persist' => $persist,
			'expires' => $persist ? time() + $ttl : 0,
		);

		self::$current[] = $notice;

		// Immediately save persistent notices to ensure they survive page loads.
		if ( $notice['persist'] ) {
			self::save_persistent_notice( $notice );
		}
	}

	/**
	 * Add a success notice.
	 *
	 * @param string               $msg  The message.
	 * @param array<string, mixed> $opts Options: persist (bool), ttl (int seconds).
	 */
	public static function success( string $msg, array $opts = array() ): void {
		self::add( $msg, self::SUCCESS, $opts );
	}

	/**
	 * Add an error notice.
	 *
	 * @param string               $msg  The message.
	 * @param array<string, mixed> $opts Options: persist (bool), ttl (int seconds).
	 */
	public static function error( string $msg, array $opts = array() ): void {
		self::add( $msg, self::ERROR, $opts );
	}

	/**
	 * Add a warning notice.
	 *
	 * @param string               $msg  The message.
	 * @param array<string, mixed> $opts Options: persist (bool), ttl (int seconds).
	 */
	public static function warning( string $msg, array $opts = array() ): void {
		self::add( $msg, self::WARNING, $opts );
	}

	/**
	 * Add an info notice.
	 *
	 * @param string               $msg  The message.
	 * @param array<string, mixed> $opts Options: persist (bool), ttl (int seconds).
	 */
	public static function info( string $msg, array $opts = array() ): void {
		self::add( $msg, self::INFO, $opts );
	}

	/**
	 * Add a debug notice.
	 *
	 * @param string               $msg  The message.
	 * @param array<string, mixed> $opts Options: persist (bool), ttl (int seconds).
	 */
	public static function debug( string $msg, array $opts = array() ): void {
		self::add( $msg, self::DEBUG, $opts );
	}

	/**
	 * Render all current notices.
	 */
	public static function render(): void {
		// Load persisted notices and merge with current.
		$notices = self::get_all_notices();

		if ( empty( $notices ) ) {
			return;
		}

		// Clear current notices after rendering (they've been displayed).
		$notices_to_render = $notices;
		self::$current     = array();

		// Track which notices we've rendered across all calls in this request.
		static $rendered_notices = array();

		foreach ( $notices_to_render as $notice ) {
			$notice_key = self::generate_notice_key( $notice );

			// Skip if we've already rendered this exact notice in this request.
			if ( in_array( $notice_key, $rendered_notices, true ) ) {
				continue;
			}

			$rendered_notices[] = $notice_key;

			$class = 'notice notice-' . $notice['type'] . ' is-dismissible';

			// Add data attributes for enhanced dismissal (works with existing WordPress X).
			$data_attr = '';
			if ( ! empty( $notice['persist'] ) ) {
				$data_attr = ' data-notice-key="' . esc_attr( $notice_key ) . '" data-persistent-notice="1" data-dismiss-nonce="' . esc_attr( wp_create_nonce( 'dismiss_notice_' . $notice_key ) ) . '"';
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data_attr is built with esc_attr() for attribute values
			printf(
				'<div class="%s"%s><p>%s</p></div>',
				esc_attr( $class ),
				esc_attr( $data_attr ),
				wp_kses_post( $notice['message'] )
			);
		}
	}

	/**
	 * Load persisted notices from user meta.
	 *
	 * @return array<array<string, mixed>>
	 */
	private static function load_persisted(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array(); // No user context.
		}

		$notices = Storage::get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( ! is_array( $notices ) ) {
			return array();
		}

		// Filter out expired notices.
		$current_time  = time();
		$valid_notices = array();

		foreach ( $notices as $notice ) {
			// If no expiration or not expired yet.
			if ( empty( $notice['expires'] ) || $notice['expires'] > $current_time ) {
				$valid_notices[] = $notice;
			}
		}

		// Update user meta if we filtered out expired notices.
		if ( count( $valid_notices ) !== count( $notices ) ) {
			Storage::update_user_meta( $user_id, self::USER_META_KEY, $valid_notices );
		}

		return $valid_notices;
	}

	/**
	 * Save a persistent notice immediately.
	 *
	 * @param array<string, mixed> $notice The notice to save.
	 */
	private static function save_persistent_notice( array $notice ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return; // No user context, can't save.
		}

		$existing = self::load_persisted();

		// Add the new notice to existing persisted notices.
		$existing[] = $notice;

		// Remove duplicates based on message and type.
		$unique = array();
		foreach ( $existing as $n ) {
			$key = $n['type'] . '|' . $n['message'];
			if ( ! isset( $unique[ $key ] ) ) {
				$unique[ $key ] = $n;
			}
		}

		$existing = array_values( $unique );

		// Save to user meta (persists until cleared).
		Storage::update_user_meta( $user_id, self::USER_META_KEY, $existing );
	}

	/**
	 * Validate notice type.
	 *
	 * @param string $type The notice type.
	 * @return string The validated notice type.
	 */
	private static function validate_type( string $type ): string {
		$type = strtolower( trim( $type ) );
		return in_array( $type, array( self::SUCCESS, self::ERROR, self::WARNING, self::INFO, self::DEBUG ), true ) ? $type : '';
	}

	/**
	 * Sanitize message.
	 *
	 * @param string $message The message to sanitize.
	 * @return string The sanitized message.
	 */
	private static function sanitize( string $message ): string {
		$message = trim( $message );
		return strlen( $message ) > 2000 ? substr( $message, 0, 2000 ) . '...' : $message;
	}

	/**
	 * Generate a unique key for a notice.
	 *
	 * @param array<string, mixed> $notice The notice.
	 * @return string The unique key.
	 */
	private static function generate_notice_key( array $notice ): string {
		return md5( $notice['type'] . '|' . $notice['message'] );
	}

	/**
	 * Enqueue JavaScript for dismissing persistent notices.
	 */
	/**
	 * Enqueue JavaScript for dismissing persistent notices.
	 */
	public static function enqueue_dismiss_script(): void {
		// Hook into WordPress's existing dismiss mechanism for persistent notices.
		$script = "
		document.addEventListener('DOMContentLoaded', function() {
			// Override WordPress's dismiss behavior for persistent notices
			var originalDismiss = jQuery.fn.on ? null : null; // WordPress uses jQuery

			// Use jQuery since WordPress does (no need to reinvent the wheel)
			jQuery(document).on('click', '.notice-dismiss', function(event) {
				var \$notice = jQuery(this).closest('.notice');
				var isPersistent = \$notice.data('persistent-notice');

				if (isPersistent) {
					// Prevent WordPress's default dismiss behavior
					event.preventDefault();
					event.stopImmediatePropagation();

					var noticeKey = \$notice.data('notice-key');
					var dismissNonce = \$notice.data('dismiss-nonce');

					if (noticeKey && dismissNonce) {
						// Send AJAX request to dismiss the notice permanently
						jQuery.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'dismiss_persistent_notice',
								notice_key: noticeKey,
								nonce: dismissNonce
							},
							success: function(response) {
								if (response.success) {
									// Notice dismissed successfully, fade it out
									\$notice.fadeOut(300, function() {
										\$notice.remove();
									});
								} else {
									// Fallback: let WordPress hide it normally
									\$notice.hide();
								}
							},
							error: function() {
								// Fallback: let WordPress hide it normally
								\$notice.hide();
							}
						});
					}

					return false; // Prevent further processing
				}
				// For non-persistent notices, let WordPress handle normally
			});
		});
		";

		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * Handle AJAX request to dismiss a persistent notice.
	 */
	/**
	 * Handle server-side dismissal via URL parameters.
	 */
	public static function handle_server_dismiss(): void {
		// Check for dismissal request.
		$dismiss_key   = sanitize_text_field( wp_unslash( $_GET['dismiss_notice'] ?? '' ) );
		$dismiss_nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );

		if ( empty( $dismiss_key ) ) {
			return;
		}

		// Verify nonce for security.
		if ( ! wp_verify_nonce( $dismiss_nonce, 'dismiss_notice_' . $dismiss_key ) ) {
			return; // Silently fail for security.
		}

		// Dismiss the notice.
		self::dismiss_persistent_notice( $dismiss_key );

		// Redirect back to clean URL (remove dismissal parameters).
		$current_url = remove_query_arg( array( 'dismiss_notice', 'nonce' ) );
		wp_safe_redirect( $current_url );
		exit;
	}

	/**
	 * Handle AJAX request to dismiss a persistent notice.
	 */
	public static function handle_dismiss_ajax(): void {
		// Security: Only logged-in users can dismiss notices.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Authentication required' );
		}

		$notice_key = sanitize_text_field( wp_unslash( $_POST['notice_key'] ?? '' ) );
		$nonce      = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

		if ( empty( $notice_key ) ) {
			wp_send_json_error( 'Invalid notice key' );
		}

		// Security: Verify notice-specific nonce.
		if ( ! wp_verify_nonce( $nonce, 'dismiss_notice_' . $notice_key ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Security: Only allow users to dismiss their own notices.
		$user_id  = get_current_user_id();
		$existing = Storage::get_user_meta( $user_id, self::USER_META_KEY, true );

		if ( ! is_array( $existing ) ) {
			wp_send_json_error( 'No notices found' );
		}

		// Verify the notice key actually exists for this user.
		$notice_exists = false;
		foreach ( $existing as $notice ) {
			if ( self::generate_notice_key( $notice ) === $notice_key ) {
				$notice_exists = true;
				break;
			}
		}

		if ( ! $notice_exists ) {
			wp_send_json_error( 'Notice not found' );
		}

		// Dismiss the notice.
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
			if ( self::generate_notice_key( $notice ) !== $notice_key ) {
				$filtered[] = $notice;
			}
		}

		Storage::update_user_meta( $user_id, self::USER_META_KEY, $filtered );
	}

	/**
	 * Clear all notices (current + persisted).
	 */
	public static function clear(): void {
		self::$current = array();

		$user_id = get_current_user_id();
		if ( $user_id ) {
			Storage::delete_user_meta( $user_id, self::USER_META_KEY );
		}
	}

	/**
	 * Get count of current notices.
	 */
	public static function count(): int {
		return count( self::get_all_notices() );
	}

	/**
	 * Check if there are notices.
	 */
	public static function has_notices(): bool {
		return self::count() > 0;
	}

	/**
	 * Get all notices.
	 *
	 * @return array<array<string, mixed>>
	 */
	public static function get_all(): array {
		return self::get_all_notices();
	}

	/**
	 * Get all notices (persisted + current).
	 *
	 * @return array<array<string, mixed>>
	 */
	private static function get_all_notices(): array {
		return array_merge( self::load_persisted(), self::$current );
	}
}
