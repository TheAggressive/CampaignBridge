<?php
/**
 * Frozen input for reproducible email review.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Groups existing canonical objects without resolving or copying post values. */
final class Review_Input {
	/**
	 * Capture all inputs needed to repeat a review compilation.
	 *
	 * @param string                           $content          Serialized template.
	 * @param array<int, array<string, mixed>> $blocks           Parsed template.
	 * @param Render_Context                   $context          Frozen content and metadata.
	 * @param Resolved_Email_Design            $design           Frozen resolved design.
	 * @param int                              $revision         Explicit capture revision.
	 * @param string                           $compiler_version Compiler that captured this input.
	 */
	public function __construct(
		private readonly string $content,
		private readonly array $blocks,
		private readonly Render_Context $context,
		private readonly Resolved_Email_Design $design,
		private readonly int $revision,
		private readonly string $compiler_version
	) {}

	/** Original serialized template. */
	public function content(): string {
		return $this->content;
	}

	/**
	 * Get the frozen parsed template.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function blocks(): array {
		return $this->blocks;
	}

	/** Canonical snapshots and frozen metadata. */
	public function context(): Render_Context {
		return $this->context;
	}

	/** Resolved design, without rereading the active theme. */
	public function design(): Resolved_Email_Design {
		return $this->design;
	}

	/** Content capture revision, distinct from the post schema version. */
	public function revision(): int {
		return $this->revision;
	}

	/** Compiler required to reproduce this input. */
	public function compiler_version(): string {
		return $this->compiler_version;
	}
}
