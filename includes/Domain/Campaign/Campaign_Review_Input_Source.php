<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Live campaign review-input source.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

use CampaignBridge\Domain\Email\Review_Input;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves live WordPress inputs once, before the workflow freezes them. */
interface Campaign_Review_Input_Source {
	/** Capture the campaign's current template, design, and referenced content. */
	public function capture( Campaign $campaign, int $revision ): ?Review_Input;

	/** Capture the review input and authored envelope from one template read. */
	public function capture_for_snapshot( Campaign $campaign, int $revision ): ?Campaign_Review_Capture;
}
