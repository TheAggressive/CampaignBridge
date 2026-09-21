<?php
/**
 * Portable brand assets from WordPress theme settings.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Email\Brand_Kit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Copies the current Site Logo into a frozen email-safe asset snapshot. */
final class Theme_Brand_Asset_Reader {
	/**
	 * Read the active theme's custom logo when it is a portable raster image.
	 *
	 * @return array{url: string, alt: string, width: int, height: int, link_url: string}|null
	 */
	public function logo(): ?array {
		$attachment_id = (int) get_theme_mod( 'custom_logo', 0 );
		if ( 1 > $attachment_id || ! in_array( get_post_mime_type( $attachment_id ), array( 'image/png', 'image/jpeg', 'image/gif' ), true ) ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $image ) || ! isset( $image[0], $image[1], $image[2] ) ) {
			return null;
		}

		$url      = is_string( $image[0] ) ? esc_url_raw( $image[0], array( 'https' ) ) : '';
		$link_url = esc_url_raw( home_url( '/' ), array( 'https' ) );
		$width    = (int) $image[1];
		$height   = (int) $image[2];
		if ( '' === $url || 1 > $width || 1 > $height || Brand_Kit::MAX_LOGO_DIMENSION < $width || Brand_Kit::MAX_LOGO_DIMENSION < $height ) {
			return null;
		}

		$alt = trim( (string) Storage::get_core_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			$alt = trim( (string) get_bloginfo( 'name' ) );
		}
		$alt = sanitize_text_field( $alt );
		if ( '' === $alt ) {
			return null;
		}

		return array(
			'url'      => $url,
			'alt'      => mb_substr( $alt, 0, 160 ),
			'width'    => $width,
			'height'   => $height,
			'link_url' => $link_url,
		);
	}
}
