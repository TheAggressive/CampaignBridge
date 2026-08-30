<?php
/**
 * Structured compiler diagnostic.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** One safe, structured compiler error or warning. */
final class Compile_Diagnostic {
	public const ERROR   = 'error';
	public const WARNING = 'warning';

	/**
	 * Create a diagnostic value.
	 *
	 * @param string $severity Error or warning.
	 * @param string $code     Stable machine code.
	 * @param string $path     Block or document path.
	 * @param string $message  Safe operator message.
	 */
	private function __construct(
		private readonly string $severity,
		private readonly string $code,
		private readonly string $path,
		private readonly string $message
	) {}

	/**
	 * Create an error diagnostic.
	 *
	 * @param string $code    Stable machine code.
	 * @param string $path    Block or document path.
	 * @param string $message Safe operator message.
	 */
	public static function error( string $code, string $path, string $message ): self {
		return new self( self::ERROR, $code, $path, $message );
	}

	/**
	 * Create a warning diagnostic.
	 *
	 * @param string $code    Stable machine code.
	 * @param string $path    Block or document path.
	 * @param string $message Safe operator message.
	 */
	public static function warning( string $code, string $path, string $message ): self {
		return new self( self::WARNING, $code, $path, $message );
	}

	/** Determine whether compilation must fail. */
	public function is_error(): bool {
		return self::ERROR === $this->severity;
	}

	/** Get the stable machine-readable code. */
	public function code(): string {
		return $this->code;
	}

	/** Get the block or document path. */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Serialize the diagnostic for APIs and logs.
	 *
	 * @return array{severity: string, code: string, path: string, message: string}
	 */
	public function to_array(): array {
		return array(
			'severity' => $this->severity,
			'code'     => $this->code,
			'path'     => $this->path,
			'message'  => $this->message,
		);
	}
}
