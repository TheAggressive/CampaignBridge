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
	private const CSS_URL          = 'https://fonts.googleapis.com/css2';
	private const CATALOG_FILE     = __DIR__ . '/google-fonts-catalog.json';
	private const PROVENANCE_FILE  = __DIR__ . '/google-fonts-catalog.source.json';
	private const MAX_CATALOG_SIZE = 1048576;
	private const MAX_RESULTS      = 20;
	private const MAX_CSS_SIZE     = 65536;
	private const VALIDATION_TTL   = 30 * DAY_IN_SECONDS;
	private const NEGATIVE_TTL     = DAY_IN_SECONDS;

	/** Whether site policy permits requests to Google font hosts. */
	public static function external_enabled(): bool {
		return (bool) apply_filters( 'campaignbridge_external_google_fonts_enabled', true );
	}

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
		if ( array() === $matches ) {
			$remote = $this->validate_exact_family( $query );
			if ( ! is_wp_error( $remote ) && null !== $remote ) {
				$matches[] = $remote;
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
		if ( ! self::external_enabled() ) {
			return new WP_Error( 'external_google_fonts_disabled', __( 'External Google Fonts are disabled by site policy.', 'campaignbridge' ) );
		}
		$catalogue = $this->catalogue();
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
			$match = $this->validate_exact_family( $requested_family );
			if ( is_wp_error( $match ) ) {
				return $match;
			}
			if ( null === $match ) {
				return new WP_Error( 'google_font_not_found', __( 'That family is not available from Google Fonts.', 'campaignbridge' ) );
			}
		}

		$family         = (string) $match['family'];
		$weights        = $this->email_weights( $match['variants'] ?? array() );
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
	 * Read the versioned catalogue shipped with the plugin.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function catalogue(): array|WP_Error {
		$size = is_file( self::CATALOG_FILE ) ? filesize( self::CATALOG_FILE ) : false;
		if ( false === $size || $size < 2 || $size > self::MAX_CATALOG_SIZE ) {
			return new WP_Error( 'invalid_google_fonts_catalogue', __( 'The bundled Google Fonts catalogue is unavailable.', 'campaignbridge' ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown,CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Reading a size-checked, fixed packaged asset, never a URL.
		$contents   = file_get_contents( self::CATALOG_FILE );
		$provenance = is_file( self::PROVENANCE_FILE ) ? file_get_contents( self::PROVENANCE_FILE ) : false; // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown,CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Reading a fixed packaged asset, never a URL.
		$metadata   = is_string( $provenance ) ? json_decode( $provenance, true ) : null;
		if ( ! is_string( $contents ) || ! is_array( $metadata ) || ! hash_equals( (string) ( $metadata['sha256'] ?? '' ), hash( 'sha256', $contents ) ) ) {
			return new WP_Error( 'invalid_google_fonts_catalogue', __( 'The bundled Google Fonts catalogue failed its integrity check.', 'campaignbridge' ) );
		}
		$decoded = json_decode( $contents, true );
		return is_array( $decoded ) ? $decoded : new WP_Error( 'invalid_google_fonts_catalogue', __( 'The bundled Google Fonts catalogue is invalid.', 'campaignbridge' ) );
	}

	/**
	 * Validate an exact, newly released family against Google's keyless CSS API.
	 *
	 * @param string $family Exact family name.
	 * @return array{family: string, category: string, variants: array<int, string>}|null|WP_Error
	 */
	private function validate_exact_family( string $family ): array|null|WP_Error {
		if ( ! self::external_enabled() ) {
			return new WP_Error( 'external_google_fonts_disabled', __( 'External Google Fonts are disabled by site policy.', 'campaignbridge' ) );
		}
		$family = trim( $family );
		if ( strlen( $family ) < 2 || strlen( $family ) > 80 || 1 === preg_match( '/[\x00-\x1F\x7F]/', $family ) ) {
			return null;
		}

		$cache_key = 'google_font_valid_' . hash( 'sha256', strtolower( $family ) );
		$cached    = Storage::get_transient( $cache_key );
		if ( 'valid' === $cached ) {
			return array(
				'family'   => $family,
				'category' => 'sans-serif',
				'variants' => array( 'regular' ),
			);
		}
		if ( 'invalid' === $cached ) {
			return null;
		}

		$response = Http_Client::get(
			self::CSS_URL . '?family=' . str_replace( '%20', '+', rawurlencode( $family ) ) . '&display=swap',
			array(
				'campaignbridge_retry' => false,
				'timeout'              => 3,
				'limit_response_size'  => self::MAX_CSS_SIZE,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'google_fonts_unavailable', __( 'Google Fonts validation is temporarily unavailable.', 'campaignbridge' ) );
		}

		$body = is_string( $response['body'] ?? null ) ? $response['body'] : '';
		if ( 200 !== ( $response['status_code'] ?? 0 ) || ! str_contains( $body, '@font-face' ) ) {
			Storage::set_transient( $cache_key, 'invalid', self::NEGATIVE_TTL );
			return null;
		}

		Storage::set_transient( $cache_key, 'valid', self::VALIDATION_TTL );
		return array(
			'family'   => $family,
			'category' => 'sans-serif',
			'variants' => array( 'regular' ),
		);
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
	 * Request only the regular and common semibold/bold email weights that exist.
	 *
	 * @param mixed $raw Catalogue variants.
	 * @return array<int, int>
	 */
	private function email_weights( mixed $raw ): array {
		$variants  = $this->variants( $raw );
		$available = array_map(
			static fn ( string $variant ): int => 'regular' === $variant ? 400 : (int) $variant,
			array_filter( $variants, static fn ( string $variant ): bool => 'regular' === $variant || 1 === preg_match( '/^[1-9]00$/', $variant ) )
		);
		$weights   = array_values( array_intersect( array( 400, 600, 700 ), $available ) );
		$numeric   = array_values( array_filter( $available, static fn ( int $weight ): bool => $weight >= 100 && $weight <= 900 ) );
		if ( count( $numeric ) >= 2 ) {
			$minimum = min( $numeric );
			$maximum = max( $numeric );
			$weights = array_values( array_filter( array( 400, 600, 700 ), static fn ( int $weight ): bool => $weight >= $minimum && $weight <= $maximum ) );
		}

		return array() === $weights ? array( 400 ) : $weights;
	}
}
