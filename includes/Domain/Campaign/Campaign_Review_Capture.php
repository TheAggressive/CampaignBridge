<?php
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
	/**
	 * Build the campaign review capture.
	 *
	 * @param Review_Input      $review_input Frozen review input.
	 * @param Campaign_Envelope $envelope     Subject, preview text, and sender.
	 */
	public function __construct(
		private readonly Review_Input $review_input,
		private readonly Campaign_Envelope $envelope
	) {}

	/**
	 * The capture's review input.
	 */
	public function review_input(): Review_Input {
		return $this->review_input;
	}

	/**
	 * The capture's envelope.
	 */
	public function envelope(): Campaign_Envelope {
		return $this->envelope;
	}
}
