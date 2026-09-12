<?php
/**
 * Normalized provider error.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One safe, structured provider error.
 *
 * Carries a normalized category, a stable machine code, and an operator-
 * safe message. Provider-specific payloads never leak into this object.
 */
final class Provider_Error {
	/**
	 * Create a provider error value.
	 *
	 * @param string $category    Normalized error category.
	 * @param string $code        Stable machine code.
	 * @param string $message     Safe operator message.
	 * @param string $provider    Provider slug.
	 * @param bool   $retryable   Whether the error is retryable.
	 */
	private function __construct(
		private readonly string $category,
		private readonly string $code,
		private readonly string $message,
		private readonly string $provider,
		private readonly bool $retryable
	) {}

	/**
	 * Create a provider error from a category.
	 *
	 * @param string $category Normalized error category.
	 * @param string $code     Stable machine code.
	 * @param string $message  Safe operator message.
	 * @param string $provider Provider slug.
	 */
	public static function from_category( string $category, string $code, string $message, string $provider ): self {
		if ( ! Provider_Error_Category::is_valid( $category ) ) {
			$category = Provider_Error_Category::UNKNOWN;
		}

		return new self(
			$category,
			$code,
			$message,
			$provider,
			Provider_Error_Category::is_retryable( $category )
		);
	}

	/**
	 * Create a network or timeout error.
	 *
	 * @param string $code     Stable machine code.
	 * @param string $message  Safe operator message.
	 * @param string $provider Provider slug.
	 */
	public static function timeout( string $code, string $message, string $provider ): self {
		return new self( Provider_Error_Category::TIMEOUT, $code, $message, $provider, true );
	}

	/**
	 * Create an authentication error.
	 *
	 * @param string $code     Stable machine code.
	 * @param string $message  Safe operator message.
	 * @param string $provider Provider slug.
	 */
	public static function authentication( string $code, string $message, string $provider ): self {
		return new self( Provider_Error_Category::AUTHENTICATION, $code, $message, $provider, false );
	}

	/** Determine whether the error is retryable. */
	public function is_retryable(): bool {
		return $this->retryable;
	}

	/** Get the normalized error category. */
	public function category(): string {
		return $this->category;
	}

	/** Get the stable machine-readable code. */
	public function code(): string {
		return $this->code;
	}

	/** Get the safe operator message. */
	public function message(): string {
		return $this->message;
	}

	/** Get the provider slug. */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * Serialize the error for APIs and logs.
	 *
	 * @return array{category: string, code: string, message: string, provider: string, retryable: bool}
	 */
	public function to_array(): array {
		return array(
			'category'  => $this->category,
			'code'      => $this->code,
			'message'   => $this->message,
			'provider'  => $this->provider,
			'retryable' => $this->retryable,
		);
	}
}
