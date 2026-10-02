<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Frozen campaign envelope.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Domain\Email\Token\Token_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The subject, preview text, and sender reviewed with a snapshot.
 *
 * Values are frozen exactly as authored so the envelope a provider receives
 * is the one that was reviewed. Capture never rejects an incomplete
 * envelope; `problems()` reports what a provider handoff would refuse, so
 * HTML-export-only review is unaffected.
 */
final class Campaign_Envelope {
	/** Maximum characters for a subject line or preview text. */
	public const MAX_SUBJECT_LENGTH = 150;

	/** Maximum characters for a sender name. */
	public const MAX_SENDER_NAME_LENGTH = 100;

	/** Storage bound for any one stored value, in bytes. */
	private const MAX_STORED_BYTES = 1000;

	public const PROBLEM_SUBJECT_MISSING     = 'subject_missing';
	public const PROBLEM_SUBJECT_TOO_LONG    = 'subject_too_long';
	public const PROBLEM_SUBJECT_TOKENS      = 'subject_tokens_invalid';
	public const PROBLEM_PREVIEW_TOO_LONG    = 'preview_text_too_long';
	public const PROBLEM_PREVIEW_TOKENS      = 'preview_text_tokens_invalid';
	public const PROBLEM_SENDER_NAME_MISSING = 'sender_name_missing';
	public const PROBLEM_SENDER_NAME_LONG    = 'sender_name_too_long';
	public const PROBLEM_SENDER_EMAIL        = 'sender_email_invalid';

	private function __construct(
		private readonly string $subject,
		private readonly string $preview_text,
		private readonly string $from_name,
		private readonly string $from_email
	) {}

	/** Capture authored values as single-line text without judging completeness. */
	public static function capture( mixed $subject, mixed $preview_text, mixed $from_name, mixed $from_email ): self {
		return new self(
			self::line( $subject, 'Subject' ),
			self::line( $preview_text, 'Preview text' ),
			self::line( $from_name, 'Sender name' ),
			strtolower( self::line( $from_email, 'Sender email' ) )
		);
	}

	/** @param array<string, mixed> $data Stored envelope. */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys( $data, array( 'subject', 'preview_text', 'from_name', 'from_email' ) );

		return self::capture( $data['subject'] ?? null, $data['preview_text'] ?? null, $data['from_name'] ?? null, $data['from_email'] ?? null );
	}

	public function subject(): string {
		return $this->subject;
	}

	public function preview_text(): string {
		return $this->preview_text;
	}

	public function from_name(): string {
		return $this->from_name;
	}

	public function from_email(): string {
		return $this->from_email;
	}

	/**
	 * Reasons a provider handoff must refuse this envelope.
	 *
	 * Subject and preview text may contain canonical tokens, which must parse
	 * against the registry. Sender fields are literal.
	 *
	 * @return array<int, string>
	 */
	public function problems( ?Token_Registry $registry = null, ?Token_Parser $parser = null ): array {
		$registry = $registry ?? Token_Registry::default();
		$parser   = $parser ?? new Token_Parser();
		$problems = array();

		if ( '' === $this->subject ) {
			$problems[] = self::PROBLEM_SUBJECT_MISSING;
		} elseif ( self::MAX_SUBJECT_LENGTH < mb_strlen( $this->subject ) ) {
			$problems[] = self::PROBLEM_SUBJECT_TOO_LONG;
		}
		if ( '' !== $this->subject && ! $parser->parse( $this->subject, $registry )->is_successful() ) {
			$problems[] = self::PROBLEM_SUBJECT_TOKENS;
		}
		if ( self::MAX_SUBJECT_LENGTH < mb_strlen( $this->preview_text ) ) {
			$problems[] = self::PROBLEM_PREVIEW_TOO_LONG;
		}
		if ( '' !== $this->preview_text && ! $parser->parse( $this->preview_text, $registry )->is_successful() ) {
			$problems[] = self::PROBLEM_PREVIEW_TOKENS;
		}
		if ( '' === $this->from_name ) {
			$problems[] = self::PROBLEM_SENDER_NAME_MISSING;
		} elseif ( self::MAX_SENDER_NAME_LENGTH < mb_strlen( $this->from_name ) ) {
			$problems[] = self::PROBLEM_SENDER_NAME_LONG;
		}
		if ( 254 < strlen( $this->from_email ) || false === filter_var( $this->from_email, FILTER_VALIDATE_EMAIL ) ) {
			$problems[] = self::PROBLEM_SENDER_EMAIL;
		}

		return $problems;
	}

	/** @return array{subject: string, preview_text: string, from_name: string, from_email: string} */
	public function to_array(): array {
		return array(
			'subject'      => $this->subject,
			'preview_text' => $this->preview_text,
			'from_name'    => $this->from_name,
			'from_email'   => $this->from_email,
		);
	}

	/** Normalize one authored value to bounded single-line text. */
	private static function line( mixed $value, string $label ): string {
		if ( null === $value ) {
			return '';
		}
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be a string.' );
		}
		$clean = preg_replace( '/[\p{Cc}\p{Cf}]+/u', ' ', $value );
		$clean = is_string( $clean ) ? trim( (string) preg_replace( '/\s+/u', ' ', $clean ) ) : '';
		if ( self::MAX_STORED_BYTES < strlen( $clean ) ) {
			throw new \InvalidArgumentException( sprintf( '%s exceeds %d bytes.', $label, self::MAX_STORED_BYTES ) );
		}

		return $clean;
	}
}
