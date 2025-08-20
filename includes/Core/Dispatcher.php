<?php
/**
 * CampaignBridge email campaign dispatcher.
 *
 * Builds email content blocks from posts/sections and dispatches them
 * to the active provider (e.g., Mailchimp or HTML download).
 *
 * @package CampaignBridge
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
	 * Generate email blocks and send via provider.
	 *
	 * @param int[] $post_ids    Selected post IDs.
	 * @param array $settings    Plugin settings array.
	 * @param array $sections_map Optional map of section_key => post_id for Mailchimp sections.
	 * @param array $providers   Provider instances map.
	 * @return bool True on success, false on failure.
	 */
	public static function generate_and_send_campaign( array $post_ids, array $settings, array $sections_map, array $providers ): bool {
		$blocks = array();

		if ( ! empty( $sections_map ) ) {
			foreach ( $sections_map as $section_key => $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}
				$img                    = get_the_post_thumbnail_url( $post_id, 'medium' );
				$content_html           = apply_filters( 'the_content', $post->post_content );
				$link                   = get_permalink( $post_id );
				$title_raw              = (string) get_post_field( 'post_title', $post_id );
				$title_decoded          = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
				$title_clean            = preg_replace( '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u', '', $title_decoded );
				$blocks[ $section_key ] = sprintf(
					"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
					$img ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $img ) ) : '',
					esc_html( $title_clean ),
					wp_kses_post( $content_html ),
					esc_url( $link )
				);
			}
			return self::dispatch_to_provider( $blocks, $settings, $providers );
		}

		foreach ( array_slice( $post_ids, 0, 8 ) as $index => $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$img           = get_the_post_thumbnail_url( $post_id, 'medium' );
			$content_html  = apply_filters( 'the_content', $post->post_content );
			$link          = get_permalink( $post_id );
			$title_raw     = (string) get_post_field( 'post_title', $post_id );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$title_clean   = preg_replace( '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u', '', $title_decoded );
			$blocks[ 'CONTENT_BLOCK_' . ( $index + 1 ) ] = sprintf(
				"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
				$img ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $img ) ) : '',
				esc_html( $title_clean ),
				wp_kses_post( $content_html ),
				esc_url( $link )
			);
		}
		return self::dispatch_to_provider( $blocks, $settings, $providers );
	}

	/**
	 * Dispatch the prepared blocks to the selected provider.
	 *
	 * @param array $blocks    section_key => HTML string.
	 * @param array $settings  Plugin settings.
	 * @param array $providers Providers map.
	 * @return bool
	 */
	public static function dispatch_to_provider( array $blocks, array $settings, array $providers ): bool {
		$provider_slug = $settings['provider'] ?? 'mailchimp';
		$provider      = $providers[ $provider_slug ] ?? null;

		if ( ! $provider ) {
			if ( class_exists( '\\CampaignBridge\\Notices' ) ) {
				Notices::error( esc_html__( 'No valid provider selected.', 'campaignbridge' ) );
			}
			return false;
		}
		return $provider->send_campaign( $blocks, $settings );
	}
}
