<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable values use explicit signatures and class-level invariant documentation.
/**
 * Review input and envelope captured together.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

use CampaignBridge\Domain\Email\Review_Input;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a snapshot freezes, read from one template load so the
 * reviewed content and envelope can never come from different edits.
 */
final class Campaign_Review_Capture {
	public function __construct(
		private readonly Review_Input $review_input,
		private readonly Campaign_Envelope $envelope
	) {}

	public function review_input(): Review_Input {
		return $this->review_input;
	}

	public function envelope(): Campaign_Envelope {
		return $this->envelope;
	}
}
