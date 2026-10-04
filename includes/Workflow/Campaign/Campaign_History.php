<?php
/**
 * Read-only campaign history for operator screens.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pages a campaign's audit events and delivery attempts, newest first.
 *
 * Access follows the campaign detail rule exactly: history is read only after
 * Campaign_Workflow::get() allows the actor to read the campaign, so a refusal
 * and its audit are identical to the detail route's. Nothing here writes.
 */
final class Campaign_History {
	/** Largest page a caller may request. */
	public const MAX_PER_PAGE = 100;

	/**
	 * Build the history service.
	 *
	 * @param Campaign_Workflow       $workflow Authorizes campaign reads.
	 * @param Audit_Event_Source      $audits   Audit events.
	 * @param Delivery_Attempt_Source $attempts Delivery attempts.
	 */
	public function __construct(
		private readonly Campaign_Workflow $workflow,
		private readonly Audit_Event_Source $audits,
		private readonly Delivery_Attempt_Source $attempts
	) {}

	/**
	 * One page of the campaign's audit events.
	 *
	 * @param Campaign_Actor $actor       Reader.
	 * @param string         $campaign_id Campaign ID.
	 * @param int            $per_page    Page size, 1 through MAX_PER_PAGE.
	 * @param int            $page        One-based page number.
	 */
	public function events( Campaign_Actor $actor, string $campaign_id, int $per_page, int $page ): Campaign_History_Result {
		$refused = $this->refusal( $actor, $campaign_id, $per_page, $page );
		if ( null !== $refused ) {
			return $refused;
		}

		return Campaign_History_Result::success(
			$this->audits->for_target( 'campaign', $campaign_id, $per_page, ( $page - 1 ) * $per_page ),
			$this->audits->count_for_target( 'campaign', $campaign_id )
		);
	}

	/**
	 * One page of the campaign's delivery attempts.
	 *
	 * @param Campaign_Actor $actor       Reader.
	 * @param string         $campaign_id Campaign ID.
	 * @param int            $per_page    Page size, 1 through MAX_PER_PAGE.
	 * @param int            $page        One-based page number.
	 */
	public function attempts( Campaign_Actor $actor, string $campaign_id, int $per_page, int $page ): Campaign_History_Result {
		$refused = $this->refusal( $actor, $campaign_id, $per_page, $page );
		if ( null !== $refused ) {
			return $refused;
		}

		return Campaign_History_Result::success(
			$this->attempts->for_campaign( $campaign_id, $per_page, ( $page - 1 ) * $per_page ),
			$this->attempts->count_for_campaign( $campaign_id )
		);
	}

	/**
	 * Refuse invalid paging or a campaign the actor cannot read.
	 *
	 * @param Campaign_Actor $actor       Reader.
	 * @param string         $campaign_id Campaign ID.
	 * @param int            $per_page    Page size.
	 * @param int            $page        One-based page number.
	 */
	private function refusal( Campaign_Actor $actor, string $campaign_id, int $per_page, int $page ): ?Campaign_History_Result {
		if ( 1 > $per_page || self::MAX_PER_PAGE < $per_page || 1 > $page ) {
			return Campaign_History_Result::failure(
				new Campaign_Workflow_Error( Campaign_Workflow_Error::INVALID_INPUT, 'Campaign history paging is invalid.' )
			);
		}

		$read  = $this->workflow->get( $actor, $campaign_id );
		$error = $read->error();

		return $read->is_success() || null === $error ? null : Campaign_History_Result::failure( $error );
	}
}
