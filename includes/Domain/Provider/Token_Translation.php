<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Outcome of translating canonical tokens for one provider.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translated content plus every reason it may not be sendable.
 *
 * Translation is fail-closed: when `is_complete()` is false the content
 * still contains canonical tokens the provider cannot substitute, so it must
 * not be handed off.
 */
final class Token_Translation {
	/**
	 * @param array<int, string> $unmapped    Canonical token IDs left untranslated.
	 * @param array<int, string> $parse_codes Parser error codes.
	 */
	public function __construct(
		private readonly string $content,
		private readonly array $unmapped,
		private readonly array $parse_codes
	) {}

	public function content(): string {
		return $this->content;
	}

	/** @return array<int, string> */
	public function unmapped(): array {
		return $this->unmapped;
	}

	/** @return array<int, string> */
	public function parse_errors(): array {
		return $this->parse_codes;
	}

	public function is_complete(): bool {
		return array() === $this->unmapped && array() === $this->parse_codes;
	}
}
