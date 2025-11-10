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
	 * @var array<string, mixed>
	 */
	protected array $rate_limit_policy;

	/**
	 * Provider capabilities.
	 *
	 * @var array<string, mixed>
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
		$this->api_key_pattern     = '/^[a-zA-Z0-9_-]{20,}$/'; // Generic pattern by default.
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
	 * Get unique slug for the provider (alias for slug()).
	 *
	 * @return string Provider slug identifier.
	 */
	public function get_slug(): string {
		return $this->slug();
	}

	/**
	 * Get human-readable label for the provider (alias for label()).
	 *
	 * @return string Provider display name.
	 */
	public function get_name(): string {
		return $this->label();
	}

	/**
	 * Get description for the provider.
	 *
	 * @return string Provider description.
	 */
	public function get_description(): string {
		return sprintf(
			/* translators: %s: provider name */
			__( '%s email service provider integration.', 'campaignbridge' ),
			$this->label()
		);
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
	 * @return array<string, mixed> Array with 'bucket' and 'max_per_minute' keys.
	 */
	public function rate_limit_policy(): array {
		return $this->rate_limit_policy;
	}

	/**
	 * Get provider capabilities and supported features.
	 *
	 * @return array<string, mixed> Array of supported features.
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
	 * @return array<string, array<string, mixed>> Schema array with field definitions.
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
	 * @param array<string, mixed> $settings Raw settings array.
	 * @return array<string, mixed> Redacted settings array.
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
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	protected function log( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf( '[%s] %s', $this->slug, $message );
			if ( ! empty( $context ) ) {
				$log_message .= ' Context: ' . wp_json_encode( $context );
			}
			\CampaignBridge\Core\Error_Handler::info( $log_message );
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
	 * @param array<string, mixed> $settings Plugin settings array.
	 * @param array<string>        $required Array of required field names.
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

	/**
	 * Sanitize provider-specific settings based on schema.
	 *
	 * Validates and sanitizes settings according to the provider's schema definition.
	 * This ensures that only valid, properly formatted settings are stored and used.
	 *
	 * @param array<string, mixed> $settings Raw settings array to sanitize.
	 * @return array<string, mixed> Sanitized settings array.
	 */
	public function sanitize_settings( array $settings ): array {
		$schema    = $this->settings_schema();
		$sanitized = array();

		foreach ( $schema as $field_name => $field_schema ) {
			$value = $settings[ $field_name ] ?? null;

			// Skip fields that aren't in the input.
			if ( ! array_key_exists( $field_name, $settings ) ) {
				continue;
			}

			// Apply sanitization based on field type.
			$sanitized_value = $this->sanitize_field_value( $value, $field_schema );

			// Only include non-null values or use default if specified.
			if ( null !== $sanitized_value || isset( $field_schema['default'] ) ) {
				$sanitized[ $field_name ] = $sanitized_value ?? $field_schema['default'];
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single field value based on its schema.
	 *
	 * @param mixed                $value        Raw field value.
	 * @param array<string, mixed> $field_schema Field schema definition.
	 * @return mixed Sanitized field value.
	 */
	private function sanitize_field_value( $value, array $field_schema ) {
		$type = $field_schema['type'] ?? 'string';

		switch ( $type ) {
			case 'string':
				return $this->sanitize_string_field( $value, $field_schema );

			case 'boolean':
				return $this->sanitize_boolean_field( $value );

			case 'integer':
				return $this->sanitize_integer_field( $value, $field_schema );

			case 'email':
				return $this->sanitize_email_field( $value );

			case 'url':
				return $this->sanitize_url_field( $value );

			default:
				// For unknown types, return as-is but log a warning.
				$this->log( "Unknown field type '{$type}' for field", array( 'field' => array_keys( $field_schema ) ) );
				return $value;
		}
	}

	/**
	 * Sanitize string field value.
	 *
	 * @param mixed                $value        Raw field value.
	 * @param array<string, mixed> $field_schema Field schema definition.
	 * @return string|null Sanitized string value.
	 */
	private function sanitize_string_field( $value, array $field_schema ): ?string {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$value = (string) $value;

		// Check length constraints.
		$min_length = $field_schema['min_length'] ?? 0;
		$max_length = $field_schema['max_length'] ?? 1000;

		if ( strlen( $value ) < $min_length || strlen( $value ) > $max_length ) {
			return null;
		}

		// Apply pattern validation if specified.
		if ( isset( $field_schema['pattern'] ) ) {
			if ( ! preg_match( $field_schema['pattern'], $value ) ) {
				return null;
			}
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize boolean field value.
	 *
	 * @param mixed $value Raw field value.
	 * @return bool|null Sanitized boolean value.
	 */
	private function sanitize_boolean_field( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower_value = strtolower( $value );
			if ( in_array( $lower_value, array( 'true', '1', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $lower_value, array( 'false', '0', 'no', 'off' ), true ) ) {
				return false;
			}
		}

		if ( is_numeric( $value ) ) {
			return (bool) $value;
		}

		return null;
	}

	/**
	 * Sanitize integer field value.
	 *
	 * @param mixed                $value        Raw field value.
	 * @param array<string, mixed> $field_schema Field schema definition.
	 * @return int|null Sanitized integer value.
	 */
	private function sanitize_integer_field( $value, array $field_schema ): ?int {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$value = (int) $value;

		// Check range constraints.
		$min = $field_schema['min'] ?? PHP_INT_MIN;
		$max = $field_schema['max'] ?? PHP_INT_MAX;

		if ( $value < $min || $value > $max ) {
			return null;
		}

		return $value;
	}

	/**
	 * Sanitize email field value.
	 *
	 * @param mixed $value Raw field value.
	 * @return string|null Sanitized email value.
	 */
	private function sanitize_email_field( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = sanitize_email( $value );

		if ( ! is_email( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Sanitize URL field value.
	 *
	 * @param mixed $value Raw field value.
	 * @return string|null Sanitized URL value.
	 */
	private function sanitize_url_field( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = esc_url_raw( $value );

		if ( empty( $value ) ) {
			return null;
		}

		return $value;
	}
}
