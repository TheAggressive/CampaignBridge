<?php
/**
 * Abstract Base Provider Class for CampaignBridge.
 *
 * Provides common functionality and default implementations for email service
 * providers. This class reduces boilerplate code and ensures consistency
 * across all provider implementations.
 *
 * Extend this class when creating new providers to inherit common functionality
 * and focus on provider-specific logic.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for provider implementations.
 *
 * Provides common functionality like basic validation, logging, and
 * default implementations that can be overridden as needed.
 */
abstract class Abstract_Provider implements Provider_Interface {
	/**
	 * Provider slug identifier.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Provider display name.
	 *
	 * @var string
	 */
	protected string $label;

	/**
	 * Required capability for this provider.
	 *
	 * @var string
	 */
	protected string $required_capability;

	/**
	 * Default rate limiting policy.
	 *
	 * @var array
	 */
	protected array $rate_limit_policy;

	/**
	 * Provider capabilities.
	 *
	 * @var array
	 */
	protected array $capabilities;

	/**
	 * API key validation pattern.
	 *
	 * @var string
	 */
	protected string $api_key_pattern;

	/**
	 * Constructor.
	 *
	 * @param string $slug Provider slug identifier.
	 * @param string $label Provider display name.
	 */
	public function __construct( string $slug, string $label ) {
		$this->slug                = $slug;
		$this->label               = $label;
		$this->required_capability = 'campaignbridge_manage';
		$this->rate_limit_policy   = array(
			'bucket'         => $slug,
			'max_per_minute' => 60,
		);
		$this->capabilities        = array(
			'audiences'  => true,
			'templates'  => true,
			'scheduling' => false,
			'automation' => false,
			'analytics'  => false,
		);
		$this->api_key_pattern     = '/^[a-zA-Z0-9_-]{20,}$/'; // Generic pattern by default
	}

	/**
	 * Get unique slug for the provider.
	 *
	 * @return string Provider slug identifier.
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * Get human-readable label for the provider.
	 *
	 * @return string Provider display name.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Get the required capability for this provider.
	 *
	 * @return string Capability slug.
	 */
	public function required_capability(): string {
		return $this->required_capability;
	}

	/**
	 * Get rate limiting policy for this provider.
	 *
	 * @return array Array with 'bucket' and 'max_per_minute' keys.
	 */
	public function rate_limit_policy(): array {
		return $this->rate_limit_policy;
	}

	/**
	 * Get provider capabilities and supported features.
	 *
	 * @return array Array of supported features.
	 */
	public function get_capabilities(): array {
		return $this->capabilities;
	}

	/**
	 * Get API key validation pattern for this provider.
	 *
	 * @return string Regex pattern for API key validation.
	 */
	public function get_api_key_pattern(): string {
		return $this->api_key_pattern;
	}

	/**
	 * Default implementation of settings schema.
	 *
	 * Override in subclasses to provide provider-specific validation.
	 *
	 * @return array Schema array with field definitions.
	 */
	public function settings_schema(): array {
		return array(
			'api_key' => array(
				'sensitive'  => true,
				'required'   => true,
				'pattern'    => '/^.+$/', // Any non-empty string.
				'min_length' => 1,
				'max_length' => 1000,
			),
		);
	}

	/**
	 * Default implementation of settings redaction.
	 *
	 * Override in subclasses for provider-specific redaction logic.
	 *
	 * @param array $settings Raw settings array.
	 * @return array Redacted settings array.
	 */
	public function redact_settings( array $settings ): array {
		$redacted = $settings;

		// Redact sensitive fields by default.
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $redacted[ $field ] ) && ! empty( $redacted[ $field ] ) ) {
				$value = (string) $redacted[ $field ];
				if ( strlen( $value ) > 8 ) {
					$redacted[ $field ] = str_repeat( '•', strlen( $value ) - 4 ) . substr( $value, -4 );
				} else {
					$redacted[ $field ] = str_repeat( '•', strlen( $value ) );
				}
			}
		}

		return $redacted;
	}

	/**
	 * Log provider activity for debugging.
	 *
	 * Only logs when WP_DEBUG is enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function log( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf( '[%s] %s', $this->slug, $message );
			if ( ! empty( $context ) ) {
				$log_message .= ' Context: ' . wp_json_encode( $context );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
			error_log( $log_message );
		}
	}

	/**
	 * Create a standardized error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code (default 400).
	 * @return \WP_Error Error object.
	 */
	protected function create_error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Validate required settings fields.
	 *
	 * @param array $settings Plugin settings array.
	 * @param array $required Array of required field names.
	 * @return bool True if all required fields are present and non-empty.
	 */
	protected function validate_required_settings( array $settings, array $required ): bool {
		foreach ( $required as $field ) {
			if ( empty( $settings[ $field ] ) ) {
				return false;
			}
		}
		return true;
	}
}
