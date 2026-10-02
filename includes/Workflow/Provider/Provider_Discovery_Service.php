<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Public operation names and typed signatures form the application contract.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Caller contract violations fail closed.
/**
 * Provider reference discovery with explicit refresh.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Provider\Discovery_Values;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Discovery;
use CampaignBridge\Domain\Provider\Provider_Discovery_Source;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the cache and refresh contract for discovered provider references.
 *
 * `cached()` never contacts the provider, so listing references can never
 * become an implicit remote call. Only `refresh()` contacts the provider.
 * A result older than FRESH_SECONDS is still returned but marked stale.
 */
final class Provider_Discovery_Service {
	/** How long a discovered list is treated as current, in seconds. */
	public const FRESH_SECONDS = 900;

	public function __construct(
		private readonly Provider_Discovery $provider,
		private readonly Provider_Discovery_Source $cache,
		private readonly Campaign_Clock $clock
	) {}

	public function capabilities(): Provider_Capabilities {
		return $this->provider->capabilities();
	}

	/**
	 * Read the cached list without contacting the provider.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function cached( string $kind, array $settings, string $scope = '' ): Discovery_Lookup {
		$this->assert_request( $kind, $scope );
		if ( ! $this->capabilities()->supports( Discovery_Kind::operation( $kind ) ) ) {
			return Discovery_Lookup::unsupported();
		}
		$account = $this->provider->account_key( $settings );
		if ( null === $account ) {
			return Discovery_Lookup::failed( $this->not_configured() );
		}

		$result = $this->cache->get( $account, $this->provider->slug(), $kind, $scope );

		return null === $result ? Discovery_Lookup::miss() : Discovery_Lookup::cached( $result, $this->is_stale( $result ) );
	}

	/**
	 * Fetch the list from the provider and replace the cached copy.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	public function refresh( string $kind, array $settings, string $scope = '' ): Discovery_Lookup {
		$this->assert_request( $kind, $scope );
		if ( ! $this->capabilities()->supports( Discovery_Kind::operation( $kind ) ) ) {
			return Discovery_Lookup::unsupported();
		}
		$account = $this->provider->account_key( $settings );
		if ( null === $account ) {
			return Discovery_Lookup::failed( $this->not_configured() );
		}

		$previous = $this->cache->get( $account, $this->provider->slug(), $kind, $scope );
		$batch    = match ( $kind ) {
			Discovery_Kind::AUDIENCES    => $this->provider->discover_audiences( $settings ),
			Discovery_Kind::MERGE_FIELDS => $this->provider->discover_merge_fields( $settings, $scope ),
			default                      => $this->provider->discover_segments( $settings, $scope ),
		};
		if ( $batch instanceof Provider_Error ) {
			return Discovery_Lookup::failed( $batch, $previous );
		}

		$result = Discovery_Result::create( $this->provider->slug(), $scope, $batch, $this->clock->now() );
		// A cache write failure is not a discovery failure; the next read is a miss.
		$this->cache->save( $account, $result );

		return Discovery_Lookup::fresh( $result );
	}

	private function assert_request( string $kind, string $scope ): void {
		if ( ! Discovery_Kind::is_valid( $kind ) ) {
			throw new \InvalidArgumentException( 'Unknown discovery kind.' );
		}
		if ( Discovery_Kind::is_audience_scoped( $kind ) === ( '' === Discovery_Values::scope( $scope ) ) ) {
			throw new \InvalidArgumentException( 'Discovery scope does not match its kind.' );
		}
	}

	private function is_stale( Discovery_Result $result ): bool {
		$fetched = strtotime( $result->fetched_at() );
		$now     = strtotime( $this->clock->now() );

		return false === $fetched || false === $now || self::FRESH_SECONDS <= $now - $fetched;
	}

	private function not_configured(): Provider_Error {
		return Provider_Error::from_category(
			Provider_Error_Category::VALIDATION,
			$this->provider->slug() . '_not_configured',
			'The provider connection is not configured.',
			$this->provider->slug()
		);
	}
}
