<?php
/**
 * Diagnostic describing a token parse error or warning.
 *
 * @package CampaignBridge
 * @since   1.0.0
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email\Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a single token parse diagnostic.
 *
 * Aligned with Compile_Diagnostic patterns: code, message, position, severity.
 * Diagnostics never contain PII, credentials, or raw provider payloads.
 */
final class Token_Diagnostic {

	const SEVERITY_ERROR   = 'error';
	const SEVERITY_WARNING = 'warning';

	const CODE_UNKNOWN_TOKEN   = 'cb_token_unknown';
	const CODE_MALFORMED_TOKEN = 'cb_token_malformed';
	const CODE_NESTED_TOKEN    = 'cb_token_nested';
	const CODE_TOKEN_LIMIT     = 'cb_token_limit_exceeded';

	const CODE_UNRESOLVED_TOKEN      = 'cb_token_unresolved';
	const CODE_URL_CONTEXT           = 'cb_token_url_context';
	const CODE_URL_VALUE_TYPE        = 'cb_token_url_value_type';
	const CODE_UNSUPPORTED_CONTEXT   = 'cb_token_context_unsupported';
	const CODE_INVALID_CONTEXT_VALUE = 'cb_token_values_invalid';

	/**
	 * Diagnostic code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Human-readable message (no PII).
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Zero-based character offset where the issue was detected.
	 *
	 * @var int
	 */
	private int $position;

	/**
	 * Severity level.
	 *
	 * @var string
	 */
	private string $severity;

	/**
	 * Construct a diagnostic.
	 *
	 * @param string $code     Diagnostic code.
	 * @param string $message  Human-readable message.
	 * @param int    $position Zero-based offset.
	 * @param string $severity Severity level.
	 */
	private function __construct(
		string $code,
		string $message,
		int $position,
		string $severity
	) {
		$this->code     = $code;
		$this->message  = $message;
		$this->position = $position;
		$this->severity = $severity;
	}

	/**
	 * Create a new diagnostic.
	 *
	 * @param string $code     Diagnostic code.
	 * @param string $message  Human-readable message (no PII).
	 * @param int    $position Zero-based character offset.
	 * @param string $severity One of SEVERITY_* constants.
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException When severity is invalid.
	 */
	public static function create(
		string $code,
		string $message,
		int $position,
		string $severity = self::SEVERITY_ERROR
	): self {
		if ( ! in_array( $severity, array( self::SEVERITY_ERROR, self::SEVERITY_WARNING ), true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid diagnostic severity: %s', $severity ) );
		}

		if ( $position < 0 ) {
			throw new \InvalidArgumentException( 'Diagnostic position must be non-negative.' );
		}

		return new self( $code, $message, $position, $severity );
	}

	/**
	 * Create a diagnostic for an unknown token.
	 *
	 * The ID is author-controlled input, so it is never echoed; the position
	 * identifies the token.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function unknown_token( int $position ): self {
		return new self(
			self::CODE_UNKNOWN_TOKEN,
			'Unknown token',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for content with too many token expressions.
	 *
	 * @param int $position Zero-based offset of the first expression over the limit.
	 *
	 * @return static
	 */
	public static function token_limit_exceeded( int $position ): self {
		return new self(
			self::CODE_TOKEN_LIMIT,
			'Too many token expressions',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for a malformed token expression.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function malformed_token( int $position ): self {
		return new self(
			self::CODE_MALFORMED_TOKEN,
			'Malformed token expression',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for a nested token.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function nested_token( int $position ): self {
		return new self(
			self::CODE_NESTED_TOKEN,
			'Nested tokens are not supported',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for a CampaignBridge-owned token without a value.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function unresolved_token( int $position ): self {
		return new self(
			self::CODE_UNRESOLVED_TOKEN,
			'A CampaignBridge token has no value in the compile context',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for token syntax embedded in a URL.
	 *
	 * A URL field holds either a literal HTTP(S) URL or exactly one URL token.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function url_context( int $position ): self {
		return new self(
			self::CODE_URL_CONTEXT,
			'A URL must be a literal HTTP or HTTPS URL or exactly one URL token',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for a non-URL token used as a URL.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function url_value_type( int $position ): self {
		return new self(
			self::CODE_URL_VALUE_TYPE,
			'Only URL tokens can be used as a link destination',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for token syntax in a location that does not accept tokens.
	 *
	 * @param int $position Zero-based offset.
	 *
	 * @return static
	 */
	public static function unsupported_context( int $position ): self {
		return new self(
			self::CODE_UNSUPPORTED_CONTEXT,
			'Tokens are not supported in this field',
			$position,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Create a diagnostic for an invalid compile-context token value map.
	 *
	 * The offending key or value is never echoed.
	 *
	 * @return static
	 */
	public static function invalid_context_value(): self {
		return new self(
			self::CODE_INVALID_CONTEXT_VALUE,
			'Token values must map CampaignBridge-owned token IDs to bounded plain-text values',
			0,
			self::SEVERITY_ERROR
		);
	}

	/**
	 * Get the diagnostic code.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Get the human-readable message.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get the zero-based character position.
	 *
	 * @return int
	 */
	public function get_position(): int {
		return $this->position;
	}

	/**
	 * Get the severity level.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return $this->severity;
	}

	/**
	 * Check whether this is an error-level diagnostic.
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return self::SEVERITY_ERROR === $this->severity;
	}
}
