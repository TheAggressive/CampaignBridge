<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Live template data required to capture a campaign review input.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

use CampaignBridge\Domain\Email\Design_Font_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries template data from persistence to the capture workflow. */
final class Campaign_Template_Input {
	/**
	 * Creates a typed live template input.
	 *
	 * @param array<string, mixed> $metadata Canonical document metadata.
	 */
	public function __construct(
		private readonly string $content,
		private readonly array $metadata,
		private readonly Design_Font_Registry $design_fonts
	) {}

	public function content(): string {
		return $this->content;
	}

	/**
	 * Returns canonical document metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function metadata(): array {
		return $this->metadata;
	}

	public function design_fonts(): Design_Font_Registry {
		return $this->design_fonts;
	}
}
