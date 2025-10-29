<?php
/**
 * Shared Constants for CampaignBridge REST API.
 *
 * Centralized constants used across REST API endpoints for consistency
 * and maintainability.
 *
 * @package CampaignBridge\REST
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Constants class.
 *
 * Centralizes constants used across REST API classes to avoid duplication
 * and ensure consistency.
 */
class Rest_Constants {
	/**
	 * API namespace for all endpoints.
	 */
	public const API_NAMESPACE = 'campaignbridge/v1';

	/**
	 * Default post type for endpoints.
	 */
	public const DEFAULT_POST_TYPE = 'post';

	/**
	 * Rate limiting defaults.
	 */
	public const RATE_LIMIT_REQUESTS = 30;
	public const RATE_LIMIT_WINDOW   = 60;

	/**
	 * Cache key prefixes for rate limiting.
	 */
	public const CACHE_KEY_PREFIX_GENERAL = 'campaignbridge_rate_limit_';
	public const CACHE_KEY_PREFIX_EDITOR  = 'campaignbridge_rate_limit_editor_settings_';

	/**
	 * HTTP status codes.
	 */
	public const HTTP_UNAUTHORIZED          = 401;
	public const HTTP_BAD_REQUEST           = 400;
	public const HTTP_TOO_MANY_REQUESTS     = 429;
	public const HTTP_INTERNAL_SERVER_ERROR = 500;
	public const HTTP_NOT_FOUND             = 404;
	public const HTTP_FORBIDDEN             = 403;

	/**
	 * Required capability for managing plugin settings.
	 */
	public const MANAGE_CAPABILITY = 'manage_options';

	/**
	 * Query defaults for posts endpoint.
	 */
	public const POSTS_PER_PAGE = 100;
}
