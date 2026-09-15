<?php
/**
 * Versioned registry of all valid email tokens.
 *
 * @package CampaignBridge
 * @since   1.0.0
 */

namespace CampaignBridge\Domain\Email\Token;

/**
 * Immutable, ordered registry of token definitions.
 *
 * Provides lookup-by-ID, duplicate rejection at construction, and
 * version identification. The registry is the single source of truth
 * for which tokens are valid in a given CampaignBridge version.
 */
final class Token_Registry {

	/**
	 * Registry schema version.
	 *
	 * @var int
	 */
	private int $version;

	/**
	 * Ordered list of token definitions.
	 *
	 * @var Token_Definition[]
	 */
	private array $definitions;

	/**
	 * Index of token ID => definition for O(1) lookup.
	 *
	 * @var array<string, Token_Definition>
	 */
	private array $index;

	/**
	 * Construct the registry.
	 *
	 * @param int                $version     Schema version.
	 * @param Token_Definition[] $definitions Ordered token definitions.
	 *
	 * @throws \InvalidArgumentException When a duplicate ID is detected.
	 */
	private function __construct( int $version, array $definitions ) {
		$this->version     = $version;
		$this->definitions = array_values( $definitions );
		$this->index       = array();

		foreach ( $this->definitions as $definition ) {
			$id = $definition->get_id();

			if ( isset( $this->index[ $id ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Duplicate token ID: %s', $id ) );
			}

			$this->index[ $id ] = $definition;
		}
	}

	/**
	 * Create a new registry.
	 *
	 * @param int                $version     Schema version (must be positive).
	 * @param Token_Definition[] $definitions Ordered token definitions.
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException When version is invalid or IDs duplicate.
	 */
	public static function create( int $version, array $definitions ): self {
		if ( $version < 1 ) {
			throw new \InvalidArgumentException( 'Registry version must be a positive integer.' );
		}

		return new self( $version, $definitions );
	}

	/**
	 * Look up a token by its canonical ID.
	 *
	 * @param string $id Canonical token ID.
	 *
	 * @return Token_Definition|null
	 */
	public function get( string $id ): ?Token_Definition {
		return $this->index[ $id ] ?? null;
	}

	/**
	 * Check whether a token ID is registered.
	 *
	 * @param string $id Canonical token ID.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->index[ $id ] );
	}

	/**
	 * Get all registered token definitions in registration order.
	 *
	 * @return Token_Definition[]
	 */
	public function all(): array {
		return $this->definitions;
	}

	/**
	 * Get the count of registered tokens.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->definitions );
	}

	/**
	 * Get the registry schema version.
	 *
	 * @return int
	 */
	public function get_version(): int {
		return $this->version;
	}

	/**
	 * Get all registered token IDs in registration order.
	 *
	 * @return string[]
	 */
	public function get_ids(): array {
		return array_map(
			static function ( Token_Definition $d ): string {
				return $d->get_id();
			},
			$this->definitions
		);
	}

	/**
	 * Create the default v1 registry with all canonical tokens.
	 *
	 * This factory is deterministic: two calls always produce registries
	 * with identical token sets and ordering.
	 *
	 * @return static
	 */
	public static function default(): self {
		return self::create(
			1,
			array(
				Token_Definition::create(
					'cb:subscriber.first_name',
					'First Name',
					Token_Definition::CATEGORY_SUBSCRIBER,
					Token_Definition::VALUE_TYPE_STRING,
					true,
					Token_Definition::PREVIEW_SAMPLE,
					true,
					false
				),
				Token_Definition::create(
					'cb:subscriber.last_name',
					'Last Name',
					Token_Definition::CATEGORY_SUBSCRIBER,
					Token_Definition::VALUE_TYPE_STRING,
					true,
					Token_Definition::PREVIEW_SAMPLE,
					true,
					false
				),
				Token_Definition::create(
					'cb:subscriber.email',
					'Email Address',
					Token_Definition::CATEGORY_SUBSCRIBER,
					Token_Definition::VALUE_TYPE_STRING,
					true,
					Token_Definition::PREVIEW_OMIT,
					true,
					true
				),
				Token_Definition::create(
					'cb:campaign.view_online_url',
					'View Online URL',
					Token_Definition::CATEGORY_CAMPAIGN,
					Token_Definition::VALUE_TYPE_URL,
					true,
					Token_Definition::PREVIEW_SAMPLE,
					false,
					false
				),
				Token_Definition::create(
					'cb:campaign.unsubscribe_url',
					'Unsubscribe URL',
					Token_Definition::CATEGORY_CAMPAIGN,
					Token_Definition::VALUE_TYPE_URL,
					true,
					Token_Definition::PREVIEW_SAMPLE,
					false,
					false
				),
				Token_Definition::create(
					'cb:organization.name',
					'Organization Name',
					Token_Definition::CATEGORY_ORGANIZATION,
					Token_Definition::VALUE_TYPE_STRING,
					true,
					Token_Definition::PREVIEW_SAMPLE,
					false,
					false
				),
				Token_Definition::create(
					'cb:organization.address',
					'Organization Address',
					Token_Definition::CATEGORY_ORGANIZATION,
					Token_Definition::VALUE_TYPE_STRING,
					true,
					Token_Definition::PREVIEW_SAMPLE,
					false,
					false
				),
			)
		);
	}
}
