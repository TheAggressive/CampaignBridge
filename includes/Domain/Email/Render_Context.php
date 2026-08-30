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
	 * @param array<string, mixed>                               $metadata  Email metadata and design tokens.
	 * @param array<string, array<string, array<string, mixed>>> $snapshots Immutable content snapshots.
	 * @param array<string, array<string, mixed>>                $bindings  Active parent bindings.
	 * @param string                                             $profile   Versioned target profile.
	 */
	public function __construct(
		private readonly array $metadata = array(),
		private readonly array $snapshots = array(),
		private readonly array $bindings = array(),
		private readonly string $profile = 'universal@1'
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
	 * Get one immutable snapshot record.
	 *
	 * @param string $collection Snapshot collection.
	 * @param string $id         Stable record identifier.
	 * @return array<string, mixed>|null
	 */
	public function snapshot( string $collection, string $id ): ?array {
		$value = $this->snapshots[ $collection ][ $id ] ?? null;

		return is_array( $value ) ? $value : null;
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

	/**
	 * Return a context copy with an active binding.
	 *
	 * @param string               $name  Binding name.
	 * @param array<string, mixed> $value Binding value.
	 */
	public function with_binding( string $name, array $value ): self {
		$bindings          = $this->bindings;
		$bindings[ $name ] = $value;

		return new self( $this->metadata, $this->snapshots, $bindings, $this->profile );
	}

	/**
	 * Get deterministic fingerprint data.
	 *
	 * @return array<string, mixed>
	 */
	public function fingerprint_payload(): array {
		return array(
			'metadata'  => $this->metadata,
			'snapshots' => $this->snapshots,
			'profile'   => $this->profile,
		);
	}
}
