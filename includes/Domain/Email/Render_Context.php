<?php
/**
 * Immutable email render context.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries metadata, immutable snapshots, and scoped parent bindings. */
final class Render_Context {
	/**
	 * Create a render context.
	 *
	 * @param array<string, mixed>                                                 $metadata  Email metadata and design tokens.
	 * @param array<string, array<int|string, Post_Snapshot|array<string, mixed>>> $snapshots Immutable content snapshots.
	 *        Record keys are post ids, which PHP stores as int when numeric; the
	 *        string lookup in snapshot() resolves through the same coercion.
	 * @param array<string, array<string, mixed>>                                  $bindings  Active parent bindings.
	 * @param string                                                               $profile   Versioned target profile.
	 * @param Post_Snapshot|null                                                   $post_binding Active scoped post snapshot.
	 */
	public function __construct(
		private readonly array $metadata = array(),
		private readonly array $snapshots = array(),
		private readonly array $bindings = array(),
		private readonly string $profile = 'universal@1',
		private readonly ?Post_Snapshot $post_binding = null
	) {}

	/** Get the versioned target profile. */
	public function profile(): string {
		return $this->profile;
	}

	/**
	 * Get one metadata value.
	 *
	 * @param string $key      Metadata key.
	 * @param mixed  $fallback Value returned when absent.
	 * @return mixed
	 */
	public function metadata( string $key, mixed $fallback = null ): mixed {
		return $this->metadata[ $key ] ?? $fallback;
	}

	/**
	 * Get a snapshot values view for existing renderers.
	 *
	 * Post values are derived on demand from the canonical object. This is the
	 * temporary compatibility boundary until post renderers use post_snapshot().
	 *
	 * @param string $collection Snapshot collection.
	 * @param string $id         Stable record identifier.
	 * @return array<string, mixed>|null
	 */
	public function snapshot( string $collection, string $id ): ?array {
		$value = $this->snapshots[ $collection ][ $id ] ?? null;

		if ( $value instanceof Post_Snapshot ) {
			return $value->to_array()['values'];
		}

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Get the canonical post snapshot without converting or resolving it.
	 *
	 * @param string $id Post identifier.
	 */
	public function post_snapshot( string $id ): ?Post_Snapshot {
		$value = $this->snapshots['posts'][ $id ] ?? null;

		return $value instanceof Post_Snapshot ? $value : null;
	}

	/**
	 * Get an active parent binding.
	 *
	 * @param string $name Binding name.
	 * @return array<string, mixed>|null
	 */
	public function binding( string $name ): ?array {
		return $this->bindings[ $name ] ?? null;
	}

	/** Get the active canonical post snapshot, if scoped. */
	public function post_binding(): ?Post_Snapshot {
		return $this->post_binding;
	}

	/**
	 * Return a context copy with the active post snapshot set or cleared.
	 *
	 * This keeps the same object as derived render state, outside fingerprint input.
	 *
	 * @param Post_Snapshot|null $snapshot Active snapshot, or null to clear the scope.
	 */
	public function with_post_binding( ?Post_Snapshot $snapshot ): self {
		return new self( $this->metadata, $this->snapshots, $this->bindings, $this->profile, $snapshot );
	}

	/**
	 * Return a context copy with a metadata value set.
	 *
	 * @param string $key   Metadata key.
	 * @param mixed  $value Value to store.
	 */
	public function with_metadata( string $key, mixed $value ): self {
		$metadata         = $this->metadata;
		$metadata[ $key ] = $value;

		return new self( $metadata, $this->snapshots, $this->bindings, $this->profile, $this->post_binding );
	}

	/**
	 * Return a context copy with an active binding.
	 *
	 * @param string               $name  Binding name.
	 * @param array<string, mixed> $value Binding value.
	 */
	public function with_binding( string $name, array $value ): self {
		$bindings          = $this->bindings;
		$bindings[ $name ] = $value;

		return new self( $this->metadata, $this->snapshots, $bindings, $this->profile, $this->post_binding );
	}

	/**
	 * Get deterministic fingerprint data.
	 *
	 * @return array<string, mixed>
	 */
	public function fingerprint_payload(): array {
		$snapshots = $this->snapshots;
		foreach ( $snapshots as $collection => $records ) {
			foreach ( $records as $id => $snapshot ) {
				if ( $snapshot instanceof Post_Snapshot ) {
					$snapshots[ $collection ][ $id ] = $snapshot->fingerprint_payload();
				}
			}
		}

		// Post IDs form a map, independent of the source's first-seen order.
		if ( isset( $snapshots['posts'] ) ) {
			ksort( $snapshots['posts'], SORT_STRING );
		}

		return array(
			'metadata'  => $this->metadata,
			'snapshots' => $snapshots,
			'profile'   => $this->profile,
		);
	}
}
