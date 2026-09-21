<?php
/**
 * Representative email-client compatibility fixtures.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Tests\Support\Email\Client_Expectations;
use CampaignBridge\Tests\Support\Email\Compatibility_Scenarios;
use PHPUnit\Framework\TestCase;

/**
 * Proves the universal artifact still carries what each target client needs.
 *
 * This suite is regression evidence, not a rendering claim. It compiles a
 * representative scenario matrix through the production compiler and checks the
 * structural properties Outlook's Word engine, Gmail's sanitizer, and Apple
 * Mail's WebKit build are known to depend on. What the fixtures deliberately do
 * not prove is declared as limitations, and those declarations are checked too
 * so they cannot quietly go stale.
 */
final class Client_Compatibility_Test extends TestCase {
	/** Every representative document compiles into both delivery formats. */
	public function test_every_scenario_compiles_for_the_universal_profile(): void {
		foreach ( Compatibility_Scenarios::names() as $scenario ) {
			$artifact = Compatibility_Scenarios::compile( $scenario );

			self::assertSame( array(), $artifact['diagnostics'], $scenario . ' should compile without diagnostics.' );
			self::assertNotSame( '', $artifact['html'], $scenario . ' should produce HTML.' );
			self::assertNotSame( '', trim( $artifact['text'] ), $scenario . ' should produce plain text.' );
		}
	}

	/** The matrix keeps its named scenarios and client profiles. */
	public function test_the_matrix_covers_every_declared_client(): void {
		self::assertSame(
			array( 'apple-mail', 'gmail', 'outlook-word', 'universal-profile' ),
			Client_Expectations::clients()
		);
		self::assertSame(
			array( 'universal-newsletter', 'stacked-columns', 'branded-typography' ),
			Compatibility_Scenarios::names()
		);
	}

	/** Fixtures declare supported rules, reasons, and valid scenario scopes. */
	public function test_client_fixtures_use_only_the_declared_rule_vocabulary(): void {
		$scenarios = Compatibility_Scenarios::names();

		foreach ( Client_Expectations::clients() as $client ) {
			$profile = Client_Expectations::load( $client );
			$ids     = array();
			self::assertNotSame( '', $profile->label(), $client . ' needs a client label.' );
			self::assertNotSame( '', $profile->proves(), $client . ' needs an explicit claim.' );
			self::assertNotSame( array(), $profile->all_expectations(), $client . ' declares no expectations.' );

			foreach ( $profile->all_expectations() as $expectation ) {
				$id = $client . '/' . ( $expectation['id'] ?? '?' );
				self::assertNotSame( '?', $expectation['id'] ?? '?', $client . ' needs an expectation ID.' );
				self::assertNotContains( $id, $ids, $id . ' is duplicated.' );
				$ids[] = $id;
				self::assertContains( $expectation['rule'] ?? '', Client_Expectations::RULES, $id . ' uses an unsupported rule.' );
				self::assertNotSame( '', (string) ( $expectation['because'] ?? '' ), $id . ' needs a stated reason.' );

				foreach ( (array) ( $expectation['scenarios'] ?? array() ) as $named ) {
					self::assertContains( $named, $scenarios, $id . ' names an unknown scenario.' );
				}
			}
		}
	}

	/** Compiled documents satisfy every applicable client constraint. */
	public function test_compiled_artifacts_satisfy_every_client_expectation(): void {
		$failures = array();

		foreach ( Compatibility_Scenarios::names() as $scenario ) {
			$html = Compatibility_Scenarios::compile( $scenario )['html'];

			foreach ( Client_Expectations::clients() as $client ) {
				$failures = array_merge( $failures, Client_Expectations::load( $client )->evaluate( $scenario, $html ) );
			}
		}

		self::assertSame( array(), $failures, "Compiled output no longer satisfies:\n" . implode( "\n", $failures ) );
	}

	/** A limitation probe must continue to describe actual compiler output. */
	public function test_declared_limitations_are_still_real(): void {
		$stale = array();

		foreach ( Client_Expectations::clients() as $client ) {
			$profile = Client_Expectations::load( $client );

			foreach ( $profile->limitations() as $limitation ) {
				$id = $client . '/' . ( $limitation['id'] ?? '?' );
				self::assertNotSame( '', (string) ( $limitation['detail'] ?? '' ), $id . ' needs a detail.' );
				self::assertNotSame( '', (string) ( $limitation['degrades_to'] ?? '' ), $id . ' needs a degradation statement.' );

				$probe = $limitation['detect'] ?? null;
				if ( ! is_array( $probe ) ) {
					continue;
				}

				foreach ( (array) ( $probe['scenarios'] ?? Compatibility_Scenarios::names() ) as $scenario ) {
					$html   = Compatibility_Scenarios::compile( (string) $scenario )['html'];
					$reason = Client_Expectations::check( $probe, $html, Client_Expectations::parse( $html ) );
					if ( null !== $reason ) {
						$stale[] = $id . ' no longer applies to ' . $scenario . ': ' . $reason;
					}
				}
			}
		}

		self::assertSame(
			array(),
			$stale,
			"A declared limitation no longer matches the compiler output. Remove or restate it:\n" . implode( "\n", $stale )
		);
	}

	/** Empty required matches and malformed rules must never pass. */
	public function test_required_rules_fail_closed(): void {
		$html = '<html><body><p>Hello</p></body></html>';
		$dom  = Client_Expectations::parse( $html );

		self::assertNotNull(
			Client_Expectations::check(
				array(
					'rule'     => 'markup_count_equals',
					'contains' => '<v:roundrect',
					'matches'  => '<w:anchorlock/>',
				),
				$html,
				$dom
			)
		);

		foreach (
			array(
				array(
					'rule'   => 'element_required',
					'select' => 'img',
					'min'    => 0,
				),
				array(
					'rule'     => 'markup_required',
					'contains' => '',
				),
				array(
					'rule'      => 'attribute_required',
					'select'    => '',
					'attribute' => 'href',
					'min'       => 0,
				),
			) as $rule
		) {
			try {
				Client_Expectations::check( $rule, $html, $dom );
				self::fail( 'Malformed compatibility rule did not fail.' );
			} catch ( \RuntimeException $error ) {
				self::assertStringContainsString( 'Compatibility rule', $error->getMessage() );
			}
		}
	}

	/**
	 * A regression in one compatibility-critical behavior must fail the suite.
	 *
	 * Each case removes exactly one property a named client depends on and
	 * asserts that the owning profile reports it. This proves the expectations
	 * have teeth without waiting for a real compiler regression.
	 *
	 * @dataProvider deliberate_regressions
	 *
	 * @param string $client      Profile that must catch the regression.
	 * @param string $scenario    Scenario to mutate.
	 * @param string $expectation Expectation that must fail.
	 * @param string $search      Markup to replace.
	 * @param string $replace     Replacement markup.
	 */
	public function test_a_deliberate_regression_fails_its_client_profile(
		string $client,
		string $scenario,
		string $expectation,
		string $search,
		string $replace
	): void {
		$html    = Compatibility_Scenarios::compile( $scenario )['html'];
		$broken  = str_replace( $search, $replace, $html );
		$profile = Client_Expectations::load( $client );

		self::assertNotSame( $html, $broken, 'The regression fixture no longer matches compiled output.' );
		self::assertSame( array(), $profile->evaluate( $scenario, $html ), 'Unmodified output should satisfy ' . $client . '.' );

		$failures = $profile->evaluate( $scenario, $broken );
		self::assertNotSame( array(), $failures, $client . ' did not notice the regression.' );
		self::assertStringContainsString( $expectation, implode( "\n", $failures ) );
	}

	/**
	 * One-property regressions per client profile.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function deliberate_regressions(): array {
		return array(
			'Outlook loses the VML button fallback'     => array(
				'outlook-word',
				'universal-newsletter',
				'buttons-have-a-vml-fallback',
				'<v:roundrect',
				'<span data-roundrect',
			),
			'Outlook loses a layout table reset'        => array(
				'outlook-word',
				'universal-newsletter',
				'layout-tables-reset-cell-spacing',
				'class="cb-email-container" width="600" cellpadding="0" cellspacing="0"',
				'class="cb-email-container" width="600" cellpadding="0"',
			),
			'Outlook loses the fixed column width'      => array(
				'outlook-word',
				'stacked-columns',
				'column-widths-are-html-attributes',
				'<td class="cb-col" valign="top" width="33.3333%"',
				'<td class="cb-col" valign="top"',
			),
			'Gmail loses inline paragraph typography'   => array(
				'gmail',
				'universal-newsletter',
				'paragraphs-carry-inline-typography',
				'font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6',
				'font-size:16px;line-height:1.6',
			),
			'Apple Mail loses reformatting opt-out'     => array(
				'apple-mail',
				'universal-newsletter',
				'message-reformatting-is-disabled',
				'<meta name="x-apple-disable-message-reformatting">',
				'',
			),
			'The profile admits an active script'       => array(
				'universal-profile',
				'universal-newsletter',
				'no-script',
				'</body>',
				'<script>void 0;</script></body>',
			),
			'The profile admits a relative destination' => array(
				'universal-profile',
				'universal-newsletter',
				'links-are-absolute',
				'href="https://example.com/docs"',
				'href="/docs"',
			),
		);
	}
}
