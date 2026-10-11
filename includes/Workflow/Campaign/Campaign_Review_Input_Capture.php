<?php
/**
 * Live campaign review-input capture coordinator.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Review_Capture;
use CampaignBridge\Domain\Campaign\Campaign_Review_Input_Source;
use CampaignBridge\Domain\Campaign\Campaign_Template_Input_Source;
use CampaignBridge\Domain\Email\Brand_Kit_Source;
use CampaignBridge\Domain\Email\Post_Snapshot_Source;
use CampaignBridge\Domain\Email\Review_Input;
use CampaignBridge\Workflow\Email\Template_Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Coordinates live data sources once, before immutable persistence. */
final class Campaign_Review_Input_Capture implements Campaign_Review_Input_Source {
	/**
	 * Build the campaign review input capture.
	 *
	 * @param Campaign_Template_Input_Source $templates  Template input storage.
	 * @param Post_Snapshot_Source           $posts      Post snapshot storage.
	 * @param Brand_Kit_Source               $brand_kits Brand Kit storage.
	 */
	public function __construct(
		private readonly Campaign_Template_Input_Source $templates,
		private readonly Post_Snapshot_Source $posts,
		private readonly Brand_Kit_Source $brand_kits
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function capture( Campaign $campaign, int $revision ): ?Review_Input {
		return $this->capture_for_snapshot( $campaign, $revision )?->review_input();
	}

	/**
	 * {@inheritDoc}
	 */
	public function capture_for_snapshot( Campaign $campaign, int $revision ): ?Campaign_Review_Capture {
		if ( 1 > $revision ) {
			return null;
		}

		$template = $this->templates->get( $campaign->template_id() );
		if ( null === $template ) {
			return null;
		}

		$input = ( new Template_Preview( $this->posts, $this->brand_kits->get(), $template->design_fonts() ) )->capture(
			$template->content(),
			$template->metadata()
		);

		return new Campaign_Review_Capture(
			new Review_Input(
				$input->content(),
				$input->blocks(),
				$input->context(),
				$input->design(),
				$revision,
				$input->compiler_version()
			),
			$template->envelope()
		);
	}
}
