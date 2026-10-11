<?php
/**
 * Timestamped discovery result.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

use CampaignBridge\Domain\Campaign\Record_Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A provider's normalized reference list at one point in time.
 *
 * This is the only discovery shape that is cached or exposed. It holds DTOs
 * and their provenance (provider, kind, audience scope, fetch time), never a
 * raw provider payload or credential.
 */
final class Discovery_Result {
	public const SCHEMA_VERSION = 1;

	/**
	 * Build the discovery result.
	 *
	 * @param string          $provider   Provider slug.
	 * @param string          $scope      Discovery scope: empty, or an audience ID.
	 * @param Discovery_Batch $batch      Discovered items of one kind.
	 * @param string          $fetched_at UTC time the list was fetched.
	 */
	private function __construct(
		private readonly string $provider,
		private readonly string $scope,
		private readonly Discovery_Batch $batch,
		private readonly string $fetched_at
	) {}

	/**
	 * A discovered list for one provider and scope.
	 *
	 * @param string          $provider   Provider slug.
	 * @param string          $scope      Discovery scope: empty, or an audience ID.
	 * @param Discovery_Batch $batch      Discovered items of one kind.
	 * @param string          $fetched_at UTC time the list was fetched.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function create( string $provider, string $scope, Discovery_Batch $batch, string $fetched_at ): self {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $provider ) ) {
			throw new \InvalidArgumentException( 'Provider slug is invalid.' );
		}
		$scope = Discovery_Values::scope( $scope );
		if ( Discovery_Kind::is_audience_scoped( $batch->kind() ) === ( '' === $scope ) ) {
			throw new \InvalidArgumentException( 'Discovery scope does not match its kind.' );
		}

		return new self( $provider, $scope, $batch, Record_Validation::timestamp( $fetched_at, 'Discovery time' ) );
	}

	/**
	 * Rebuild a discovered list from its cached values.
	 *
	 * @param array<string, mixed> $data Stored result.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_array( array $data ): self {
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported discovery schema version.' );
		}
		$kind  = $data['kind'] ?? null;
		$items = $data['items'] ?? null;
		if ( ! is_string( $kind ) || ! Discovery_Kind::is_valid( $kind ) || ! is_array( $items ) || ! is_bool( $data['complete'] ?? null ) || ! is_string( $data['scope'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Stored discovery is malformed.' );
		}

		$hydrated = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				throw new \InvalidArgumentException( 'Stored discovery item is malformed.' );
			}
			$hydrated[] = Discovery_Kind::item( $kind, $item );
		}

		return self::create(
			is_string( $data['provider'] ?? null ) ? $data['provider'] : '',
			$data['scope'],
			Discovery_Batch::create( $kind, $hydrated, $data['complete'] ),
			is_string( $data['fetched_at'] ?? null ) ? $data['fetched_at'] : ''
		);
	}

	/**
	 * The result's provider.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * What the list contains.
	 */
	public function kind(): string {
		return $this->batch->kind();
	}

	/** Audience ID for audience-scoped kinds, '' for account-wide kinds. */
	public function scope(): string {
		return $this->scope;
	}

	/**
	 * The discovered items.
	 *
	 * @return array<int, Discovered_Item>
	 */
	public function items(): array {
		return $this->batch->items();
	}

	/**
	 * Whether the provider returned every item.
	 */
	public function is_complete(): bool {
		return $this->batch->is_complete();
	}

	/**
	 * The result's fetched at.
	 */
	public function fetched_at(): string {
		return $this->fetched_at;
	}

	/**
	 * The list's cached values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'provider'       => $this->provider,
			'kind'           => $this->kind(),
			'scope'          => $this->scope,
			'items'          => array_map( static fn ( Discovered_Item $item ): array => $item->to_array(), $this->items() ),
			'complete'       => $this->is_complete(),
			'fetched_at'     => $this->fetched_at,
		);
	}
}
