<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Campaign collection filter validation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign_List_Filter;
use PHPUnit\Framework\TestCase;

/** Proves the filter accepts only known states and identifier-shaped providers. */
final class Campaign_List_Filter_Test extends TestCase {
	public function test_any_matches_every_state_and_provider(): void {
		self::assertSame( array(), Campaign_List_Filter::any()->states() );
		self::assertNull( Campaign_List_Filter::any()->provider() );
	}

	public function test_states_are_deduplicated(): void {
		self::assertSame( array( 'draft', 'sent' ), ( new Campaign_List_Filter( array( 'draft', 'sent', 'draft' ) ) )->states() );
	}

	/**
	 * @dataProvider invalid_filters
	 * @param array<int, string> $states   States.
	 * @param string|null        $provider Provider.
	 */
	public function test_unknown_states_and_malformed_providers_are_refused( array $states, ?string $provider ): void {
		$this->expectException( \InvalidArgumentException::class );
		new Campaign_List_Filter( $states, $provider );
	}

	/** @return array<string, array{0: array<int, string>, 1: string|null}> */
	public static function invalid_filters(): array {
		return array(
			'unknown state'      => array( array( 'shipped' ), null ),
			'keyed states'       => array( array( 'a' => 'draft' ), null ),
			'provider with sql'  => array( array(), "x' OR 1=1" ),
			'uppercase provider' => array( array(), 'Mailchimp' ),
		);
	}
}
