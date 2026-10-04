<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment
/**
 * Scripted remote draft provider for workflow tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Support\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Draft_Outcome;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
use CampaignBridge\Domain\Provider\Remote_Draft_Matches;
use CampaignBridge\Domain\Provider\Remote_Draft_State;

/**
 * Stands in for the provider's draft API.
 *
 * It counts mutations, replays scripted outcomes, and models the remote
 * draft: a sync sets its audience, while tests may change its status,
 * audience, or segment as if edited in the provider.
 */
final class Scripted_Draft_Gateway implements Provider_Draft_Gateway {
	public int $creates = 0;

	public int $syncs = 0;

	public int $inspections = 0;

	/** @var array<int, Draft_Content> Content sent by creates and syncs, in order. */
	public array $contents = array();

	/** @var array<int, Draft_Outcome> Outcomes for successive creates. */
	public array $create_outcomes = array();

	/** @var array<int, Action_Outcome> Outcomes for successive syncs. */
	public array $sync_outcomes = array();

	/** @var (\Closure(): void)|null Runs while the remote create is in flight. */
	public ?\Closure $during_create = null;

	/** @var (\Closure(): void)|null Runs while a sync is in flight, as a concurrent request could. */
	public ?\Closure $during_sync = null;

	public string $next_remote_id = 'mc0001';

	/** Remote status as the provider would report it. */
	public string $remote_status = Remote_Draft_State::DRAFT;

	/** Remote audience; null until a create or sync sets it. */
	public ?string $remote_audience = null;

	public bool $remote_segmented = false;

	/** When set, every inspection fails with this error. */
	public ?Provider_Error $inspect_error = null;

	/** When true, a sync is accepted but leaves the remote audience unchanged. */
	public bool $sync_ignores_audience = false;

	/** Scheduled or actual send time the provider reports, as UTC. */
	public ?string $remote_send_time = null;

	/** Result of the next draft search; null means a complete search found nothing. */
	public Remote_Draft_Matches|Provider_Error|null $found = null;

	/** @var array<int, array{title: string, since: string}> Draft searches, in order. */
	public array $searches = array();

	public function slug(): string {
		return 'mailchimp';
	}

	public function create_draft( array $settings, Draft_Content $content ): Draft_Outcome {
		++$this->creates;
		$this->contents[] = $content;
		if ( null !== $this->during_create ) {
			( $this->during_create )();
		}
		$outcome = array_shift( $this->create_outcomes ) ?? Draft_Outcome::created( $this->next_remote_id );
		if ( null !== $outcome->remote_id() ) {
			$this->remote_audience = $content->audience_id();
		}

		return $outcome;
	}

	public function sync_draft( array $settings, string $remote_id, Draft_Content $content ): Action_Outcome {
		++$this->syncs;
		$this->contents[] = $content;
		if ( null !== $this->during_sync ) {
			$concurrent        = $this->during_sync;
			$this->during_sync = null;
			$concurrent();
		}
		$outcome          = array_shift( $this->sync_outcomes ) ?? Action_Outcome::accepted();
		if ( Action_Outcome::ACCEPTED === $outcome->status() && ! $this->sync_ignores_audience ) {
			$this->remote_audience = $content->audience_id();
		}

		return $outcome;
	}

	public function inspect_draft( array $settings, string $remote_id ): Remote_Draft_State|Provider_Error {
		++$this->inspections;

		return $this->inspect_error ?? Remote_Draft_State::create( $this->remote_status, (string) $this->remote_audience, $this->remote_segmented, $this->remote_send_time );
	}

	public function find_drafts( array $settings, string $title, string $created_after ): Remote_Draft_Matches|Provider_Error {
		$this->searches[] = array(
			'title' => $title,
			'since' => $created_after,
		);

		return $this->found ?? Remote_Draft_Matches::create( array(), true );
	}
}
