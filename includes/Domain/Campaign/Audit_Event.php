<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable persistence values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Append-only audit event.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable normalized audit record. */
final class Audit_Event {
	public const SCHEMA_VERSION = 1;
	private const RESULTS       = array( 'success', 'failure', 'denied', 'unknown' );

	private function __construct(
		private readonly string $id,
		private readonly ?int $actor_user_id,
		private readonly string $action,
		private readonly string $target_type,
		private readonly string $target_id,
		private readonly string $result,
		private readonly Audit_Context $context,
		private readonly string $created_at
	) {}

	/** @param array<string, mixed> $data Persisted values. */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys(
			$data,
			array( 'schema_version', 'id', 'actor_user_id', 'action', 'target_type', 'target_id', 'result', 'context', 'created_at' )
		);
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported audit-event schema version.' );
		}

		$actor = $data['actor_user_id'] ?? null;
		if ( null !== $actor && ( ! is_int( $actor ) || 1 > $actor ) ) {
			throw new \InvalidArgumentException( 'Audit actor must be a positive user ID or null.' );
		}
		$result = $data['result'] ?? null;
		if ( ! is_string( $result ) || ! in_array( $result, self::RESULTS, true ) ) {
			throw new \InvalidArgumentException( 'Audit result is invalid.' );
		}
		$context = $data['context'] ?? null;
		if ( ! is_array( $context ) ) {
			throw new \InvalidArgumentException( 'Audit context must be an object.' );
		}

		return new self(
			Record_Validation::identifier( $data['id'] ?? null, 'Audit event ID' ),
			$actor,
			Record_Validation::identifier( $data['action'] ?? null, 'Audit action' ),
			Record_Validation::identifier( $data['target_type'] ?? null, 'Audit target type' ),
			Record_Validation::identifier( $data['target_id'] ?? null, 'Audit target ID' ),
			$result,
			Audit_Context::from_array( $context ),
			Record_Validation::timestamp( $data['created_at'] ?? null, 'Audit timestamp' )
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function actor_user_id(): ?int {
		return $this->actor_user_id;
	}

	public function action(): string {
		return $this->action;
	}

	public function target_type(): string {
		return $this->target_type;
	}

	public function target_id(): string {
		return $this->target_id;
	}

	public function result(): string {
		return $this->result;
	}

	public function context(): Audit_Context {
		return $this->context;
	}

	public function created_at(): string {
		return $this->created_at;
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'id'             => $this->id,
			'actor_user_id'  => $this->actor_user_id,
			'action'         => $this->action,
			'target_type'    => $this->target_type,
			'target_id'      => $this->target_id,
			'result'         => $this->result,
			'context'        => $this->context->to_array(),
			'created_at'     => $this->created_at,
		);
	}
}
