<?php
/**
 * Canonical provider-neutral campaign application workflow.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign_List_Filter;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Review_Input_Source;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot_Source;
use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Campaign_State_Machine;
use CampaignBridge\Domain\Campaign\Campaign_Transaction;
use CampaignBridge\Domain\Email\Compiled_Artifact;
use CampaignBridge\Services\Email\Compiler_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns every reversible local campaign lifecycle mutation.
 *
 * It has no HTTP, UI, CLI, provider, or global-current-user dependency.
 */
final class Campaign_Workflow {
	/**
	 * Build the campaign workflow.
	 *
	 * @param Campaign_Source              $campaigns          Campaign storage.
	 * @param Campaign_Snapshot_Source     $snapshots          Snapshot storage.
	 * @param Audit_Event_Source           $audits             Audit event storage.
	 * @param Campaign_Review_Input_Source $review_inputs      Review input capture.
	 * @param Campaign_Template_Authority  $template_authority Decides template access.
	 * @param Campaign_Transaction         $transaction        Runs writes atomically.
	 * @param Campaign_Id_Generator        $ids                Identifier generator.
	 * @param Campaign_Clock               $clock              Source of the current time.
	 */
	public function __construct(
		private readonly Campaign_Source $campaigns,
		private readonly Campaign_Snapshot_Source $snapshots,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Review_Input_Source $review_inputs,
		private readonly Campaign_Template_Authority $template_authority,
		private readonly Campaign_Transaction $transaction,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * Load one campaign through the same object-authorization boundary as mutations.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id Campaign ID.
	 */
	public function get( Campaign_Actor $actor, string $campaign_id ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_read' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}

		return Campaign_Workflow_Result::success( $loaded );
	}

	/**
	 * The campaign's active snapshot, after proving it still reproduces its reviewed artifact.
	 *
	 * Read-only: authorized like get(), and refused with MISSING_SNAPSHOT or
	 * VALIDATION_FAILED instead of showing an artifact that was not reviewed.
	 *
	 * @param Campaign_Actor $actor       Reader.
	 * @param string         $campaign_id Campaign ID.
	 */
	public function reviewed_snapshot( Campaign_Actor $actor, string $campaign_id ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_read' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		$snapshot = $this->verified_snapshot( $loaded );
		if ( $snapshot instanceof Campaign_Workflow_Error ) {
			return Campaign_Workflow_Result::failure( $snapshot, $loaded );
		}

		return Campaign_Workflow_Result::success( $loaded, $snapshot );
	}

	/**
	 * Return one bounded, authorized owner collection without in-memory filtering.
	 *
	 * @param Campaign_Actor            $actor         Who is acting, with their resolved campaign authority.
	 * @param int                       $owner_user_id Campaign owner's user ID.
	 * @param int                       $limit         Maximum number of records.
	 * @param int                       $offset        Number of records to skip.
	 * @param Campaign_List_Filter|null $filter        Optional list filter.
	 */
	public function list( Campaign_Actor $actor, int $owner_user_id, int $limit, int $offset, ?Campaign_List_Filter $filter = null ): Campaign_Workflow_List_Result {
		if ( 1 > $owner_user_id || 1 > $limit || 100 < $limit || 0 > $offset ) {
			return Campaign_Workflow_List_Result::failure(
				new Campaign_Workflow_Error( Campaign_Workflow_Error::INVALID_INPUT, 'Campaign collection input is invalid.' )
			);
		}
		if ( ! $actor->can_manage_all() && ( ! $actor->can_create() || $actor->user_id() !== $owner_user_id ) ) {
			return Campaign_Workflow_List_Result::failure(
				new Campaign_Workflow_Error( Campaign_Workflow_Error::FORBIDDEN, 'Campaign collection access is not allowed.' )
			);
		}

		return Campaign_Workflow_List_Result::success(
			$this->campaigns->for_owner( $owner_user_id, $limit, $offset, $filter ),
			$this->campaigns->count_for_owner( $owner_user_id, $filter )
		);
	}

	/**
	 * Create a draft campaign from a template.
	 *
	 * @param Campaign_Actor $actor              Who is acting, with their resolved campaign authority.
	 * @param int            $owner_user_id      Campaign owner's user ID.
	 * @param int            $template_id        Email template post ID.
	 * @param string|null    $provider           Provider slug.
	 * @param string|null    $audience_reference Audience reference, or null.
	 */
	public function create(
		Campaign_Actor $actor,
		int $owner_user_id,
		int $template_id,
		?string $provider = null,
		?string $audience_reference = null
	): Campaign_Workflow_Result {
		$id = $this->ids->generate( 'campaign' );
		if ( ! $actor->can_create() || ( $owner_user_id !== $actor->user_id() && ! $actor->can_manage_all() ) ) {
			return $this->failure( Campaign_Workflow_Error::FORBIDDEN, 'Campaign creation is not allowed.', $actor, 'campaign_create', $id, null, array(), 'denied' );
		}
		$template_denial = $this->authorize_template( $actor, $template_id, 'campaign_create', $id );
		if ( null !== $template_denial ) {
			return $template_denial;
		}

		try {
			$campaign = Campaign::create( $id, $owner_user_id, $template_id, $provider, $audience_reference, $this->clock->now() );
		} catch ( \InvalidArgumentException ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_INPUT, 'Campaign creation input is invalid.', $actor, 'campaign_create', $id );
		}

		$written = $this->transaction->run(
			fn (): bool => $this->campaigns->add( $campaign )
				&& $this->audits->add( $this->event( $actor, 'campaign_create', $campaign->id(), 'success', array( 'version' => 1 ) ) )
		);
		if ( ! $written ) {
			return $this->failure( Campaign_Workflow_Error::PERSISTENCE_FAILED, 'Campaign creation could not be persisted.', $actor, 'campaign_create', $id );
		}

		return Campaign_Workflow_Result::success( $campaign );
	}

	/**
	 * Point the campaign at another template.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 * @param int            $template_id      Email template post ID.
	 */
	public function edit_template( Campaign_Actor $actor, string $campaign_id, int $expected_version, int $template_id ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_edit' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		if ( 1 > $template_id ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_INPUT, 'Template identifier must be positive.', $actor, 'campaign_edit', $campaign_id, $loaded );
		}
		$template_denial = $this->authorize_template( $actor, $template_id, 'campaign_edit', $campaign_id, $loaded );
		if ( null !== $template_denial ) {
			return $template_denial;
		}
		if ( ! Campaign_State_Machine::is_editable( $loaded->state() ) ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_STATE, 'Campaign is not editable in its current state.', $actor, 'campaign_edit', $campaign_id, $loaded );
		}
		if ( $loaded->version() !== $expected_version ) {
			return $this->conflict( $actor, 'campaign_edit', $loaded, $expected_version );
		}

		$replacement = $loaded->edit_template( $template_id, $this->clock->now() );
		return $this->persist_mutation( $actor, 'campaign_edit', $loaded, $replacement, $expected_version, array( 'snapshot_invalidated' => null !== $loaded->active_snapshot_id() ) );
	}

	/**
	 * Choose the campaign's provider and audience.
	 *
	 * @param Campaign_Actor $actor              Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id        Campaign ID.
	 * @param int            $expected_version   The version the caller last read.
	 * @param string|null    $provider           Provider slug.
	 * @param string|null    $audience_reference Audience reference, or null.
	 */
	public function select_audience(
		Campaign_Actor $actor,
		string $campaign_id,
		int $expected_version,
		?string $provider,
		?string $audience_reference
	): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_audience_select' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		if ( ! Campaign_State_Machine::is_editable( $loaded->state() ) ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_STATE, 'Audience selection is not editable in the current state.', $actor, 'campaign_audience_select', $campaign_id, $loaded );
		}
		if ( $loaded->version() !== $expected_version ) {
			return $this->conflict( $actor, 'campaign_audience_select', $loaded, $expected_version );
		}

		try {
			$replacement = $loaded->select_audience( $provider, $audience_reference, $this->clock->now() );
		} catch ( \InvalidArgumentException ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_INPUT, 'Provider or audience reference is invalid.', $actor, 'campaign_audience_select', $campaign_id, $loaded );
		}

		return $this->persist_mutation(
			$actor,
			'campaign_audience_select',
			$loaded,
			$replacement,
			$expected_version,
			array( 'approval_invalidated' => Campaign_State::APPROVED === $loaded->state() )
		);
	}

	/**
	 * Freeze the template's current content into a new snapshot for review.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 */
	public function snapshot( Campaign_Actor $actor, string $campaign_id, int $expected_version ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_snapshot' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		$template_denial = $this->authorize_template( $actor, $loaded->template_id(), 'campaign_snapshot', $campaign_id, $loaded );
		if ( null !== $template_denial ) {
			return $template_denial;
		}
		if ( ! Campaign_State_Machine::is_editable( $loaded->state() ) ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_STATE, 'A snapshot cannot be refreshed in the current state.', $actor, 'campaign_snapshot', $campaign_id, $loaded );
		}
		if ( $loaded->version() !== $expected_version ) {
			return $this->conflict( $actor, 'campaign_snapshot', $loaded, $expected_version );
		}

		$revision = $this->next_revision( $campaign_id );
		$capture  = $this->review_inputs->capture_for_snapshot( $loaded, $revision );
		if ( null === $capture ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_INPUT, 'The campaign template could not be captured.', $actor, 'campaign_snapshot', $campaign_id, $loaded );
		}
		$input    = $capture->review_input();
		$compiled = Compiler_Factory::create( $input->design() )->compile( $input->blocks(), $input->context() );
		if ( ! $compiled->is_success() ) {
			$this->audit_failure( $actor, 'campaign_snapshot', $campaign_id, Campaign_Workflow_Error::VALIDATION_FAILED, array( 'revision' => $revision ) );
			return Campaign_Workflow_Result::failure(
				new Campaign_Workflow_Error( Campaign_Workflow_Error::VALIDATION_FAILED, 'Campaign validation failed; no snapshot was persisted.' ),
				$loaded,
				$compiled
			);
		}

		$snapshot    = Campaign_Snapshot::from_array(
			array(
				'schema_version' => Campaign_Snapshot::SCHEMA_VERSION,
				'id'             => $this->ids->generate( 'snapshot' ),
				'campaign_id'    => $campaign_id,
				'revision'       => $revision,
				'review_input'   => $input->to_array(),
				'artifact'       => Compiled_Artifact::from_result( $compiled )->to_array(),
				'created_at'     => $this->clock->now(),
				'envelope'       => $capture->envelope()->to_array(),
			)
		);
		$replacement = $loaded->select_snapshot( $snapshot->id(), $this->clock->now() );
		$written     = $this->transaction->run(
			fn (): bool => $this->snapshots->add( $snapshot )
				&& $this->campaigns->compare_and_swap( $replacement, $expected_version )
				&& $this->audits->add(
					$this->event(
						$actor,
						'campaign_snapshot',
						$campaign_id,
						'success',
						array(
							'revision'             => $revision,
							'snapshot_id'          => $snapshot->id(),
							'artifact_fingerprint' => $snapshot->artifact()->fingerprint(),
							'approval_invalidated' => Campaign_State::APPROVED === $loaded->state(),
							'envelope_complete'    => array() === $capture->envelope()->problems(),
						)
					)
				)
		);
		if ( ! $written ) {
			return $this->write_failure( $actor, 'campaign_snapshot', $loaded, $expected_version );
		}

		return Campaign_Workflow_Result::success( $replacement, $snapshot, $compiled );
	}

	/**
	 * Check the live template without changing the campaign.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id Campaign ID.
	 */
	public function validate( Campaign_Actor $actor, string $campaign_id ): Campaign_Workflow_Result {
		return $this->compile_live( $actor, $campaign_id, 'campaign_validate', true );
	}

	/**
	 * Compile the live template without changing the campaign.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id Campaign ID.
	 */
	public function preview( Campaign_Actor $actor, string $campaign_id ): Campaign_Workflow_Result {
		return $this->compile_live( $actor, $campaign_id, 'campaign_preview', false );
	}

	/**
	 * Submit the snapshot for approval.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 */
	public function submit_for_review( Campaign_Actor $actor, string $campaign_id, int $expected_version ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_submit_review' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		$template_denial = $this->authorize_template( $actor, $loaded->template_id(), 'campaign_submit_review', $campaign_id, $loaded );
		if ( null !== $template_denial ) {
			return $template_denial;
		}
		$artifact = $this->verified_snapshot( $loaded );
		if ( $artifact instanceof Campaign_Workflow_Error ) {
			return $this->failure( $artifact->code(), $artifact->message(), $actor, 'campaign_submit_review', $campaign_id, $loaded );
		}
		if ( $loaded->version() !== $expected_version ) {
			return $this->conflict( $actor, 'campaign_submit_review', $loaded, $expected_version );
		}
		try {
			$replacement = $loaded->transition_to( Campaign_State::READY_FOR_REVIEW, $this->clock->now() );
		} catch ( \InvalidArgumentException ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_STATE, 'Campaign cannot be submitted from its current state.', $actor, 'campaign_submit_review', $campaign_id, $loaded );
		}

		return $this->persist_mutation(
			$actor,
			'campaign_submit_review',
			$loaded,
			$replacement,
			$expected_version,
			array(
				'snapshot_id'          => $artifact->id(),
				'artifact_fingerprint' => $artifact->artifact()->fingerprint(),
			)
		);
	}

	/**
	 * Approve the reviewed snapshot.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 */
	public function approve( Campaign_Actor $actor, string $campaign_id, int $expected_version ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_approve', true );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		$template_denial = $this->authorize_template( $actor, $loaded->template_id(), 'campaign_approve', $campaign_id, $loaded );
		if ( null !== $template_denial ) {
			return $template_denial;
		}
		$artifact = $this->verified_snapshot( $loaded );
		if ( $artifact instanceof Campaign_Workflow_Error ) {
			return $this->failure( Campaign_Workflow_Error::APPROVAL_NOT_ALLOWED, $artifact->message(), $actor, 'campaign_approve', $campaign_id, $loaded );
		}
		if ( $loaded->version() !== $expected_version ) {
			return $this->conflict( $actor, 'campaign_approve', $loaded, $expected_version );
		}
		try {
			$replacement = $loaded->approve_by( $actor->user_id(), $this->clock->now() );
		} catch ( \InvalidArgumentException ) {
			return $this->failure( Campaign_Workflow_Error::APPROVAL_NOT_ALLOWED, 'Campaign cannot be approved from its current state.', $actor, 'campaign_approve', $campaign_id, $loaded );
		}

		return $this->persist_mutation(
			$actor,
			'campaign_approve',
			$loaded,
			$replacement,
			$expected_version,
			array(
				'snapshot_id'          => $artifact->id(),
				'artifact_fingerprint' => $artifact->artifact()->fingerprint(),
				'compiler_version'     => $artifact->artifact()->compiler_version(),
				'profile_version'      => $artifact->artifact()->profile_version(),
			)
		);
	}

	/**
	 * Return an approved campaign to review.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 */
	public function revoke_approval( Campaign_Actor $actor, string $campaign_id, int $expected_version ): Campaign_Workflow_Result {
		return $this->transition( $actor, $campaign_id, $expected_version, Campaign_State::READY_FOR_REVIEW, 'campaign_revoke_approval' );
	}

	/**
	 * Archive the campaign.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 */
	public function archive( Campaign_Actor $actor, string $campaign_id, int $expected_version ): Campaign_Workflow_Result {
		return $this->transition( $actor, $campaign_id, $expected_version, Campaign_State::ARCHIVED, 'campaign_archive' );
	}

	/**
	 * Copy the campaign as a new draft, once per idempotency key.
	 *
	 * @param Campaign_Actor $actor           Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id     Campaign ID.
	 * @param string         $idempotency_key Client retry key; the same key replays the first outcome.
	 */
	public function duplicate( Campaign_Actor $actor, string $campaign_id, string $idempotency_key ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, 'campaign_duplicate' );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		if ( ! $actor->can_create() ) {
			return $this->failure( Campaign_Workflow_Error::FORBIDDEN, 'Campaign duplication is not allowed.', $actor, 'campaign_duplicate', $campaign_id, $loaded, array(), 'denied' );
		}
		$template_denial = $this->authorize_template( $actor, $loaded->template_id(), 'campaign_duplicate', $campaign_id, $loaded );
		if ( null !== $template_denial ) {
			return $template_denial;
		}
		if ( '' === $idempotency_key || 191 < strlen( $idempotency_key ) ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_INPUT, 'A bounded idempotency key is required.', $actor, 'campaign_duplicate', $campaign_id, $loaded );
		}

		$duplicate_id = $this->duplicate_id( $campaign_id, $idempotency_key );
		$prior        = $this->campaigns->get( $duplicate_id );
		if ( null !== $prior ) {
			return $this->duplicate_replay( $actor, $loaded, $prior );
		}

		$now       = $this->clock->now();
		$duplicate = Campaign::create(
			$duplicate_id,
			$loaded->owner_user_id(),
			$loaded->template_id(),
			$loaded->provider(),
			$loaded->audience_reference(),
			$now
		);
		$written   = $this->transaction->run(
			fn (): bool => $this->campaigns->add( $duplicate )
				&& $this->audits->add( $this->event( $actor, 'campaign_duplicate', $duplicate->id(), 'success', array( 'source_campaign_id' => $campaign_id ) ) )
		);
		if ( ! $written ) {
			$prior = $this->campaigns->get( $duplicate_id );
			if ( null !== $prior ) {
				return $this->duplicate_replay( $actor, $loaded, $prior );
			}
			return $this->failure( Campaign_Workflow_Error::PERSISTENCE_FAILED, 'Campaign duplication could not be persisted.', $actor, 'campaign_duplicate', $campaign_id, $loaded );
		}

		return Campaign_Workflow_Result::success( $duplicate );
	}

	/**
	 * Compile the campaign's live template, optionally auditing the check.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id Campaign ID.
	 * @param string         $action      Audit action name.
	 * @param bool           $audit       Whether to audit the check.
	 */
	private function compile_live( Campaign_Actor $actor, string $campaign_id, string $action, bool $audit ): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, $action );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		$template_denial = $this->authorize_template( $actor, $loaded->template_id(), $action, $campaign_id, $loaded );
		if ( null !== $template_denial ) {
			return $template_denial;
		}
		$input = $this->review_inputs->capture( $loaded, $this->next_revision( $campaign_id ) );
		if ( null === $input ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_INPUT, 'The campaign template could not be captured.', $actor, $action, $campaign_id, $loaded );
		}
		$compiled = Compiler_Factory::create( $input->design() )->compile( $input->blocks(), $input->context() );
		if (
			$audit
			&& ! $this->audits->add(
				$this->event(
					$actor,
					$action,
					$campaign_id,
					$compiled->is_success() ? 'success' : 'failure',
					array( 'artifact_fingerprint' => $compiled->is_success() ? $compiled->fingerprint() : null )
				)
			)
		) {
			return Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::PERSISTENCE_FAILED, 'Campaign validation audit could not be persisted.' ), $loaded, $compiled );
		}
		if ( ! $compiled->is_success() ) {
			return Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::VALIDATION_FAILED, 'Campaign validation failed.' ), $loaded, $compiled );
		}

		return Campaign_Workflow_Result::success( $loaded, null, $compiled );
	}

	/**
	 * Move the campaign to another state through the state machine.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id      Campaign ID.
	 * @param int            $expected_version The version the caller last read.
	 * @param string         $state            Campaign lifecycle state.
	 * @param string         $action           Audit action name.
	 */
	private function transition(
		Campaign_Actor $actor,
		string $campaign_id,
		int $expected_version,
		string $state,
		string $action
	): Campaign_Workflow_Result {
		$loaded = $this->authorized( $actor, $campaign_id, $action );
		if ( $loaded instanceof Campaign_Workflow_Result ) {
			return $loaded;
		}
		if ( $loaded->version() !== $expected_version ) {
			return $this->conflict( $actor, $action, $loaded, $expected_version );
		}
		try {
			$replacement = $loaded->transition_to( $state, $this->clock->now() );
		} catch ( \InvalidArgumentException ) {
			return $this->failure( Campaign_Workflow_Error::INVALID_STATE, 'Campaign transition is not allowed.', $actor, $action, $campaign_id, $loaded );
		}

		return $this->persist_mutation( $actor, $action, $loaded, $replacement, $expected_version );
	}

	/**
	 * The campaign's active snapshot, if it is intact.
	 *
	 * @param Campaign $campaign The campaign as read.
	 */
	private function verified_snapshot( Campaign $campaign ): Campaign_Snapshot|Campaign_Workflow_Error {
		return ( new Campaign_Snapshot_Verifier( $this->snapshots ) )->verify( $campaign );
	}


	/**
	 * Store a campaign change and its audit event together, under the version claim.
	 *
	 * @param Campaign_Actor       $actor            Who is acting, with their resolved campaign authority.
	 * @param string               $action           Audit action name.
	 * @param Campaign             $before           The campaign before the change.
	 * @param Campaign             $after            The campaign after the change.
	 * @param int                  $expected_version The version the caller last read.
	 * @param array<string, mixed> $context          Safe audit context.
	 */
	private function persist_mutation(
		Campaign_Actor $actor,
		string $action,
		Campaign $before,
		Campaign $after,
		int $expected_version,
		array $context = array()
	): Campaign_Workflow_Result {
		$context = array_merge(
			array(
				'from_state'       => $before->state(),
				'to_state'         => $after->state(),
				'expected_version' => $expected_version,
				'result_version'   => $after->version(),
			),
			$context
		);
		$written = $this->transaction->run(
			fn (): bool => $this->campaigns->compare_and_swap( $after, $expected_version )
				&& $this->audits->add( $this->event( $actor, $action, $after->id(), 'success', $context ) )
		);
		if ( ! $written ) {
			return $this->write_failure( $actor, $action, $before, $expected_version );
		}

		return Campaign_Workflow_Result::success( $after );
	}

	/**
	 * Load the campaign and check that the actor may perform the action.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param string         $campaign_id Campaign ID.
	 * @param string         $action      Audit action name.
	 * @param bool           $approval    Whether this is an approval.
	 */
	private function authorized( Campaign_Actor $actor, string $campaign_id, string $action, bool $approval = false ): Campaign|Campaign_Workflow_Result {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( null === $campaign ) {
			return $this->failure( Campaign_Workflow_Error::NOT_FOUND, 'Campaign was not found.', $actor, $action, $campaign_id );
		}
		$allowed = $approval ? $actor->can_approve( $campaign ) : $actor->can_manage( $campaign );
		if ( ! $allowed ) {
			return $this->failure( Campaign_Workflow_Error::FORBIDDEN, 'Campaign operation is not allowed.', $actor, $action, $campaign_id, $campaign, array(), 'denied' );
		}

		return $campaign;
	}

	/**
	 * Refuse the action when the actor may not use the template.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param int            $template_id Email template post ID.
	 * @param string         $action      Audit action name.
	 * @param string         $target_id   ID of the record the event is about.
	 * @param Campaign|null  $campaign    The campaign as read.
	 */
	private function authorize_template(
		Campaign_Actor $actor,
		int $template_id,
		string $action,
		string $target_id,
		?Campaign $campaign = null
	): ?Campaign_Workflow_Result {
		if ( $this->template_authority->can_use_template( $actor, $template_id ) ) {
			return null;
		}

		return $this->failure(
			Campaign_Workflow_Error::FORBIDDEN,
			'Template access is not allowed.',
			$actor,
			$action,
			$target_id,
			$campaign,
			array( 'template_id' => $template_id ),
			'denied'
		);
	}

	/**
	 * Refuse a stale version.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $action           Audit action name.
	 * @param Campaign       $campaign         The campaign as read.
	 * @param int            $expected_version The version the caller last read.
	 */
	private function conflict( Campaign_Actor $actor, string $action, Campaign $campaign, int $expected_version ): Campaign_Workflow_Result {
		return $this->failure(
			Campaign_Workflow_Error::CONFLICT,
			'Campaign version is stale.',
			$actor,
			$action,
			$campaign->id(),
			$campaign,
			array(
				'expected_version' => $expected_version,
				'current_version'  => $campaign->version(),
			)
		);
	}

	/**
	 * Explain a failed write: a concurrent change, or a persistence failure.
	 *
	 * @param Campaign_Actor $actor            Who is acting, with their resolved campaign authority.
	 * @param string         $action           Audit action name.
	 * @param Campaign       $before           The campaign before the change.
	 * @param int            $expected_version The version the caller last read.
	 */
	private function write_failure( Campaign_Actor $actor, string $action, Campaign $before, int $expected_version ): Campaign_Workflow_Result {
		$current = $this->campaigns->get( $before->id() );
		if ( null !== $current && $current->version() !== $expected_version ) {
			return $this->conflict( $actor, $action, $current, $expected_version );
		}

		return $this->failure( Campaign_Workflow_Error::PERSISTENCE_FAILED, 'Campaign mutation could not be persisted.', $actor, $action, $before->id(), $current ?? $before );
	}

	/**
	 * Audit a failure and return it.
	 *
	 * @param string               $code      Stable error code.
	 * @param string               $message   Operator-safe message.
	 * @param Campaign_Actor       $actor     Who is acting, with their resolved campaign authority.
	 * @param string               $action    Audit action name.
	 * @param string               $target_id ID of the record the event is about.
	 * @param Campaign|null        $campaign  The campaign as read.
	 * @param array<string, mixed> $context   Safe audit context.
	 * @param string               $result    Audit result: success, failure, denied, or unknown.
	 */
	private function failure(
		string $code,
		string $message,
		Campaign_Actor $actor,
		string $action,
		string $target_id,
		?Campaign $campaign = null,
		array $context = array(),
		string $result = 'failure'
	): Campaign_Workflow_Result {
		$this->audit_failure( $actor, $action, $target_id, $code, $context, $result );
		return Campaign_Workflow_Result::failure( new Campaign_Workflow_Error( $code, $message ), $campaign );
	}

	/**
	 * Append a failure audit event.
	 *
	 * @param Campaign_Actor       $actor     Who is acting, with their resolved campaign authority.
	 * @param string               $action    Audit action name.
	 * @param string               $target_id ID of the record the event is about.
	 * @param string               $code      Stable error code.
	 * @param array<string, mixed> $context   Safe audit context.
	 * @param string               $result    Audit result: success, failure, denied, or unknown.
	 */
	private function audit_failure(
		Campaign_Actor $actor,
		string $action,
		string $target_id,
		string $code,
		array $context = array(),
		string $result = 'failure'
	): void {
		try {
			$this->audits->add( $this->event( $actor, $action, $target_id, $result, array_merge( array( 'error_code' => $code ), $context ) ) );
		} catch ( \InvalidArgumentException ) {
			// Malformed external identifiers cannot become an unsafe audit record.
			return;
		}
	}

	/**
	 * Build one workflow audit event.
	 *
	 * @param Campaign_Actor       $actor     Who is acting, with their resolved campaign authority.
	 * @param string               $action    Audit action name.
	 * @param string               $target_id ID of the record the event is about.
	 * @param string               $result    Audit result: success, failure, denied, or unknown.
	 * @param array<string, mixed> $context   Safe audit context.
	 */
	private function event( Campaign_Actor $actor, string $action, string $target_id, string $result, array $context ): Audit_Event {
		return Audit_Event::from_array(
			array(
				'schema_version' => Audit_Event::SCHEMA_VERSION,
				'id'             => $this->ids->generate( 'audit' ),
				'actor_user_id'  => $actor->user_id(),
				'action'         => $action,
				'target_type'    => 'campaign',
				'target_id'      => $target_id,
				'result'         => $result,
				'context'        => Audit_Context::from_array( $context )->to_array(),
				'created_at'     => $this->clock->now(),
			)
		);
	}

	/**
	 * The next snapshot revision number for the campaign.
	 *
	 * @param string $campaign_id Campaign ID.
	 */
	private function next_revision( string $campaign_id ): int {
		$latest = $this->snapshots->for_campaign( $campaign_id, 1 );
		return isset( $latest[0] ) ? $latest[0]->revision() + 1 : 1;
	}

	/**
	 * The stable ID of the duplicate made with one idempotency key.
	 *
	 * @param string $campaign_id Campaign ID.
	 * @param string $key         Idempotency key.
	 */
	private function duplicate_id( string $campaign_id, string $key ): string {
		return 'duplicate-' . substr( hash( 'sha256', $campaign_id . "\0" . $key ), 0, 40 );
	}

	/**
	 * Answer a repeated duplicate request with the campaign it created.
	 *
	 * @param Campaign_Actor $actor     Who is acting, with their resolved campaign authority.
	 * @param Campaign       $source    The campaign being copied.
	 * @param Campaign       $duplicate The duplicate campaign.
	 */
	private function duplicate_replay( Campaign_Actor $actor, Campaign $source, Campaign $duplicate ): Campaign_Workflow_Result {
		if (
			Campaign_State::DRAFT !== $duplicate->state()
			|| 1 !== $duplicate->version()
			|| $source->owner_user_id() !== $duplicate->owner_user_id()
			|| $source->template_id() !== $duplicate->template_id()
			|| $source->provider() !== $duplicate->provider()
			|| $source->audience_reference() !== $duplicate->audience_reference()
			|| null !== $duplicate->active_snapshot_id()
		) {
			return $this->failure(
				Campaign_Workflow_Error::IDEMPOTENCY_CONFLICT,
				'The idempotency key does not match the prior duplication result.',
				$actor,
				'campaign_duplicate',
				$source->id(),
				$source
			);
		}

		return Campaign_Workflow_Result::success( $duplicate, null, null, true );
	}
}
