<?php
/**
 * Stable Campaign REST resource mapping.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Services\Campaign\Delivery_Policy_Reader;
use CampaignBridge\Workflow\Campaign\Campaign_Actions;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Token\Token_Preview;
use CampaignBridge\Workflow\Campaign\Campaign_Test_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maps typed application values to intentionally bounded transport DTOs. */
final class Campaign_Rest_Resource {
	/**
	 * A campaign as the REST API publishes it, with the actions the reader may take now.
	 *
	 * @param Campaign       $campaign The campaign as read.
	 * @param Campaign_Actor $actor    Who is acting, with their resolved campaign authority.
	 * @return array<string, mixed>
	 */
	public static function campaign( Campaign $campaign, Campaign_Actor $actor ): array {
		return array(
			'id'                  => $campaign->id(),
			'state'               => $campaign->state(),
			'version'             => $campaign->version(),
			'owner_user_id'       => $campaign->owner_user_id(),
			'template_id'         => $campaign->template_id(),
			'provider'            => $campaign->provider(),
			'audience_reference'  => $campaign->audience_reference(),
			'active_snapshot_id'  => $campaign->active_snapshot_id(),
			'created_at'          => $campaign->created_at(),
			'updated_at'          => $campaign->updated_at(),
			'scheduled_for'       => $campaign->scheduled_for(),
			'approved_by_user_id' => $campaign->approved_by_user_id(),
			'actions'             => Campaign_Actions::for( $actor, $campaign, Delivery_Policy_Reader::current() ),
		);
	}

	/**
	 * A campaign snapshot as the REST API publishes it.
	 *
	 * @param Campaign_Snapshot $snapshot The campaign snapshot.
	 * @return array<string, mixed>
	 */
	public static function snapshot( Campaign_Snapshot $snapshot ): array {
		return array(
			'id'          => $snapshot->id(),
			'revision'    => $snapshot->revision(),
			'fingerprint' => $snapshot->artifact()->fingerprint(),
			'created_at'  => $snapshot->created_at(),
			'envelope'    => self::envelope( $snapshot ),
		);
	}

	/**
	 * The normalized remote draft reference; never a provider payload.
	 *
	 * @param Remote_Campaign_Reference $reference The campaign's remote reference.
	 * @return array<string, string|null>
	 */
	public static function remote( Remote_Campaign_Reference $reference ): array {
		return array(
			'provider'       => $reference->provider(),
			'remote_id'      => $reference->remote_id(),
			'observed_state' => $reference->observed_state(),
			'observed_at'    => $reference->observed_at(),
			'reconciled_at'  => $reference->reconciled_at(),
		);
	}

	/**
	 * The delivery attempt identity and its known outcome.
	 *
	 * @param Delivery_Attempt $attempt The delivery attempt.
	 * @return array<string, string>
	 */
	public static function attempt( Delivery_Attempt $attempt ): array {
		return array(
			'id'           => $attempt->id(),
			'status'       => $attempt->status(),
			'retryability' => $attempt->retryability(),
		);
	}

	/**
	 * What a new test send tested; never its recipients. Null on replay, because test requests are not stored.
	 *
	 * @param Campaign_Test_Result $result Test outcome.
	 * @return array<string, mixed>|null
	 */
	public static function test( Campaign_Test_Result $result ): ?array {
		$snapshot = $result->snapshot();
		$delivery = $result->delivery();
		if ( null === $snapshot || null === $delivery ) {
			return null;
		}

		return array(
			'format'          => $delivery->format(),
			'recipient_count' => $delivery->recipient_count(),
			'snapshot_id'     => $snapshot->id(),
			'fingerprint'     => $snapshot->artifact()->fingerprint(),
		);
	}

	/**
	 * The reviewed envelope and what a provider handoff would refuse.
	 *
	 * @param Campaign_Snapshot $snapshot The campaign snapshot.
	 * @return array<string, mixed>|null Null for a pre-envelope snapshot.
	 */
	private static function envelope( Campaign_Snapshot $snapshot ): ?array {
		$envelope = $snapshot->envelope();
		if ( null === $envelope ) {
			return null;
		}
		$problems = $envelope->problems();

		return array_merge(
			$envelope->to_array(),
			array(
				'complete' => array() === $problems,
				'problems' => $problems,
			)
		);
	}

	/**
	 * Validation outcome shared by the snapshot and validation routes.
	 *
	 * @param Compile_Result $result Compiler output.
	 * @return array<string, mixed>
	 */
	public static function validation( Compile_Result $result ): array {
		return array(
			'valid'            => $result->is_success(),
			'diagnostics'      => self::diagnostics( $result ),
			'compiler_version' => $result->compiler_version(),
			'profile_version'  => $result->profile_version(),
			'fingerprint'      => '' === $result->fingerprint() ? null : $result->fingerprint(),
		);
	}

	/**
	 * The canonical artifact representation established by POST /preview.
	 *
	 * @param Compile_Result $result Compiler output.
	 * @return array<string, mixed>
	 */
	public static function preview( Compile_Result $result ): array {
		return array(
			'html'             => $result->html(),
			'text'             => $result->text(),
			'diagnostics'      => self::diagnostics( $result ),
			'assets'           => $result->assets(),
			'compiler_version' => $result->compiler_version(),
			'profile_version'  => $result->profile_version(),
			'fingerprint'      => $result->fingerprint(),
			'sample'           => self::sample( $result ),
		);
	}

	/**
	 * The stored artifact of a reviewed snapshot, with the synthetic personalization sample.
	 *
	 * @param Campaign_Snapshot $snapshot Integrity-checked snapshot.
	 * @return array<string, mixed>
	 */
	public static function artifact( Campaign_Snapshot $snapshot ): array {
		$artifact = $snapshot->artifact();
		$preview  = Token_Preview::default();

		return array(
			'html'             => $artifact->html(),
			'text'             => $artifact->text(),
			'fingerprint'      => $artifact->fingerprint(),
			'compiler_version' => $artifact->compiler_version(),
			'profile_version'  => $artifact->profile_version(),
			'sample'           => $preview->applies_to( $artifact->html() . $artifact->text() )
				? array(
					'html' => $preview->html( $artifact->html() ),
					'text' => $preview->text( $artifact->text() ),
				)
				: null,
		);
	}

	/**
	 * Compiler diagnostics as plain arrays.
	 *
	 * @param Compile_Result $result Compiler output.
	 * @return array<int, array<string, string>>
	 */
	public static function diagnostics( Compile_Result $result ): array {
		return array_map(
			static fn ( $diagnostic ): array => $diagnostic->to_array(),
			$result->diagnostics()
		);
	}

	/**
	 * The sample-personalized HTML and text, when the artifact has provider-resolved tokens.
	 *
	 * @param Compile_Result $result Compiler output.
	 * @return array{html: string, text: string}|null
	 */
	private static function sample( Compile_Result $result ): ?array {
		$preview = Token_Preview::default();
		if ( ! $result->is_success() || ! $preview->applies_to( $result->html() . $result->text() ) ) {
			return null;
		}

		return array(
			'html' => $preview->html( $result->html() ),
			'text' => $preview->text( $result->text() ),
		);
	}
}
