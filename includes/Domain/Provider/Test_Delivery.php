<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Bounded test-delivery request.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who receives one test of an existing remote draft, and in which format.
 *
 * Recipients are operational input for a single provider call. They are
 * never subscriber records and must not be persisted or logged; only their
 * count may be recorded.
 */
final class Test_Delivery {
	/** Most recipients one test request may name. */
	public const MAX_RECIPIENTS = 5;

	public const FORMAT_HTML = 'html';
	public const FORMAT_TEXT = 'text';

	/** RFC 5321 path limit. */
	private const MAX_ADDRESS_BYTES = 254;

	/** @param array<int, string> $recipients Normalized, unique addresses. */
	private function __construct(
		private readonly array $recipients,
		private readonly string $format
	) {}

	/**
	 * Validate and normalize a test request.
	 *
	 * @param array<mixed> $recipients Candidate addresses.
	 */
	public static function create( array $recipients, string $format ): self {
		if ( ! in_array( $format, array( self::FORMAT_HTML, self::FORMAT_TEXT ), true ) ) {
			throw new \InvalidArgumentException( 'Test format is invalid.' );
		}
		if ( ! array_is_list( $recipients ) || array() === $recipients || self::MAX_RECIPIENTS < count( $recipients ) ) {
			throw new \InvalidArgumentException( 'A test needs between 1 and ' . self::MAX_RECIPIENTS . ' recipients.' );
		}

		$normalized = array();
		foreach ( $recipients as $recipient ) {
			if ( ! is_string( $recipient ) ) {
				throw new \InvalidArgumentException( 'Test recipients must be email addresses.' );
			}
			$address = strtolower( trim( $recipient ) );
			if ( self::MAX_ADDRESS_BYTES < strlen( $address ) || false === filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
				throw new \InvalidArgumentException( 'Test recipients must be email addresses.' );
			}
			$normalized[ $address ] = $address;
		}

		return new self( array_values( $normalized ), $format );
	}

	/** @return array<int, string> */
	public function recipients(): array {
		return $this->recipients;
	}

	public function recipient_count(): int {
		return count( $this->recipients );
	}

	public function format(): string {
		return $this->format;
	}
}
