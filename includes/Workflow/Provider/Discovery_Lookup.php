<?php
/**
 * Outcome of a discovery read or refresh.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Provider\Discovery_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed outcome shared by future REST, admin, and Abilities adapters.
 *
 * A lookup carries the best available result, where it came from, whether it
 * is past its freshness window, and any normalized error. A failed refresh
 * keeps the previous cached result so operators are never left with nothing,
 * but it is always reported as stale.
 */
final class Discovery_Lookup {
	public const SOURCE_REMOTE = 'remote';
	public const SOURCE_CACHE  = 'cache';
	public const SOURCE_NONE   = 'none';

	/**
	 * Build the discovery lookup.
	 *
	 * @param Discovery_Result|null $result    The discovered list, when there is one.
	 * @param string                $source    The campaign being copied.
	 * @param bool                  $stale     Whether the cached list is older than its freshness window.
	 * @param bool                  $supported Whether the provider supports this list.
	 * @param Provider_Error|null   $error     Normalized provider error, when there is one.
	 */
	private function __construct(
		private readonly ?Discovery_Result $result,
		private readonly string $source,
		private readonly bool $stale,
		private readonly bool $supported,
		private readonly ?Provider_Error $error
	) {}

	/**
	 * A list just fetched from the provider.
	 *
	 * @param Discovery_Result $result The discovered list.
	 */
	public static function fresh( Discovery_Result $result ): self {
		return new self( $result, self::SOURCE_REMOTE, false, true, null );
	}

	/**
	 * A list read from the cache.
	 *
	 * @param Discovery_Result $result The discovered list.
	 * @param bool             $stale  Whether the cached list is older than its freshness window.
	 */
	public static function cached( Discovery_Result $result, bool $stale ): self {
		return new self( $result, self::SOURCE_CACHE, $stale, true, null );
	}

	/**
	 * Nothing cached yet.
	 */
	public static function miss(): self {
		return new self( null, self::SOURCE_NONE, false, true, null );
	}

	/**
	 * A failed refresh, with the previous cached list when there is one.
	 *
	 * @param Provider_Error        $error    Normalized provider error.
	 * @param Discovery_Result|null $previous The previous cached list, when there is one.
	 */
	public static function failed( Provider_Error $error, ?Discovery_Result $previous = null ): self {
		return new self( $previous, null === $previous ? self::SOURCE_NONE : self::SOURCE_CACHE, null !== $previous, true, $error );
	}

	/**
	 * The provider does not support this list.
	 */
	public static function unsupported(): self {
		return new self( null, self::SOURCE_NONE, false, false, null );
	}

	/**
	 * The lookup's result.
	 */
	public function result(): ?Discovery_Result {
		return $this->result;
	}

	/**
	 * The lookup's source.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Whether the lookup is stale.
	 */
	public function is_stale(): bool {
		return $this->stale;
	}

	/**
	 * Whether the lookup is supported.
	 */
	public function is_supported(): bool {
		return $this->supported;
	}

	/**
	 * The lookup's error.
	 */
	public function error(): ?Provider_Error {
		return $this->error;
	}
}
