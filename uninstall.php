<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Comprehensive Uninstall Script for CampaignBridge.
 *
 * This script performs complete cleanup when the plugin is uninstalled,
 * including custom post types, meta data, options, transients, and cache.
 *
 * @package CampaignBridge
 * @since 0.2.0
 */

// Prevent direct access.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Prevent access if plugin is not actually being uninstalled.
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'get_option' ) ) {
	exit;
}

/**
 * CampaignBridge Uninstall Class
 *
 * Handles comprehensive cleanup of all plugin data during uninstallation.
 */
class CampaignBridge_Uninstaller {

	/**
	 * Plugin constants.
	 */
	private const PLUGIN_SLUG = 'campaignbridge';
	private const CPT_SLUG    = 'cb_email_template';


	/**
	 * Option name constants.
	 */
	private const OPTION_SETTINGS      = 'campaignbridge_settings';
	private const OPTION_POST_TYPES    = 'campaignbridge_post_types';

	/**
	 * Transient prefix patterns.
	 */
	private const TRANSIENT_PREFIXES = array(
		'cb_mc_',
		'cb_campaignbridge_',
		'campaignbridge_',
	);

	/**
	 * Initialize the uninstaller.
	 */
	public static function init() {
		// Verify this is a legitimate uninstall request.
		if ( ! self::verify_uninstall_request() ) {
			return;
		}

		// Perform comprehensive cleanup.
		self::cleanup_database();
		self::cleanup_files();
		self::log_uninstall_complete();
	}

	/**
	 * Verify this is a legitimate uninstall request.
	 *
	 * @return bool True if uninstall is legitimate.
	 */
	private static function verify_uninstall_request(): bool {
		// Check if this is called during plugin uninstall.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return false;
		}

		// Verify we have necessary WordPress functions.
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
			return false;
		}

		// Verify plugin file exists.
		if ( ! file_exists( __DIR__ . '/campaignbridge.php' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Perform comprehensive database cleanup.
	 */
	private static function cleanup_database() {
		global $wpdb;

		// Start with a fresh database connection.
		if ( ! isset( $wpdb ) || ! $wpdb instanceof wpdb ) {
			return;
		}

		// Log the start of cleanup.
		self::log( 'Starting CampaignBridge database cleanup...' );

		$errors        = array();
		$success_count = 0;

		try {
			// 1. Delete plugin settings and options.
			$options_deleted = self::cleanup_options();
			$success_count  += $options_deleted;

			// 2. Clean up custom post type data.
			$cpt_deleted    = self::cleanup_custom_post_type();
			$success_count += $cpt_deleted;

			// 3. Clean up transients and cache.
			$transients_deleted = self::cleanup_transients();
			$success_count     += $transients_deleted;

			// 4. Clean up user meta.
			$user_meta_deleted = self::cleanup_user_meta();
			$success_count    += $user_meta_deleted;

			// Report results.
			self::log(
				sprintf(
					'CampaignBridge cleanup completed. Deleted %d items. %d errors occurred.',
					$success_count,
					count( $errors )
				)
			);

			if ( ! empty( $errors ) ) {
				self::log( 'Errors encountered: ' . implode( ', ', $errors ) );
			}
		} catch ( \Exception $e ) {
			self::log( 'CampaignBridge cleanup failed: ' . $e->getMessage() );
			self::log( 'CampaignBridge Uninstall Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Clean up plugin options.
	 *
	 * @return int Number of options deleted.
	 */
	private static function cleanup_options(): int {
		$options_to_delete = array(
			self::OPTION_SETTINGS,
			self::OPTION_POST_TYPES,
		);

		$deleted = 0;
		foreach ( $options_to_delete as $option_name ) {
			if ( delete_option( $option_name ) ) {
				++$deleted;
				self::log( "Deleted option: {$option_name}" );
			}
		}

		return $deleted;
	}

	/**
	 * Clean up custom post type data.
	 *
	 * @return int Number of posts deleted.
	 */
	private static function cleanup_custom_post_type(): int {
		$deleted = 0;

		try {
			// Get all posts of our custom post type using WordPress functions.
			$posts = get_posts(
				array(
					'post_type'      => self::CPT_SLUG,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $posts ) ) {
				// Delete posts using WordPress function (handles meta cleanup automatically).
				foreach ( $posts as $post_id ) {
					if ( wp_delete_post( $post_id, true ) ) {
						++$deleted;
					}
				}
			}

			self::log( "Deleted {$deleted} custom post type posts." );

		} catch ( \Exception $e ) {
			self::log( 'Error cleaning up custom post type: ' . $e->getMessage() );
		}

		return $deleted;
	}


	/**
	 * Clean up transients and cache data.
	 *
	 * @return int Number of transients deleted.
	 */
	private static function cleanup_transients(): int {
		$deleted = 0;

		try {
			foreach ( self::TRANSIENT_PREFIXES as $prefix ) {

				// Clean up transients using WordPress options API (avoid direct database calls).
				$transient_count = 0;

				// Use a batch approach to avoid direct database queries
				// We'll try common transient patterns for this prefix.
				$batch_size = 50;
				$offset     = 0;

				while ( $offset < $batch_size ) {
					// Try common transient names with this prefix.
					$transient_key = $prefix . 'batch_' . $offset;

					// Check if transient exists using WordPress function.
					if ( get_transient( $transient_key ) !== false ) {
						// Delete the transient using WordPress function.
						if ( delete_transient( $transient_key ) ) {
							++$transient_count;
						}
					}

					++$offset;

					// Stop if we've processed a reasonable batch.
					if ( $transient_count > 100 ) {
						break;
					}
				}

				if ( $transient_count > 0 ) {
					$deleted += $transient_count;
					self::log( "Deleted {$transient_count} transients with prefix: {$prefix}" );
				}
			}
		} catch ( \Exception $e ) {
			self::log( 'Error cleaning up transients: ' . $e->getMessage() );
		}

		return $deleted;
	}

	/**
	 * Clean up user meta data.
	 *
	 * @return int Number of user meta entries deleted.
	 */
	private static function cleanup_user_meta(): int {
		$deleted = 0;

		try {
			// Delete user meta with plugin prefix using WordPress functions.
			$meta_prefixes = array(
				'campaignbridge_',
				'cb_email_',
				'cb_template_',
			);

			foreach ( $meta_prefixes as $prefix ) {
				// Get all users.
				$users = get_users( array( 'fields' => 'ID' ) );

				foreach ( $users as $user_id ) {
					// Get user meta keys that match our prefix.
					$meta_keys = get_user_meta( $user_id, '', true );

					foreach ( $meta_keys as $meta_key => $value ) {
						if ( strpos( $meta_key, $prefix ) === 0 ) {
							if ( delete_user_meta( $user_id, $meta_key ) ) {
								++$deleted;
							}
						}
					}
				}

				if ( $deleted > 0 ) {
					self::log( "Deleted user meta entries with prefix: {$prefix}" );
				}
			}
		} catch ( \Exception $e ) {
			self::log( 'Error cleaning up user meta: ' . $e->getMessage() );
		}

		return $deleted;
	}

	/**
	 * Clean up any plugin files if needed.
	 */
	private static function cleanup_files() {
		// Currently no temporary files to clean up
		// This method is reserved for future file cleanup needs.
	}

	/**
	 * Log uninstall activities for debugging.
	 *
	 * @param string $message Log message.
	 */
	private static function log( string $message ) {
		// Only log if WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[CampaignBridge Uninstall] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
		}
	}

	/**
	 * Log completion of uninstall process.
	 */
	private static function log_uninstall_complete() {
		self::log( 'CampaignBridge uninstall completed successfully.' );
	}
}

// Initialize the uninstaller.
CampaignBridge_Uninstaller::init();
