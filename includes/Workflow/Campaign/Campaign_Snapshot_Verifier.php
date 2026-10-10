<?php
/**
 * Verifies a campaign's selected snapshot.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot_Source;
use CampaignBridge\Services\Email\Compiler_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single content-authority check shared by review, approval, and handoff.
 *
 * A snapshot is trusted only when it belongs to the campaign and recompiling
 * its frozen review input reproduces the stored artifact fingerprint. Live
 * WordPress content is never read.
 */
final class Campaign_Snapshot_Verifier {
	/**
	 * Build the campaign snapshot verifier.
	 *
	 * @param Campaign_Snapshot_Source $snapshots Snapshot storage.
	 */
	public function __construct( private readonly Campaign_Snapshot_Source $snapshots ) {}

	/**
	 * The campaign's active snapshot, if it is intact and belongs to the campaign.
	 *
	 * @param Campaign $campaign The campaign as read.
	 */
	public function verify( Campaign $campaign ): Campaign_Snapshot|Campaign_Workflow_Error {
		if ( null === $campaign->active_snapshot_id() ) {
			return new Campaign_Workflow_Error( Campaign_Workflow_Error::MISSING_SNAPSHOT, 'Campaign has no selected immutable snapshot.' );
		}
		$snapshot = $this->snapshots->get( $campaign->active_snapshot_id() );
		if ( null === $snapshot || $snapshot->campaign_id() !== $campaign->id() ) {
			return new Campaign_Workflow_Error( Campaign_Workflow_Error::MISSING_SNAPSHOT, 'Campaign snapshot is unavailable.' );
		}
		$result = Compiler_Factory::create( $snapshot->review_input()->design() )->compile( $snapshot->review_input()->blocks(), $snapshot->review_input()->context() );
		if ( ! $result->is_success() || ! hash_equals( $snapshot->artifact()->fingerprint(), $result->fingerprint() ) ) {
			return new Campaign_Workflow_Error( Campaign_Workflow_Error::VALIDATION_FAILED, 'The selected snapshot does not reproduce its reviewed artifact.' );
		}

		return $snapshot;
	}
}
