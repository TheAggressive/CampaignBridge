<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable persistence values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Immutable durable campaign snapshot.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

use CampaignBridge\Domain\Email\Compiled_Artifact;
use CampaignBridge\Domain\Email\Review_Input;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Couples one frozen M1 review input to its exact successful artifact.
 *
 * Schema version 2 also freezes the authored envelope (subject, preview
 * text, sender) reviewed with the artifact. Version 1 snapshots predate the
 * envelope and read with none, so they can still be reviewed locally but
 * must be re-snapshotted before any provider handoff.
 */
final class Campaign_Snapshot {
	public const SCHEMA_VERSION = 2;

	/** The pre-envelope schema version that remains readable. */
	public const LEGACY_SCHEMA_VERSION = 1;

	private function __construct(
		private readonly string $id,
		private readonly string $campaign_id,
		private readonly int $revision,
		private readonly Review_Input $review_input,
		private readonly Compiled_Artifact $artifact,
		private readonly string $created_at,
		private readonly ?Campaign_Envelope $envelope
	) {}

	/** @param array<string, mixed> $data Persisted values. */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys(
			$data,
			array( 'schema_version', 'id', 'campaign_id', 'revision', 'review_input', 'artifact', 'created_at', 'envelope' )
		);
		$version  = $data['schema_version'] ?? null;
		$envelope = $data['envelope'] ?? null;
		if ( self::SCHEMA_VERSION === $version ) {
			if ( ! is_array( $envelope ) ) {
				throw new \InvalidArgumentException( 'Campaign snapshot envelope is missing.' );
			}
			$envelope = Campaign_Envelope::from_array( $envelope );
		} elseif ( self::LEGACY_SCHEMA_VERSION !== $version || null !== $envelope ) {
			throw new \InvalidArgumentException( 'Unsupported campaign-snapshot schema version.' );
		}
		$revision    = $data['revision'] ?? null;
		$review_data = $data['review_input'] ?? null;
		$artifact    = $data['artifact'] ?? null;
		if ( ! is_int( $revision ) || 1 > $revision || ! is_array( $review_data ) || ! is_array( $artifact ) ) {
			throw new \InvalidArgumentException( 'Campaign snapshot is malformed.' );
		}

		$review = Review_Input::from_array( $review_data );
		$result = Compiled_Artifact::from_array( $artifact );
		if ( $review->revision() !== $revision ) {
			throw new \InvalidArgumentException( 'Campaign snapshot revision does not match its frozen review input.' );
		}
		if ( $review->compiler_version() !== $result->compiler_version() || $review->context()->profile() !== $result->profile_version() ) {
			throw new \InvalidArgumentException( 'Campaign snapshot compiler/profile versions are inconsistent.' );
		}

		return new self(
			Record_Validation::identifier( $data['id'] ?? null, 'Snapshot ID' ),
			Record_Validation::identifier( $data['campaign_id'] ?? null, 'Campaign ID' ),
			$revision,
			$review,
			$result,
			Record_Validation::timestamp( $data['created_at'] ?? null, 'Snapshot timestamp' ),
			$envelope
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function campaign_id(): string {
		return $this->campaign_id;
	}

	public function revision(): int {
		return $this->revision;
	}

	public function review_input(): Review_Input {
		return $this->review_input;
	}

	public function artifact(): Compiled_Artifact {
		return $this->artifact;
	}

	public function created_at(): string {
		return $this->created_at;
	}

	/** The reviewed envelope, or null for a pre-envelope snapshot. */
	public function envelope(): ?Campaign_Envelope {
		return $this->envelope;
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		$data = array(
			'schema_version' => null === $this->envelope ? self::LEGACY_SCHEMA_VERSION : self::SCHEMA_VERSION,
			'id'             => $this->id,
			'campaign_id'    => $this->campaign_id,
			'revision'       => $this->revision,
			'review_input'   => $this->review_input->to_array(),
			'artifact'       => $this->artifact->to_array(),
			'created_at'     => $this->created_at,
		);
		if ( null !== $this->envelope ) {
			$data['envelope'] = $this->envelope->to_array();
		}

		return $data;
	}
}
