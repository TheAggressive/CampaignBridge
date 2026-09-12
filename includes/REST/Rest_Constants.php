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
	public const MANAGE_CAPABILITY = 'campaignbridge_manage';

	/**
	 * Query defaults for posts endpoint.
	 */
	public const POSTS_PER_PAGE = 100;

	/**
	 * Default word cap for the excerpt preview.
	 *
	 * Keep in sync with the JS default in `src/blocks/shared/posts.ts`.
	 */
	public const DEFAULT_EXCERPT_MAX_WORDS = 50;

	/**
	 * Upper bound for the excerpt preview word cap.
	 *
	 * Prevents a client from requesting an unbounded excerpt for every
	 * post in a response, which would bloat the JSON payload without
	 * providing a usable editor preview.
	 */
	public const EXCERPT_PREVIEW_MAX_WORDS = 500;
}
