<?php
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
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * Queue a notice.
	 *
	 * @param string $message HTML/text message (will be kses-escaped on render).
	 * @param string $type    success|warning|error|info.
	 * @return void
	 */
	public static function add( $message, $type = 'info' ) {
		self::$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
	}

	/**
	 * Queue a success notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function success( $message ) {
		self::add( $message, 'success' ); }

	/**
	 * Queue a warning notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function warning( $message ) {
		self::add( $message, 'warning' ); }

	/**
	 * Queue an error notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function error( $message ) {
		self::add( $message, 'error' ); }

	/**
	 * Queue an informational notice.
	 *
	 * @param string $message Message text or HTML.
	 * @return void
	 */
	public static function info( $message ) {
		self::add( $message, 'info' ); }

	/**
	 * Render all queued notices and clear the queue.
	 *
	 * @return void
	 */
	public static function render() {
		if ( empty( self::$notices ) ) {
			return;
		}
		foreach ( self::$notices as $notice ) {
			$class = 'notice';
			switch ( $notice['type'] ) {
				case 'success':
					$class .= ' notice-success';
					break;
				case 'warning':
					$class .= ' notice-warning';
					break;
				case 'error':
					$class .= ' notice-error';
					break;
				default:
					$class .= ' notice-info';
			}
			printf(
				'<div class="%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $class ),
				wp_kses_post( $notice['message'] )
			);
		}
		self::$notices = array();
	}
}
