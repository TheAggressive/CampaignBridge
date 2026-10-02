<?php
/**
 * Mailchimp failure normalization.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps Mailchimp transport and HTTP failures to normalized provider errors.
 *
 * Shared by connection verification and discovery so every Mailchimp
 * operation reports the same categories, codes, and operator-safe messages.
 * Raw Mailchimp error bodies are never read into the result.
 */
final class Mailchimp_Errors {
	private const PROVIDER = 'mailchimp';

	/**
	 * Normalize a transport failure from the HTTP client.
	 *
	 * @param \WP_Error $error Transport error.
	 */
	public static function from_transport( \WP_Error $error ): Provider_Error {
		$code    = $error->get_error_code();
		$message = strtolower( $error->get_error_message() );
		$timeout = in_array( $code, array( 'connect_timeout', 'timeout' ), true )
			|| str_contains( $message, 'timed out' )
			|| str_contains( $message, 'timeout' );

		return self::for_category( $timeout ? Provider_Error_Category::TIMEOUT : Provider_Error_Category::NETWORK );
	}

	/**
	 * Normalize a non-success HTTP status.
	 *
	 * @param int $status HTTP status code.
	 */
	public static function from_status( int $status ): Provider_Error {
		return self::for_category( self::category_for_status( $status ) );
	}

	/** Normalize a success status whose body could not be understood. */
	public static function unexpected_response(): Provider_Error {
		return self::for_category( Provider_Error_Category::UNKNOWN );
	}

	/**
	 * Build the normalized error for one category.
	 *
	 * @param string $category Normalized category.
	 */
	public static function for_category( string $category ): Provider_Error {
		return Provider_Error::from_category( $category, self::code( $category ), self::message( $category ), self::PROVIDER );
	}

	/**
	 * Map an HTTP status code to a normalized category.
	 *
	 * @param int $status HTTP status code.
	 */
	public static function category_for_status( int $status ): string {
		return match ( true ) {
			400 === $status => Provider_Error_Category::VALIDATION,
			401 === $status => Provider_Error_Category::AUTHENTICATION,
			403 === $status => Provider_Error_Category::AUTHORIZATION,
			404 === $status => Provider_Error_Category::NOT_FOUND,
			409 === $status => Provider_Error_Category::CONFLICT,
			429 === $status => Provider_Error_Category::RATE_LIMITED,
			500 <= $status  => Provider_Error_Category::PROVIDER_ERROR,
			default         => Provider_Error_Category::UNKNOWN,
		};
	}

	/**
	 * Stable machine code for a category.
	 *
	 * @param string $category Normalized category.
	 */
	private static function code( string $category ): string {
		$map = array(
			Provider_Error_Category::VALIDATION     => 'mailchimp_invalid_credentials',
			Provider_Error_Category::AUTHENTICATION => 'mailchimp_authentication_failed',
			Provider_Error_Category::AUTHORIZATION  => 'mailchimp_authorization_failed',
			Provider_Error_Category::NOT_FOUND      => 'mailchimp_not_found',
			Provider_Error_Category::CONFLICT       => 'mailchimp_conflict',
			Provider_Error_Category::RATE_LIMITED   => 'mailchimp_rate_limited',
			Provider_Error_Category::TIMEOUT        => 'mailchimp_connection_timeout',
			Provider_Error_Category::NETWORK        => 'mailchimp_connection_unavailable',
			Provider_Error_Category::PROVIDER_ERROR => 'mailchimp_provider_error',
			Provider_Error_Category::UNKNOWN        => 'mailchimp_provider_error',
		);

		return $map[ $category ] ?? 'mailchimp_provider_error';
	}

	/**
	 * Operator-facing message for a category.
	 *
	 * @param string $category Normalized category.
	 */
	private static function message( string $category ): string {
		$map = array(
			Provider_Error_Category::VALIDATION     => __( 'The Mailchimp API key format is invalid.', 'campaignbridge' ),
			Provider_Error_Category::AUTHENTICATION => __( 'Mailchimp rejected the stored credentials.', 'campaignbridge' ),
			Provider_Error_Category::AUTHORIZATION  => __( 'The Mailchimp account is not authorized.', 'campaignbridge' ),
			Provider_Error_Category::NOT_FOUND      => __( 'The Mailchimp resource was not found.', 'campaignbridge' ),
			Provider_Error_Category::CONFLICT       => __( 'The Mailchimp request conflicted with existing data.', 'campaignbridge' ),
			Provider_Error_Category::RATE_LIMITED   => __( 'Mailchimp rate limit reached. Try again later.', 'campaignbridge' ),
			Provider_Error_Category::TIMEOUT        => __( 'Mailchimp request timed out.', 'campaignbridge' ),
			Provider_Error_Category::NETWORK        => __( 'Mailchimp could not be reached.', 'campaignbridge' ),
			Provider_Error_Category::PROVIDER_ERROR => __( 'Mailchimp service returned an error.', 'campaignbridge' ),
			Provider_Error_Category::UNKNOWN        => __( 'Mailchimp returned an unexpected response.', 'campaignbridge' ),
		);

		return $map[ $category ] ?? __( 'Mailchimp returned an unexpected response.', 'campaignbridge' );
	}
}
