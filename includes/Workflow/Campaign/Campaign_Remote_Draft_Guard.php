<?php
/**
 * Re-asserts an approved remote draft before anything is delivered from it.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
use CampaignBridge\Domain\Provider\Remote_Draft_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Never trusts a remote draft as-is before delivering from it.
 *
 * A draft can be edited in the provider after approval, or keep an old
 * audience or envelope after approval was revoked and given again. Before a
 * test, schedule, or send the guard:
 *
 * 1. reads the draft and requires it to be unsent;
 * 2. re-asserts the approved audience, envelope, and content with
 *    idempotent updates;
 * 3. reads it back and, when the whole audience will receive it, requires
 *    exactly the approved audience with no segment.
 *
 * Every call is a read or an idempotent update and none reaches the
 * audience, so a refusal leaves nothing to reconcile.
 */
final class Campaign_Remote_Draft_Guard {
	/**
	 * Build the campaign remote draft guard.
	 *
	 * @param Provider_Draft_Gateway $drafts Provider draft gateway.
	 */
	public function __construct( private readonly Provider_Draft_Gateway $drafts ) {}

	/**
	 * Re-assert the approved draft and prove what the provider holds.
	 *
	 * @param array<string, mixed> $settings       Decrypted provider settings.
	 * @param string               $remote_id      The provider's campaign ID.
	 * @param Draft_Content        $content        The draft content.
	 * @param bool                 $whole_audience Whether the operation reaches the campaign audience.
	 * @return array{0: string, 1: string, 2: Provider_Error|null}|null Refusal, or null when the draft matches.
	 */
	public function reassert( array $settings, string $remote_id, Draft_Content $content, bool $whole_audience ): ?array {
		$before = $this->drafts->inspect_draft( $settings, $remote_id );
		if ( $before instanceof Provider_Error ) {
			return array( Campaign_Workflow_Error::PROVIDER_FAILED, 'The provider draft could not be read. Nothing was delivered.', $before );
		}
		if ( Remote_Draft_State::DRAFT !== $before->status() ) {
			return array( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, sprintf( 'The provider campaign is %s, not an unsent draft; it was changed outside CampaignBridge. Reconcile before delivering. Nothing was delivered.', $before->status() ), null );
		}

		$sync = $this->drafts->sync_draft( $settings, $remote_id, $content );
		if ( Action_Outcome::ACCEPTED !== $sync->status() ) {
			return array( Campaign_Workflow_Error::PROVIDER_FAILED, 'The provider draft could not be brought in line with the approved campaign. Nothing was delivered.', $sync->error() );
		}
		if ( ! $whole_audience ) {
			return null;
		}

		$after = $this->drafts->inspect_draft( $settings, $remote_id );
		if ( $after instanceof Provider_Error ) {
			return array( Campaign_Workflow_Error::PROVIDER_FAILED, 'The provider draft could not be verified. Nothing was delivered.', $after );
		}

		return $after->matches( $content->audience_id() )
			? null
			: array( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The provider draft targets a segment or audience that was not approved. Remove it in the provider, then try again. Nothing was delivered.', null );
	}
}
