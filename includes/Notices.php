<?php
/**
 * Admin Notices Management System for CampaignBridge.
 *
 * This class provides a comprehensive system for managing and displaying
 * admin notices throughout the CampaignBridge plugin. It handles notice
 * queuing, rendering, and lifecycle management with support for multiple
 * notice types and automatic cleanup.
 *
 * This class ensures consistent and professional user feedback
 * throughout the CampaignBridge admin interface.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace CampaignBridge;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Admin notices helper.
 *
 * Queues notices and renders them on admin screens.
 */
class Notices {
	/**
	 * Notice type constants.
	 */
	private const NOTICE_SUCCESS = 'success';
	private const NOTICE_WARNING = 'warning';
	private const NOTICE_ERROR   = 'error';
	private const NOTICE_INFO    = 'info';

	/**
	 * Maximum notice message length.
	 */
	private const MAX_MESSAGE_LENGTH = 1000;

	/**
	 * Notice queue.
	 *
	 * @var array<int,array{message:string,type:string}>
	 */
	private static $notices = array();

	/**
	 * Hook renderers for standard and network admin.
	 *
	 * @return void
	 */
	public static function init() {
		// Hook into admin notices.
		\add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		\add_action( 'network_admin_notices', array( __CLASS__, 'render' ) );
	}


	/**
	 * Queue a notice.
	 *
	 * @param string $message HTML/text message (will be kses-escaped on render).
	 * @param string $type    Notice type: success|warning|error|info.
	 * @return void
	 */
	public static function add( string $message, string $type = self::NOTICE_INFO ): void {
		// Validate and sanitize input.
		$message = self::sanitize_message( $message );
		$type    = self::validate_notice_type( $type );

		if ( ! empty( $message ) && ! empty( $type ) ) {
			self::$notices[] = array(
				'message' => $message,
				'type'    => $type,
			);
		}
	}

	/**
	 * Queue a success notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function success( string $message ): void {
		self::add( $message, self::NOTICE_SUCCESS );
	}

	/**
	 * Queue a warning notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function warning( string $message ): void {
		self::add( $message, self::NOTICE_WARNING );
	}

	/**
	 * Queue an error notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function error( string $message ): void {
		self::add( $message, self::NOTICE_ERROR );
	}

	/**
	 * Queue an informational notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function info( string $message ): void {
		self::add( $message, self::NOTICE_INFO );
	}

	/**
	 * Render all queued notices and clear the queue.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( empty( self::$notices ) ) {
			return;
		}

		foreach ( self::$notices as $notice ) {
			$class = self::get_notice_css_class( $notice['type'] );
			printf(
				'<div class="%1$s is-dismissible"><p>%2$s</p></div>',
				\esc_attr( $class ),
				\wp_kses_post( $notice['message'] )
			);
		}

		// Clear notices after displaying.
		self::$notices = array();
	}


	/**
	 * Get the CSS class for a notice type.
	 *
	 * @param string $type Notice type.
	 * @return string CSS class string.
	 */
	private static function get_notice_css_class( string $type ): string {
		$class = 'notice';

		switch ( $type ) {
			case self::NOTICE_SUCCESS:
				$class .= ' notice-success';
				break;
			case self::NOTICE_WARNING:
				$class .= ' notice-warning';
				break;
			case self::NOTICE_ERROR:
				$class .= ' notice-error';
				break;
			default:
				$class .= ' notice-info';
		}

		return $class;
	}

	/**
	 * Sanitize and validate a notice message.
	 *
	 * @param string $message Raw message content.
	 * @return string Sanitized message.
	 */
	private static function sanitize_message( string $message ): string {
		// Trim whitespace and limit length.
		$message = trim( $message );

		if ( strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			$message = substr( $message, 0, self::MAX_MESSAGE_LENGTH ) . '...';
		}

		// Basic sanitization - we'll use wp_kses_post in render() for final output.
		return wp_strip_all_tags( $message );
	}

	/**
	 * Validate notice type.
	 *
	 * @param string $type Notice type to validate.
	 * @return string Valid notice type or empty string if invalid.
	 */
	private static function validate_notice_type( string $type ): string {
		$valid_types = array(
			self::NOTICE_SUCCESS,
			self::NOTICE_WARNING,
			self::NOTICE_ERROR,
			self::NOTICE_INFO,
		);

		return in_array( $type, $valid_types, true ) ? $type : '';
	}

	/**
	 * Get the count of queued notices.
	 *
	 * @return int Number of notices in queue.
	 */
	public static function count(): int {
		return count( self::$notices );
	}

	/**
	 * Check if there are any queued notices.
	 *
	 * @return bool True if notices exist, false otherwise.
	 */
	public static function has_notices(): bool {
		return ! empty( self::$notices );
	}

	/**
	 * Clear all queued notices without rendering.
	 *
	 * @return int Number of notices cleared.
	 */
	public static function clear(): int {
		$count         = count( self::$notices );
		self::$notices = array();
		return $count;
	}

	/**
	 * Get all queued notices (for debugging or external processing).
	 *
	 * @return array<int,array{message:string,type:string}> Array of notices.
	 */
	public static function get_all(): array {
		return self::$notices;
	}
}
