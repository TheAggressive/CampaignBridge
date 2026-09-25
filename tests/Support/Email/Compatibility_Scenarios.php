<?php
/**
 * Representative compile scenarios for the email-client compatibility matrix.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Support\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;

/**
 * Loads and compiles the scenario documents the client fixtures assert against.
 *
 * Scenarios are inputs, not expected output. They compile through the ordinary
 * production compiler graph so a compatibility failure always reflects real
 * artifact markup rather than a test-only rendering path.
 */
final class Compatibility_Scenarios {
	/**
	 * Decoded scenario definitions.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $scenarios = null;

	/**
	 * Every scenario definition, keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null === self::$scenarios ) {
			/**
			 * Scenario definitions.
			 *
			 * @var array<string, array<string, mixed>> $scenarios
			 */
			$scenarios       = require dirname( __DIR__, 2 ) . '/Fixtures/Email/compatibility/scenarios.php';
			self::$scenarios = $scenarios;
		}

		return self::$scenarios;
	}

	/**
	 * Every scenario slug, in declaration order.
	 *
	 * @return array<int, string>
	 */
	public static function names(): array {
		return array_keys( self::all() );
	}

	/**
	 * Compile one scenario into its universal-profile HTML artifact.
	 *
	 * @param string $name Scenario slug.
	 * @return array{html: string, text: string, diagnostics: array<int, string>}
	 * @throws \RuntimeException When the scenario is unknown.
	 */
	public static function compile( string $name ): array {
		$scenario = self::all()[ $name ] ?? null;
		if ( null === $scenario ) {
			throw new \RuntimeException( 'Unknown compatibility scenario: ' . $name );
		}

		/**
		 * Render metadata.
		 *
		 * @var array<string, mixed> $metadata
		 */
		$metadata = $scenario['metadata'];
		$kit      = null;
		if ( isset( $scenario['brand_kit'] ) && is_array( $scenario['brand_kit'] ) ) {
			$kit                  = Brand_Kit::from_colors(
				is_array( $scenario['brand_kit']['colors'] ?? null ) ? $scenario['brand_kit']['colors'] : array(),
				Brand_Kit::SOURCE_CUSTOM,
				null,
				is_array( $scenario['brand_kit']['fonts'] ?? null ) ? $scenario['brand_kit']['fonts'] : array(),
				null,
				is_array( $scenario['brand_kit']['logo'] ?? null ) ? $scenario['brand_kit']['logo'] : null
			);
			$metadata['brandKit'] = $kit;
		}

		/**
		 * Parsed block document.
		 *
		 * @var array<int, array<string, mixed>> $blocks
		 */
		$blocks = $scenario['blocks'];
		$result = Compiler_Factory::create( Email_Design_Factory::resolve( $kit ) )->compile( $blocks, new Render_Context( $metadata ) );

		return array(
			'html'        => $result->html(),
			'text'        => $result->text(),
			'diagnostics' => array_map(
				static fn ( $diagnostic ): string => $diagnostic->code() . ' at ' . $diagnostic->path(),
				$result->diagnostics()
			),
		);
	}
}
