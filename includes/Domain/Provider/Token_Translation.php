<?php
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
	 * Build the token translation.
	 *
	 * @param string             $content          The draft content.
	 * @param array<int, string> $unmapped         Canonical token IDs left untranslated.
	 * @param array<int, string> $parse_codes      Parser error codes.
	 * @param bool               $literal_conflict Whether literal provider syntax remains.
	 */
	public function __construct(
		private readonly string $content,
		private readonly array $unmapped,
		private readonly array $parse_codes,
		private readonly bool $literal_conflict = false
	) {}

	/**
	 * The translation's content.
	 */
	public function content(): string {
		return $this->content;
	}

	/**
	 * The translation's unmapped.
	 *
	 * @return array<int, string>
	 */
	public function unmapped(): array {
		return $this->unmapped;
	}

	/**
	 * The translation's parse codes.
	 *
	 * @return array<int, string>
	 */
	public function parse_errors(): array {
		return $this->parse_codes;
	}

	/**
	 * Whether the canonical content already contained the provider's own
	 * token syntax, which the provider would evaluate after handoff.
	 */
	public function has_literal_conflict(): bool {
		return $this->literal_conflict;
	}

	/**
	 * Whether every token translated and no literal provider syntax remains.
	 */
	public function is_complete(): bool {
		return array() === $this->unmapped && array() === $this->parse_codes && ! $this->literal_conflict;
	}
}
