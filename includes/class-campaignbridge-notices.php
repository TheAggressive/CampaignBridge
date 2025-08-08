<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CampaignBridge_Notices {
	private static $notices = array();

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'render' ) );
	}

	public static function add( $message, $type = 'info' ) {
		self::$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
	}

	public static function success( $message ) {
		self::add( $message, 'success' ); }
	public static function warning( $message ) {
		self::add( $message, 'warning' ); }
	public static function error( $message ) {
		self::add( $message, 'error' ); }
	public static function info( $message ) {
		self::add( $message, 'info' ); }

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

CampaignBridge_Notices::init();
