<?php
/**
 * Abstract provider base class.
 *
 * @package CampaignBridge\Providers
 */

namespace CampaignBridge\Providers;

use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Core\Http_Client_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract provider base class.
 *
 * Provides the shared identity (slug, label) and a default is_configured()
 * implementation for concrete providers. The verify_connection() method is
 * left abstract — each provider implements its own live check.
 */
abstract class Abstract_Provider implements Provider_Interface {

	/**
	 * Stable machine identifier.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Human-readable display name.
	 *
	 * @var string
	 */
	protected string $label;

	/**
	 * HTTP client for provider API calls.
	 *
	 * @var Http_Client_Interface
	 */
	protected Http_Client_Interface $http_client;

	/**
	 * Constructor.
	 *
	 * @param string                $slug        Stable machine identifier.
	 * @param string                $label       Human-readable display name.
	 * @param Http_Client_Interface $http_client HTTP client for API calls.
	 *
	 * @throws \InvalidArgumentException If the slug is not a valid identifier.
	 */
	public function __construct(
		string $slug,
		string $label,
		Http_Client_Interface $http_client = new Http_Client_Instance()
	) {
		if ( '' === $slug || 1 !== preg_match( '/^[a-z0-9_]+$/', $slug ) ) {
			throw new \InvalidArgumentException( 'Provider slug must be a non-empty lowercase identifier.' );
		}

		$this->slug        = $slug;
		$this->label       = $label;
		$this->http_client = $http_client;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Default is_configured(): checks for a non-empty api_key string.
	 *
	 * Concrete providers may override with stricter validation.
	 *
	 * @param array<string, mixed> $settings The provider settings.
	 */
	public function is_configured( array $settings ): bool {
		$api_key = $settings['api_key'] ?? null;
		return is_string( $api_key ) && '' !== $api_key;
	}
}
