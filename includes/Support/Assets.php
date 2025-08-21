<?php
/**
 * CampaignBridge Assets Registration.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets Registration.
 */
class Assets {
	/**
	 * Register the admin assets.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_enqueue_scripts',
			function ( $hook ): void {
				// Only on our Template Manager screen.
				if ( 'campaignbridge_page_campaignbridge-template-manager' !== $hook ) {
					return;
				}
				$asset_path = __DIR__ . '/../../dist/admin/index.asset.php';
				$asset_path = realpath( $asset_path );
				if ( ! $asset_path || ! file_exists( $asset_path ) ) {
					return;
				}
				$asset = include $asset_path;

				// Ensure editor environment is available.
				wp_enqueue_editor();

				$deps = array_unique( array_merge( $asset['dependencies'] ?? array(), array( 'wp-edit-post', 'wp-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data', 'wp-api-fetch', 'wp-block-editor' ) ) );

				wp_enqueue_script(
					'cb-admin',
					plugins_url( 'dist/admin/index.js', dirname( __DIR__, 2 ) . '/campaignbridge.php' ),
					$deps,
					$asset['version'] ?? null,
					true
				);

				$css_file = dirname( $asset_path ) . '/style-index.css';
				if ( file_exists( $css_file ) ) {
					wp_enqueue_style(
						'cb-admin',
						plugins_url( 'dist/admin/style-index.css', dirname( __DIR__, 2 ) . '/campaignbridge.php' ),
						array(),
						$asset['version'] ?? null
					);
				}
			}
		);
	}
}
