<?php
/**
 * Bounded filter for one owner's campaign collection.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Narrows a campaign collection by lifecycle state and provider.
 *
 * An empty state list matches every state. The provider is a provider slug,
 * `NO_PROVIDER` for campaigns without one (HTML export), or null for any.
 */
final class Campaign_List_Filter {
	/** Provider filter value that matches campaigns with no provider. */
	public const NO_PROVIDER = 'none';

	/**
	 * Build a validated filter.
	 *
	 * @param array<int, string> $states   Known lifecycle states; empty for any.
	 * @param string|null        $provider Provider slug, NO_PROVIDER, or null for any.
	 * @throws \InvalidArgumentException When a state or provider is not recognized.
	 */
	public function __construct( private readonly array $states = array(), private readonly ?string $provider = null ) {
		if ( array() !== array_diff( $states, Campaign_State::all() ) || ! array_is_list( $states ) ) {
			throw new \InvalidArgumentException( 'Campaign filter state is not recognized.' );
		}
		if ( null !== $provider && 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $provider ) ) {
			throw new \InvalidArgumentException( 'Campaign filter provider is not an identifier.' );
		}
	}

	/** A filter that matches every campaign. */
	public static function any(): self {
		return new self();
	}

	/**
	 * States to match, deduplicated; empty matches every state.
	 *
	 * @return array<int, string>
	 */
	public function states(): array {
		return array_values( array_unique( $this->states ) );
	}

	/** Provider slug, NO_PROVIDER, or null for any provider. */
	public function provider(): ?string {
		return $this->provider;
	}
}
