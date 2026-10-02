<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed ports and values use explicit signatures and class-level documentation.
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

	private function __construct(
		private readonly ?Discovery_Result $result,
		private readonly string $source,
		private readonly bool $stale,
		private readonly bool $supported,
		private readonly ?Provider_Error $error
	) {}

	public static function fresh( Discovery_Result $result ): self {
		return new self( $result, self::SOURCE_REMOTE, false, true, null );
	}

	public static function cached( Discovery_Result $result, bool $stale ): self {
		return new self( $result, self::SOURCE_CACHE, $stale, true, null );
	}

	public static function miss(): self {
		return new self( null, self::SOURCE_NONE, false, true, null );
	}

	public static function failed( Provider_Error $error, ?Discovery_Result $previous = null ): self {
		return new self( $previous, null === $previous ? self::SOURCE_NONE : self::SOURCE_CACHE, null !== $previous, true, $error );
	}

	public static function unsupported(): self {
		return new self( null, self::SOURCE_NONE, false, false, null );
	}

	public function result(): ?Discovery_Result {
		return $this->result;
	}

	public function source(): string {
		return $this->source;
	}

	public function is_stale(): bool {
		return $this->stale;
	}

	public function is_supported(): bool {
		return $this->supported;
	}

	public function error(): ?Provider_Error {
		return $this->error;
	}
}
