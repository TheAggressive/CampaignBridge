<?php
/**
 * Google Fonts catalogue lookup and deterministic font resolution.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Core\Http_Client;
use CampaignBridge\Core\Storage;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps remote Google data outside the domain model and admin UI. */
final class Google_Fonts {
	private const API_URL       = 'https://www.googleapis.com/webfonts/v1/webfonts';
	private const CSS_URL       = 'https://fonts.googleapis.com/css2';
	private const CACHE_KEY     = 'campaignbridge_google_fonts_catalogue_v1';
	private const CACHE_SECONDS = DAY_IN_SECONDS;
	private const MAX_RESULTS   = 20;

	/**
	 * Search the official Google Web Fonts catalogue.
	 *
	 * @param string $query User-entered family search.
	 * @return array<int, array{family: string, category: string, variants: array<int, string>}>
	 */
	public function search( string $query ): array|WP_Error {
		$query = trim( $query );
		if ( strlen( $query ) < 2 || strlen( $query ) > 80 ) {
			return new WP_Error( 'invalid_font_search', __( 'Enter between 2 and 80 characters.', 'campaignbridge' ) );
		}

		$catalogue = $this->catalogue();
		if ( is_wp_error( $catalogue ) ) {
			return $catalogue;
		}

		$needle  = strtolower( $query );
		$matches = array();
		foreach ( $catalogue as $font ) {
			$family = $font['family'] ?? null;
			if ( ! is_string( $family ) || ! str_contains( strtolower( $family ), $needle ) ) {
				continue;
			}

			$matches[] = array(
				'family'   => $family,
				'category' => is_string( $font['category'] ?? null ) ? $font['category'] : 'sans-serif',
				'variants' => $this->variants( $font['variants'] ?? array() ),
			);
			if ( self::MAX_RESULTS === count( $matches ) ) {
				break;
			}
		}

		return $matches;
	}

	/**
	 * Resolve one exact catalogue family to a stable CSS2 stylesheet URL.
	 *
	 * @param string $requested_family Exact catalogue family.
	 * @return array{slug: string, name: string, family: string, weights: array<int, int>, url: string}|WP_Error
	 */
	public function resolve( string $requested_family ): array|WP_Error {
		$requested_family = trim( $requested_family );
		$catalogue        = $this->catalogue();
		if ( is_wp_error( $catalogue ) ) {
			return $catalogue;
		}

		$match = null;
		foreach ( $catalogue as $font ) {
			if ( is_string( $font['family'] ?? null ) && 0 === strcasecmp( $font['family'], $requested_family ) ) {
				$match = $font;
				break;
			}
		}
		if ( null === $match ) {
			return new WP_Error( 'google_font_not_found', __( 'That family is not in the Google Fonts catalogue.', 'campaignbridge' ) );
		}

		$family         = (string) $match['family'];
		$weights        = $this->weights( $match['variants'] ?? array() );
		$encoded_family = str_replace( '%20', '+', rawurlencode( $family ) );
		$css_url        = self::CSS_URL . '?family=' . $encoded_family . ':wght@' . implode( ';', $weights ) . '&display=swap';
		$fallback       = 'serif' === ( $match['category'] ?? '' ) ? 'Georgia,serif' : 'Arial,Helvetica,sans-serif';

		return array(
			'slug'    => 'custom',
			'name'    => $family,
			'family'  => $family . ',' . $fallback,
			'weights' => $weights,
			'url'     => $css_url,
		);
	}

	/**
	 * Fetch or read the cached remote catalogue.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function catalogue(): array|WP_Error {
		$cached = Storage::get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$key = defined( 'CAMPAIGNBRIDGE_GOOGLE_FONTS_API_KEY' ) ? constant( 'CAMPAIGNBRIDGE_GOOGLE_FONTS_API_KEY' ) : '';
		$key = apply_filters( 'campaignbridge_google_fonts_api_key', $key );
		if ( ! is_string( $key ) || '' === trim( $key ) ) {
			return new WP_Error( 'google_fonts_not_configured', __( 'Google Fonts lookup is not configured. Add CAMPAIGNBRIDGE_GOOGLE_FONTS_API_KEY to wp-config.php.', 'campaignbridge' ) );
		}

		$response = Http_Client::get(
			self::API_URL . '?sort=popularity&fields=items(family%2Ccategory%2Cvariants)',
			array(
				'campaignbridge_retry' => false,
				'headers'              => array( 'X-Goog-Api-Key' => trim( $key ) ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== ( $response['status_code'] ?? 0 ) ) {
			return new WP_Error( 'google_fonts_unavailable', __( 'The Google Fonts catalogue is temporarily unavailable.', 'campaignbridge' ) );
		}

		$decoded = json_decode( is_string( $response['body'] ?? null ) ? $response['body'] : '', true );
		$items   = is_array( $decoded ) && is_array( $decoded['items'] ?? null ) ? $decoded['items'] : null;
		if ( null === $items ) {
			return new WP_Error( 'invalid_google_fonts_response', __( 'Google Fonts returned an invalid catalogue.', 'campaignbridge' ) );
		}

		Storage::set_transient( self::CACHE_KEY, $items, self::CACHE_SECONDS );
		return $items;
	}

	/**
	 * Keep only string variant identifiers.
	 *
	 * @param mixed $raw Remote variants.
	 * @return array<int, string>
	 */
	private function variants( mixed $raw ): array {
		return is_array( $raw ) ? array_values( array_filter( $raw, 'is_string' ) ) : array();
	}

	/**
	 * Select portable weights that the family actually provides.
	 *
	 * @param mixed $variants Remote variants.
	 * @return array<int, int>
	 */
	private function weights( mixed $variants ): array {
		$available = array();
		foreach ( $this->variants( $variants ) as $variant ) {
			if ( 'regular' === $variant ) {
				$available[] = 400;
			} elseif ( 1 === preg_match( '/^[1-9]00$/', $variant ) ) {
				$available[] = (int) $variant;
			}
		}
		$weights = array_values( array_intersect( array( 400, 600, 700 ), $available ) );
		return array() !== $weights ? $weights : array( $available[0] ?? 400 );
	}
}
