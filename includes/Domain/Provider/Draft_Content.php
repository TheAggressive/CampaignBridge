<?php
/**
 * Provider-neutral remote draft request.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a provider needs to create one draft from an approved artifact.
 *
 * Content is already translated into the provider's token syntax and comes
 * only from the approved snapshot. `correlation` is a bounded label the
 * adapter attaches to the remote draft so reconciliation can find it.
 */
final class Draft_Content {
	/** Upper bound on uploaded HTML, in bytes. */
	public const MAX_HTML_BYTES = 2097152;

	/**
	 * Build the draft content.
	 *
	 * @param string $audience_id  The provider's audience ID.
	 * @param string $subject      Subject line.
	 * @param string $preview_text Preview text.
	 * @param string $from_name    Sender name.
	 * @param string $reply_to     Reply-to address.
	 * @param string $html         HTML content.
	 * @param string $text         Plain-text content.
	 * @param string $fingerprint  Artifact fingerprint.
	 * @param string $correlation  The provider's correlation ID, when known.
	 */
	private function __construct(
		private readonly string $audience_id,
		private readonly string $subject,
		private readonly string $preview_text,
		private readonly string $from_name,
		private readonly string $reply_to,
		private readonly string $html,
		private readonly string $text,
		private readonly string $fingerprint,
		private readonly string $correlation
	) {}

	/**
	 * Everything the provider needs to create or update a draft.
	 *
	 * @param string $audience_id  The provider's audience ID.
	 * @param string $subject      Subject line.
	 * @param string $preview_text Preview text.
	 * @param string $from_name    Sender name.
	 * @param string $reply_to     Reply-to address.
	 * @param string $html         HTML content.
	 * @param string $text         Plain-text content.
	 * @param string $fingerprint  Artifact fingerprint.
	 * @param string $correlation  The provider's correlation ID, when known.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function create(
		string $audience_id,
		string $subject,
		string $preview_text,
		string $from_name,
		string $reply_to,
		string $html,
		string $text,
		string $fingerprint,
		string $correlation
	): self {
		if ( '' === $subject || '' === $from_name || '' === $html ) {
			throw new \InvalidArgumentException( 'A draft requires a subject, sender name, and HTML.' );
		}
		if ( false === filter_var( $reply_to, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( 'Draft reply-to must be a valid email address.' );
		}
		if ( self::MAX_HTML_BYTES < strlen( $html ) || self::MAX_HTML_BYTES < strlen( $text ) ) {
			throw new \InvalidArgumentException( 'Draft content exceeds the upload bound.' );
		}
		if ( 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $fingerprint ) ) {
			throw new \InvalidArgumentException( 'Draft fingerprint is invalid.' );
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9 _:-]{0,99}$/', $correlation ) ) {
			throw new \InvalidArgumentException( 'Draft correlation label is invalid.' );
		}

		return new self( Discovery_Values::remote_id( $audience_id, 'Audience ID' ), $subject, $preview_text, $from_name, strtolower( $reply_to ), $html, $text, $fingerprint, $correlation );
	}

	/**
	 * The content's audience ID.
	 */
	public function audience_id(): string {
		return $this->audience_id;
	}

	/**
	 * The content's subject.
	 */
	public function subject(): string {
		return $this->subject;
	}

	/**
	 * The content's preview text.
	 */
	public function preview_text(): string {
		return $this->preview_text;
	}

	/**
	 * The content's from name.
	 */
	public function from_name(): string {
		return $this->from_name;
	}

	/**
	 * The content's reply to.
	 */
	public function reply_to(): string {
		return $this->reply_to;
	}

	/**
	 * The content's HTML.
	 */
	public function html(): string {
		return $this->html;
	}

	/**
	 * The content's text.
	 */
	public function text(): string {
		return $this->text;
	}

	/** Fingerprint of the approved artifact this content was translated from. */
	public function fingerprint(): string {
		return $this->fingerprint;
	}

	/**
	 * The content's correlation.
	 */
	public function correlation(): string {
		return $this->correlation;
	}
}
