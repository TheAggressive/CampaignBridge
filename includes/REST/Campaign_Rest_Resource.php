<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Stable Campaign REST resource mapping.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Domain\Campaign\Campaign;
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
	/** @return array<string, mixed> */
	public static function campaign( Campaign $campaign ): array {
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
		);
	}

	/** @return array<string, mixed> */
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
	 * What a new test send tested; never its recipients. Null on replay,
	 * because test requests are not stored.
	 *
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

	/** @return array<int, array<string, string>> */
	public static function diagnostics( Compile_Result $result ): array {
		return array_map(
			static fn ( $diagnostic ): array => $diagnostic->to_array(),
			$result->diagnostics()
		);
	}

	/** @return array{html: string, text: string}|null */
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
