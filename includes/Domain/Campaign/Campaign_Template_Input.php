<?php
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
	 * @param string               $content      The draft content.
	 * @param array<string, mixed> $metadata     Canonical document metadata.
	 * @param Design_Font_Registry $design_fonts Unsaved design fonts.
	 * @param Campaign_Envelope    $envelope     Subject, preview text, and sender.
	 */
	public function __construct(
		private readonly string $content,
		private readonly array $metadata,
		private readonly Design_Font_Registry $design_fonts,
		private readonly Campaign_Envelope $envelope
	) {}

	/**
	 * The input's content.
	 */
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

	/**
	 * The input's design fonts.
	 */
	public function design_fonts(): Design_Font_Registry {
		return $this->design_fonts;
	}

	/** The authored subject, preview text, and sender, as captured. */
	public function envelope(): Campaign_Envelope {
		return $this->envelope;
	}
}
