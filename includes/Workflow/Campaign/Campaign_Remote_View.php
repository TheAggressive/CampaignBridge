<?php
/**
 * A campaign and its provider reference, as read for operator screens.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable read of a campaign's remote reference, or the refusal that withheld it. */
final class Campaign_Remote_View {
	/**
	 * Build a view.
	 *
	 * @param Campaign|null                  $campaign  Campaign as read.
	 * @param Remote_Campaign_Reference|null $reference Provider reference, when one exists.
	 * @param Campaign_Workflow_Error|null   $error     Refusal.
	 */
	private function __construct(
		private readonly ?Campaign $campaign,
		private readonly ?Remote_Campaign_Reference $reference,
		private readonly ?Campaign_Workflow_Error $error
	) {}

	/**
	 * A readable campaign and its reference, which is null before handoff.
	 *
	 * @param Campaign                       $campaign  Campaign as read.
	 * @param Remote_Campaign_Reference|null $reference Provider reference.
	 */
	public static function success( Campaign $campaign, ?Remote_Campaign_Reference $reference ): self {
		return new self( $campaign, $reference, null );
	}

	/**
	 * A refusal.
	 *
	 * @param Campaign_Workflow_Error $error Refusal.
	 */
	public static function failure( Campaign_Workflow_Error $error ): self {
		return new self( null, null, $error );
	}

	/** Whether the campaign was read. */
	public function is_success(): bool {
		return null === $this->error;
	}

	/** Campaign as read. */
	public function campaign(): ?Campaign {
		return $this->campaign;
	}

	/** Provider reference, or null before handoff. */
	public function reference(): ?Remote_Campaign_Reference {
		return $this->reference;
	}

	/** Refusal, when the read was withheld. */
	public function error(): ?Campaign_Workflow_Error {
		return $this->error;
	}
}
