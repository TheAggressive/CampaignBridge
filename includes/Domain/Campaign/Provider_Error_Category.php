<?php
/**
 * Provider error categories.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized provider error categories.
 *
 * Every provider failure is mapped to one of these stable categories so
 * workflow and UI can react deterministically without parsing provider-
 * specific messages.
 */
final class Provider_Error_Category {
	public const AUTHENTICATION = 'authentication';
	public const AUTHORIZATION  = 'authorization';
	public const RATE_LIMITED   = 'rate_limited';
	public const VALIDATION     = 'validation';
	public const NOT_FOUND      = 'not_found';
	public const CONFLICT       = 'conflict';
	public const TIMEOUT        = 'timeout';
	public const NETWORK        = 'network';
	public const PROVIDER_ERROR = 'provider_error';
	public const UNKNOWN        = 'unknown';

	/**
	 * All valid error categories.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::AUTHENTICATION,
			self::AUTHORIZATION,
			self::RATE_LIMITED,
			self::VALIDATION,
			self::NOT_FOUND,
			self::CONFLICT,
			self::TIMEOUT,
			self::NETWORK,
			self::PROVIDER_ERROR,
			self::UNKNOWN,
		);
	}

	/**
	 * Determine whether a string is a valid error category.
	 *
	 * @param string $category Category to check.
	 */
	public static function is_valid( string $category ): bool {
		return in_array( $category, self::all(), true );
	}

	/**
	 * Determine whether the error is retryable.
	 *
	 * Retryable errors indicate a transient condition where a subsequent
	 * attempt may succeed. Non-retryable errors require operator intervention
	 * or a different request.
	 *
	 * @param string $category Category to check.
	 */
	public static function is_retryable( string $category ): bool {
		return in_array(
			$category,
			array( self::RATE_LIMITED, self::TIMEOUT, self::NETWORK, self::UNKNOWN ),
			true
		);
	}
}
