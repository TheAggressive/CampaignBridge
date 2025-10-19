<?php
/**
 * Error Handler for CampaignBridge.
 *
 * Provides comprehensive error handling, logging, and graceful failure management
 * following WordPress best practices and modern PHP patterns.
 *
 * @package CampaignBridge\Core
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprehensive error handling and logging
 */
class Error_Handler {
	/**
	 * Log levels
	 */
	private const LOG_LEVEL_DEBUG   = 0;
	private const LOG_LEVEL_INFO    = 1;
	private const LOG_LEVEL_WARNING = 2;
	private const LOG_LEVEL_ERROR   = 3;

	/**
	 * Current log level
	 *
	 * @var int
	 */
	private int $log_level;

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private string $log_file;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor
	 *
	 * Initializes the error handler with default log level and file path.
	 */
	public function __construct() {
		$this->log_level = $this->get_log_level();
		$this->log_file  = WP_CONTENT_DIR . '/campaignbridge.log';
	}

	/**
	 * Get singleton instance
	 */
	private static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Handle exceptions gracefully
	 *
	 * @param \Throwable $exception The exception to handle.
	 * @param string     $context  Additional context information.
	 * @return void
	 */
	public function handle_exception( \Throwable $exception, string $context = '' ): void {
		$this->error(
			'Exception occurred',
			array(
				'message' => $exception->getMessage(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
				'trace'   => $exception->getTraceAsString(),
				'context' => $context,
			)
		);

		// In production, show user-friendly error.
		if ( ! WP_DEBUG ) {
			wp_die(
				esc_html__( 'An error occurred. Please try again or contact support.', 'campaignbridge' ),
				esc_html__( 'Error', 'campaignbridge' ),
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Safe operation wrapper
	 *
	 * @param callable $operation Function to execute.
	 * @param string   $context   Operation context for logging.
	 * @return mixed
	 */
	public function safe_operation( callable $operation, string $context = '' ) {
		try {
			return $operation();
		} catch ( \Throwable $e ) {
			$this->handle_exception( $e, $context );
			return null;
		}
	}

	/**
	 * Debug level logging
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::get_instance()->log( self::LOG_LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Info level logging
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::get_instance()->log( self::LOG_LEVEL_INFO, $message, $context );
	}

	/**
	 * Warning level logging
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::get_instance()->log( self::LOG_LEVEL_WARNING, $message, $context );
	}

	/**
	 * Error level logging
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::get_instance()->log( self::LOG_LEVEL_ERROR, $message, $context );
	}

	/**
	 * Core logging method
	 *
	 * @param int                  $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	private function log( int $level, string $message, array $context = array() ): void {
		if ( ! $this->should_log( $level ) ) {
			return;
		}

		$log_entry = array(
			'timestamp'   => current_time( 'mysql' ),
			'level'       => $this->get_level_name( $level ),
			'message'     => $message,
			'context'     => $context,
			'user_id'     => get_current_user_id(),
			'request_uri' => sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
			'user_agent'  => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
		);

		$log_line = wp_json_encode( $log_entry ) . PHP_EOL;

		// Use error_log for production or file_put_contents for development.
		if ( WP_DEBUG ) {
			file_put_contents( $this->log_file, $log_line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Logging only.
		} else {
			error_log( $log_line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,CampaignBridge.Sniffs.DirectLogging.DirectLoggingFunction -- Production logging within Error_Handler.
		}
	}

	/**
	 * Check if message should be logged based on level
	 *
	 * @param int $level Log level to check.
	 * @return bool
	 */
	private function should_log( int $level ): bool {
		return $level >= $this->log_level;
	}

	/**
	 * Get log level name
	 *
	 * @param int $level Log level.
	 * @return string
	 */
	private function get_level_name( int $level ): string {
		return match ( $level ) {
			self::LOG_LEVEL_DEBUG   => 'DEBUG',
			self::LOG_LEVEL_INFO    => 'INFO',
			self::LOG_LEVEL_WARNING => 'WARNING',
			self::LOG_LEVEL_ERROR   => 'ERROR',
			default                 => 'UNKNOWN',
		};
	}

	/**
	 * Get current log level from settings
	 *
	 * @return int
	 */
	private function get_log_level(): int {
		// Security: Only allow admin users to access log level settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::LOG_LEVEL_ERROR; // Default to most restrictive level for non-admins.
		}

		$level = \CampaignBridge\Core\Storage::get_option( 'campaignbridge_log_level', 'INFO' );

		return match ( strtoupper( $level ) ) {
			'DEBUG'   => self::LOG_LEVEL_DEBUG,
			'INFO'    => self::LOG_LEVEL_INFO,
			'WARNING' => self::LOG_LEVEL_WARNING,
			'ERROR'   => self::LOG_LEVEL_ERROR,
			default   => self::LOG_LEVEL_INFO,
		};
	}

	/**
	 * Register WordPress error handling hooks
	 *
	 * @return void
	 */
	public function register_error_handler(): void {
		// Use WordPress hooks instead of PHP error handlers for better security.
		add_action( 'wp_php_error', array( $this, 'handle_wp_php_error' ), 10, 2 );
		add_action( 'wp_die_handler', array( $this, 'handle_wp_die' ) );
	}

	/**
	 * Handle WordPress PHP errors
	 *
	 * @param array<string, mixed> $error Error details.
	 * @param string               $message Error message.
	 * @return void
	 */
	public function handle_wp_php_error( array $error, string $message ): void {
		$this->error(
			'WordPress PHP Error',
			array(
				'message' => $message,
				'error'   => $error,
			)
		);
	}

	/**
	 * Handle WordPress die events
	 *
	 * @param string $message Die message.
	 * @return void
	 */
	public function handle_wp_die( string $message ): void {
		$this->error(
			'WordPress Die Event',
			array(
				'message' => $message,
			)
		);
	}
}
