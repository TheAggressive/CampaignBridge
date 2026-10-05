<?php
/**
 * One page of a campaign's audit history or delivery attempts.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable page of history records, or the refusal that withheld it. */
final class Campaign_History_Result {
	/**
	 * Build a result.
	 *
	 * @param array<int, Audit_Event|Delivery_Attempt> $records Newest first.
	 * @param int                                      $total   Records across all pages.
	 * @param Campaign_Workflow_Error|null             $error   Refusal.
	 */
	private function __construct(
		private readonly array $records,
		private readonly int $total,
		private readonly ?Campaign_Workflow_Error $error
	) {}

	/**
	 * Build a successful page.
	 *
	 * @param array<int, Audit_Event|Delivery_Attempt> $records Newest first.
	 * @param int                                      $total   Records across all pages.
	 */
	public static function success( array $records, int $total ): self {
		return new self( $records, $total, null );
	}

	/**
	 * Build a refusal.
	 *
	 * @param Campaign_Workflow_Error $error Refusal.
	 */
	public static function failure( Campaign_Workflow_Error $error ): self {
		return new self( array(), 0, $error );
	}

	/** Whether the page was read. */
	public function is_success(): bool {
		return null === $this->error;
	}

	/**
	 * Records on this page, newest first.
	 *
	 * @return array<int, Audit_Event|Delivery_Attempt>
	 */
	public function records(): array {
		return $this->records;
	}

	/** Records across all pages. */
	public function total(): int {
		return $this->total;
	}

	/** Refusal, when the page was withheld. */
	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}
}
