<?php
/**
 * Screen-specific asset loading.
 *
 * @package CampaignBridge\Admin\Core
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Loads traditional and compiled assets declared by an admin screen. */
final class Screen_Asset_Loader {
	/**
	 * Enqueue the assets declared in a screen configuration.
	 *
	 * @param string               $screen_name Screen name.
	 * @param string               $type        Screen type.
	 * @param array<string, mixed> $config      Screen configuration.
	 */
	public static function enqueue( string $screen_name, string $type, array $config ): void {
		global $screen;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for tab navigation, not form processing.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : null;
		$screen      = new Screen_Context( $screen_name, $type, $current_tab, null );

		self::enqueue_traditional_assets( $screen, $config['assets'] ?? array() );
		self::enqueue_built_assets( $screen, $config['assets'] ?? array() );
	}

	/**
	 * Enqueue styles and scripts without generated asset metadata.
	 *
	 * @param Screen_Context       $screen Screen context.
	 * @param array<string, mixed> $assets Asset configuration.
	 */
	private static function enqueue_traditional_assets( Screen_Context $screen, array $assets ): void {
		if ( isset( $assets['styles'] ) && is_array( $assets['styles'] ) ) {
			foreach ( $assets['styles'] as $handle => $src ) {
				if ( is_string( $handle ) && is_string( $src ) ) {
					$screen->enqueue_style( $handle, $src );
				}
			}
		}

		if ( ! isset( $assets['scripts'] ) || ! is_array( $assets['scripts'] ) ) {
			return;
		}

		foreach ( $assets['scripts'] as $handle => $script ) {
			if ( ! is_string( $handle ) || ( ! is_string( $script ) && ! is_array( $script ) ) ) {
				continue;
			}

			$src  = is_array( $script ) ? $script['src'] ?? '' : $script;
			$deps = is_array( $script ) && isset( $script['deps'] ) && is_array( $script['deps'] ) ? $script['deps'] : array( 'jquery' );
			if ( is_string( $src ) && '' !== $src ) {
				$screen->enqueue_script( $handle, $src, $deps );
			}
		}
	}

	/**
	 * Enqueue styles and scripts with generated asset metadata.
	 *
	 * @param Screen_Context       $screen Screen context.
	 * @param array<string, mixed> $assets Asset configuration.
	 */
	private static function enqueue_built_assets( Screen_Context $screen, array $assets ): void {
		self::enqueue_built_styles( $screen, $assets['asset_styles'] ?? array() );
		self::enqueue_built_scripts( $screen, $assets['asset_scripts'] ?? array() );

		$combined_assets = $assets['asset_both'] ?? array();
		if ( ! is_array( $combined_assets ) ) {
			return;
		}

		foreach ( $combined_assets as $handle => $asset_file ) {
			if ( is_string( $handle ) && is_string( $asset_file ) ) {
				$screen->asset_enqueue( $handle, $asset_file );
			}
		}
	}

	/**
	 * Enqueue compiled styles.
	 *
	 * @param Screen_Context $screen Screen context.
	 * @param mixed          $styles Style configuration.
	 */
	private static function enqueue_built_styles( Screen_Context $screen, $styles ): void {
		if ( ! is_array( $styles ) ) {
			return;
		}

		foreach ( $styles as $handle => $asset_data ) {
			if ( ! is_string( $handle ) ) {
				continue;
			}

			if ( is_string( $asset_data ) ) {
				$screen->asset_enqueue_style( $handle, $asset_data );
				continue;
			}

			if ( is_array( $asset_data ) ) {
				$asset_file = $asset_data['src'] ?? $asset_data['path'] ?? '';
				$deps       = isset( $asset_data['deps'] ) && is_array( $asset_data['deps'] ) ? $asset_data['deps'] : array();
				if ( is_string( $asset_file ) && '' !== $asset_file ) {
					$screen->asset_enqueue_style( $handle, $asset_file, $deps );
				}
			}
		}
	}

	/**
	 * Enqueue compiled scripts.
	 *
	 * @param Screen_Context $screen  Screen context.
	 * @param mixed          $scripts Script configuration.
	 */
	private static function enqueue_built_scripts( Screen_Context $screen, $scripts ): void {
		if ( ! is_array( $scripts ) ) {
			return;
		}

		foreach ( $scripts as $handle => $asset_data ) {
			if ( ! is_string( $handle ) ) {
				continue;
			}

			if ( is_string( $asset_data ) ) {
				$screen->asset_enqueue_script( $handle, $asset_data );
				continue;
			}

			if ( is_array( $asset_data ) ) {
				$asset_file = $asset_data['src'] ?? $asset_data['path'] ?? '';
				$deps       = isset( $asset_data['deps'] ) && is_array( $asset_data['deps'] ) ? $asset_data['deps'] : array();
				$in_footer  = ! isset( $asset_data['in_footer'] ) || true === $asset_data['in_footer'];
				if ( is_string( $asset_file ) && '' !== $asset_file ) {
					$screen->asset_enqueue_script( $handle, $asset_file, $deps, $in_footer );
				}
			}
		}
	}
}
