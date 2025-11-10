<?php
/**
 * Email Campaign Dispatcher for CampaignBridge.
 *
 * Processes posts into email content blocks and dispatches campaigns
 * to configured email providers (Mailchimp, HTML export, etc.).
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace CampaignBridge\Core;

use CampaignBridge\Notices;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Builds email content blocks from posts/sections and dispatches them
 * to the active provider (e.g., Mailchimp or HTML download).
 */
class Dispatcher {
	/**
	 * Image size for post thumbnails.
	 */
	private const THUMBNAIL_SIZE = 'medium';

	/**
	 * Maximum number of posts to process for performance.
	 */
	private const MAX_POSTS_LIMIT = 8;

	/**
	 * Prefix for content block keys.
	 */
	private const CONTENT_BLOCK_PREFIX = 'CONTENT_BLOCK_';

	/**
	 * Regex pattern to clean image dimensions from titles.
	 */
	private const TITLE_DIMENSION_PATTERN = '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u';
	/**
	 * Generate email blocks and send via provider.
	 *
	 * @param int[]                $post_ids    Selected post IDs.
	 * @param array<string, mixed> $settings    Plugin settings (should contain decrypted API keys for provider use).
	 * @param array<string, int>   $sections_map Optional section mapping for Mailchimp.
	 * @param array<string, mixed> $providers   Provider instances.
	 * @return bool True on success.
	 */
	public static function generate_and_send_campaign( array $post_ids, array $settings, array $sections_map, array $providers ): bool {
		// Ensure settings contain decrypted API keys for provider use.
		$decrypted_settings = self::ensure_decrypted_settings( $settings );

		// Handle sections map (Mailchimp sections).
		if ( ! empty( $sections_map ) ) {
			return self::process_sections_and_dispatch( $sections_map, $decrypted_settings, $providers );
		}

		// Handle regular posts (limit to 8 for performance).
		return self::process_posts_and_dispatch( $post_ids, $decrypted_settings, $providers );
	}

	/**
	 * Process Mailchimp sections and dispatch to provider.
	 *
	 * @param array<string, int>   $sections_map section_key => post_id mapping.
	 * @param array<string, mixed> $settings     Plugin settings.
	 * @param array<string, mixed> $providers    Provider instances.
	 * @return bool True on success.
	 */
	private static function process_sections_and_dispatch( array $sections_map, array $settings, array $providers ): bool {
		$blocks = array();

		foreach ( $sections_map as $section_key => $post_id ) {
			$blocks[ $section_key ] = self::create_post_block( $post_id );
		}

		return self::dispatch_to_provider( $blocks, $settings, $providers );
	}

	/**
	 * Process regular posts and dispatch to provider.
	 *
	 * @param int[]                $post_ids  Post IDs to process.
	 * @param array<string, mixed> $settings  Plugin settings.
	 * @param array<string, mixed> $providers Provider instances.
	 * @return bool True on success.
	 */
	private static function process_posts_and_dispatch( array $post_ids, array $settings, array $providers ): bool {
		$blocks = array();

		foreach ( array_slice( $post_ids, 0, self::MAX_POSTS_LIMIT ) as $index => $post_id ) {
			$blocks[ self::CONTENT_BLOCK_PREFIX . ( $index + 1 ) ] = self::create_post_block( $post_id );
		}

		return self::dispatch_to_provider( $blocks, $settings, $providers );
	}

	/**
	 * Create an email block from a post.
	 *
	 * @param int $post_id The post ID.
	 * @return string The HTML block content.
	 */
	private static function create_post_block( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$img           = get_the_post_thumbnail_url( $post_id, self::THUMBNAIL_SIZE ) ? get_the_post_thumbnail_url( $post_id, self::THUMBNAIL_SIZE ) : null;
		$content_html  = apply_filters( 'the_content', $post->post_content );
		$link          = get_permalink( $post_id );
		$title_raw     = get_post_field( 'post_title', $post_id );
		$title_raw     = $title_raw ? $title_raw : '';
		$title_decoded = html_entity_decode( (string) $title_raw, ENT_QUOTES, 'UTF-8' );
		$title_clean   = preg_replace( self::TITLE_DIMENSION_PATTERN, '', $title_decoded ) ? preg_replace( self::TITLE_DIMENSION_PATTERN, '', $title_decoded ) : '';

		return self::generate_post_html_block( $img, $title_clean, $content_html, $link ? $link : '' );
	}

	/**
	 * Generate HTML block for a post.
	 *
	 * @param string|null $image_url    Post thumbnail URL.
	 * @param string      $title        Post title.
	 * @param string      $content_html Post content HTML.
	 * @param string      $link         Post permalink.
	 * @return string The HTML block.
	 */
	private static function generate_post_html_block( ?string $image_url, string $title, string $content_html, string $link ): string {
		return sprintf(
			"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
			$image_url ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $image_url ) ) : '',
			esc_html( $title ),
			wp_kses_post( $content_html ),
			esc_url( $link )
		);
	}

	/**
	 * Dispatch the prepared blocks to the selected provider.
	 *
	 * @param array<string, string> $blocks               section_key => HTML string.
	 * @param array<string, mixed>  $settings             Plugin settings with decrypted API keys.
	 * @param array<string, mixed>  $providers            Providers map.
	 * @return bool
	 */
	public static function dispatch_to_provider( array $blocks, array $settings, array $providers ): bool {
		$provider_slug = $settings['provider'] ?? 'html';
		$provider      = $providers[ $provider_slug ] ?? null;

		if ( ! $provider ) {
			if ( class_exists( '\\CampaignBridge\\Notices' ) ) {
				Notices::error( esc_html__( 'No valid provider selected.', 'campaignbridge' ) );
			}
			return false;
		}
		return $provider->send_campaign( $blocks, $settings );
	}

	/**
	 * Ensure settings contain decrypted API keys for provider use.
	 *
	 * This method checks if sensitive fields in settings are encrypted and decrypts them
	 * if necessary. This ensures providers receive usable API keys for external API calls.
	 *
	 * @param array<string, mixed> $settings Raw settings that may contain encrypted sensitive data.
	 * @return array<string, mixed> Settings with decrypted sensitive fields.
	 */
	private static function ensure_decrypted_settings( array $settings ): array {
		$decrypted_settings = $settings;

		// Decrypt sensitive fields that providers need.
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $decrypted_settings[ $field ] ) && ! empty( $decrypted_settings[ $field ] ) ) {
				// Check if field appears to be encrypted (base64 encoded binary data).
				$value = $decrypted_settings[ $field ];
				if ( is_string( $value ) && self::is_encrypted_value( $value ) ) {
					try {
						$decrypted_settings[ $field ] = Encryption::decrypt( $value );
					} catch ( \Throwable $e ) {
						// Log error but don't expose details.
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							\CampaignBridge\Core\Error_Handler::error(
								'CampaignBridge Dispatcher: Failed to decrypt sensitive field',
								array(
									'field' => $field,
									'error' => $e->getMessage(),
								)
							);
						}

						// Remove corrupted sensitive data rather than passing invalid data.
						unset( $decrypted_settings[ $field ] );
					}
				}
			}
		}

		return $decrypted_settings;
	}

	/**
	 * Check if a value appears to be encrypted data.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	private static function is_encrypted_value( string $value ): bool {
		// Check if it's base64 encoded (encrypted data is base64 encoded).
		if ( ! preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value ) ) {
			return false;
		}

		// Try to decode and check if it looks like encrypted binary data.
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded ) {
			return false;
		}

		// Encrypted data should be at least 28 bytes (IV + tag + minimal ciphertext).
		return strlen( $decoded ) >= 28;
	}
}
