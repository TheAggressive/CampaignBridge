<?php
/**
 * Structural email-client expectations for one target client profile.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Support\Email;

/**
 * Evaluates one client's structural expectations against a compiled artifact.
 *
 * CampaignBridge claims that its universal profile renders predictably in a
 * named set of email clients. This class turns that claim into repository-owned
 * regression evidence: each client fixture in
 * `tests/Fixtures/Email/compatibility/clients/` declares the structural
 * properties that client depends on, in a small typed rule vocabulary, and this
 * evaluator reports which ones a compiled document violates.
 *
 * It does not render in a real client and does not prove visual parity. It
 * proves that the markup properties those clients are known to require are
 * still present, so a compiler change cannot silently remove them. The
 * limitations each fixture declares record what the profile deliberately does
 * not deliver.
 */
final class Client_Expectations {
	/**
	 * The complete rule vocabulary a client fixture may use.
	 *
	 * Kept deliberately small: a client expectation is a structural assertion
	 * over parsed markup, not a scripting language.
	 *
	 * @var array<int, string>
	 */
	public const RULES = array(
		'element_required',
		'element_forbidden',
		'attribute_required',
		'attribute_forbidden',
		'attribute_name_forbidden',
		'style_property_required',
		'style_property_forbidden',
		'markup_required',
		'markup_forbidden',
		'markup_count_equals',
	);

	/**
	 * Create a profile from its decoded fixture.
	 *
	 * @param array<string, mixed> $document Decoded client fixture.
	 */
	private function __construct( private readonly array $document ) {}

	/**
	 * Load one client fixture by slug.
	 *
	 * @param string $client Client slug.
	 * @throws \RuntimeException When the fixture is missing or malformed.
	 */
	public static function load( string $client ): self {
		$path = self::directory() . '/' . $client . '.json';
		if ( ! is_file( $path ) ) {
			throw new \RuntimeException( 'Unknown email client fixture: ' . $client );
		}

		$document = json_decode( (string) file_get_contents( $path ), true, 16, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Local packaged fixture.
		if ( ! is_array( $document ) || ( $document['client'] ?? null ) !== $client ) {
			throw new \RuntimeException( 'Client fixture ' . $client . ' does not declare its own slug.' );
		}

		return new self( $document );
	}

	/**
	 * Every declared client slug, in stable order.
	 *
	 * @return array<int, string>
	 */
	public static function clients(): array {
		$files = glob( self::directory() . '/*.json' );
		$slugs = array_map( static fn ( string $file ): string => basename( $file, '.json' ), is_array( $files ) ? $files : array() );
		sort( $slugs, SORT_STRING );

		return $slugs;
	}

	/** The client slug. */
	public function client(): string {
		return (string) $this->document['client'];
	}

	/** The human-readable client and rendering engine. */
	public function label(): string {
		return (string) ( $this->document['label'] ?? $this->client() );
	}

	/** What this profile's expectations prove. */
	public function proves(): string {
		return (string) ( $this->document['proves'] ?? '' );
	}

	/**
	 * The expectations that apply to one scenario.
	 *
	 * An expectation without a `scenarios` list applies to every scenario.
	 *
	 * @param string $scenario Scenario slug.
	 * @return array<int, array<string, mixed>>
	 */
	public function expectations( string $scenario ): array {
		$applicable = array();
		foreach ( $this->all_expectations() as $expectation ) {
			$scenarios = $expectation['scenarios'] ?? null;
			if ( null === $scenarios || in_array( $scenario, (array) $scenarios, true ) ) {
				$applicable[] = $expectation;
			}
		}

		return $applicable;
	}

	/**
	 * Every expectation this profile declares.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_expectations(): array {
		$expectations = $this->document['expectations'] ?? array();

		return is_array( $expectations ) ? array_values( $expectations ) : array();
	}

	/**
	 * The deliberate degradations this profile accepts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function limitations(): array {
		$limitations = $this->document['limitations'] ?? array();

		return is_array( $limitations ) ? array_values( $limitations ) : array();
	}

	/**
	 * Evaluate every applicable expectation against one compiled document.
	 *
	 * @param string $scenario Scenario slug.
	 * @param string $html     Compiled email HTML.
	 * @return array<int, string> One message per violated expectation.
	 */
	public function evaluate( string $scenario, string $html ): array {
		$dom      = self::parse( $html );
		$failures = array();

		foreach ( $this->expectations( $scenario ) as $expectation ) {
			$reason = self::check( $expectation, $html, $dom );
			if ( null !== $reason ) {
				$failures[] = sprintf(
					'%s / %s / %s: %s (%s)',
					$this->client(),
					$scenario,
					(string) $expectation['id'],
					$reason,
					(string) ( $expectation['because'] ?? '' )
				);
			}
		}

		return $failures;
	}

	/**
	 * Apply one rule and describe the violation, or return null when it holds.
	 *
	 * @param array<string, mixed> $rule One expectation or limitation probe.
	 * @param string               $html Compiled email HTML.
	 * @param \Dom\HTMLDocument    $dom  Parsed document.
	 * @throws \RuntimeException When the rule kind is outside the vocabulary.
	 */
	public static function check( array $rule, string $html, \Dom\HTMLDocument $dom ): ?string {
		$kind = (string) ( $rule['rule'] ?? '' );
		if ( ! in_array( $kind, self::RULES, true ) ) {
			throw new \RuntimeException( 'Unsupported compatibility rule: ' . $kind );
		}
		self::validate_rule( $rule, $kind );

		$select   = (string) ( $rule['select'] ?? '' );
		$minimum  = (int) ( $rule['min'] ?? 1 );
		$elements = '' === $select ? array() : iterator_to_array( $dom->querySelectorAll( $select ) );

		return match ( $kind ) {
			'element_required'         => count( $elements ) >= $minimum
				? null
				: sprintf( 'expected at least %d "%s" element(s), found %d', $minimum, $select, count( $elements ) ),
			'element_forbidden'        => array() === $elements
				? null
				: sprintf( 'found %d forbidden "%s" element(s)', count( $elements ), $select ),
			'attribute_required'       => self::attribute_required( $rule, $elements, $select, $minimum ),
			'attribute_forbidden'      => self::attribute_forbidden( $rule, $elements, $select ),
			'attribute_name_forbidden' => self::attribute_name_forbidden( $rule, $elements, $select ),
			'style_property_required'  => self::style_property_required( $rule, $elements, $select, $minimum ),
			'style_property_forbidden' => self::style_property_forbidden( $rule, $elements, $select ),
			'markup_required'          => substr_count( $html, (string) $rule['contains'] ) >= $minimum
				? null
				: sprintf( 'expected markup %s at least %d time(s), found %d', wp_json_encode( $rule['contains'] ), $minimum, substr_count( $html, (string) $rule['contains'] ) ),
			'markup_forbidden'         => str_contains( $html, (string) $rule['contains'] )
				? sprintf( 'found forbidden markup %s', wp_json_encode( $rule['contains'] ) )
				: null,
			'markup_count_equals'      => substr_count( $html, (string) $rule['contains'] ) >= $minimum
				&& substr_count( $html, (string) $rule['contains'] ) === substr_count( $html, (string) $rule['matches'] )
				? null
				: sprintf(
					'markup %s occurs %d time(s) (minimum %d) but %s occurs %d time(s)',
					wp_json_encode( $rule['contains'] ),
					substr_count( $html, (string) $rule['contains'] ),
					$minimum,
					wp_json_encode( $rule['matches'] ),
					substr_count( $html, (string) $rule['matches'] )
				),
		};
	}

	/**
	 * Reject malformed rules before a missing selector or literal can pass vacuously.
	 *
	 * @param array<string, mixed> $rule Rule to validate.
	 * @param string               $kind Declared rule kind.
	 * @throws \RuntimeException When required rule fields are missing or invalid.
	 */
	private static function validate_rule( array $rule, string $kind ): void {
		$minimum = $rule['min'] ?? 1;
		if ( ! is_int( $minimum ) || $minimum < 0 || ( 0 === $minimum && 'attribute_required' !== $kind ) ) {
			throw new \RuntimeException( 'Compatibility rule has an invalid min: ' . $kind );
		}

		if ( str_starts_with( $kind, 'markup_' ) ) {
			$fields = 'markup_count_equals' === $kind ? array( 'contains', 'matches' ) : array( 'contains' );
		} else {
			$fields = array( 'select' );
			if ( str_starts_with( $kind, 'attribute_' ) ) {
				$fields[] = 'attribute_name_forbidden' === $kind ? 'prefix' : 'attribute';
			}
			if ( str_starts_with( $kind, 'style_property_' ) ) {
				$fields[] = 'property';
			}
		}

		foreach ( $fields as $field ) {
			if ( ! is_string( $rule[ $field ] ?? null ) || '' === trim( $rule[ $field ] ) ) {
				throw new \RuntimeException( 'Compatibility rule lacks ' . $field . ': ' . $kind );
			}
		}

		if ( isset( $rule['matches'] ) && 'attribute_required' === $kind && false === @preg_match( (string) $rule['matches'], '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid fixture regex is reported below.
			throw new \RuntimeException( 'Compatibility rule has an invalid matches pattern.' );
		}
	}

	/**
	 * Require an attribute, and optionally its value, on every matched element.
	 *
	 * @param array<string, mixed>     $rule     Expectation.
	 * @param array<int, \Dom\Element> $elements Matched elements.
	 * @param string                   $select   Selector, for the message.
	 * @param int                      $minimum  Minimum matched elements.
	 */
	private static function attribute_required( array $rule, array $elements, string $select, int $minimum ): ?string {
		if ( count( $elements ) < $minimum ) {
			return sprintf( 'expected at least %d "%s" element(s) to check, found %d', $minimum, $select, count( $elements ) );
		}

		$attribute = (string) $rule['attribute'];
		$values    = isset( $rule['values'] ) ? array_map( 'strval', (array) $rule['values'] ) : null;
		$matches   = isset( $rule['matches'] ) ? (string) $rule['matches'] : null;

		foreach ( $elements as $element ) {
			if ( ! $element->hasAttribute( $attribute ) ) {
				return sprintf( '"%s" element is missing the %s attribute', $select, $attribute );
			}

			$value = (string) ( $element->getAttribute( $attribute ) ?? '' );
			if ( null !== $values && ! in_array( $value, $values, true ) ) {
				return sprintf( '"%s" has %s="%s", expected one of: %s', $select, $attribute, $value, implode( ', ', $values ) );
			}
			if ( null !== $matches && 1 !== preg_match( $matches, $value ) ) {
				return sprintf( '"%s" has %s="%s", which does not match %s', $select, $attribute, $value, $matches );
			}
		}

		return null;
	}

	/**
	 * Forbid an attribute on every matched element.
	 *
	 * @param array<string, mixed>     $rule     Expectation.
	 * @param array<int, \Dom\Element> $elements Matched elements.
	 * @param string                   $select   Selector, for the message.
	 */
	private static function attribute_forbidden( array $rule, array $elements, string $select ): ?string {
		$attribute = (string) $rule['attribute'];
		foreach ( $elements as $element ) {
			if ( $element->hasAttribute( $attribute ) ) {
				return sprintf( '"%s" element carries the forbidden %s attribute', $select, $attribute );
			}
		}

		return null;
	}

	/**
	 * Forbid every attribute whose name starts with one prefix.
	 *
	 * CSS has no wildcard attribute-name selector, so inline event handlers are
	 * checked by name rather than by a markup substring that would also match
	 * ordinary prose.
	 *
	 * @param array<string, mixed>     $rule     Expectation.
	 * @param array<int, \Dom\Element> $elements Matched elements.
	 * @param string                   $select   Selector, for the message.
	 */
	private static function attribute_name_forbidden( array $rule, array $elements, string $select ): ?string {
		$prefix = strtolower( (string) $rule['prefix'] );
		foreach ( $elements as $element ) {
			foreach ( $element->attributes as $attribute ) {
				if ( str_starts_with( strtolower( $attribute->name ), $prefix ) ) {
					return sprintf( '"%s" element carries the forbidden attribute %s', $select, $attribute->name );
				}
			}
		}

		return null;
	}

	/**
	 * Require an inline CSS property on every matched element.
	 *
	 * @param array<string, mixed>     $rule     Expectation.
	 * @param array<int, \Dom\Element> $elements Matched elements.
	 * @param string                   $select   Selector, for the message.
	 * @param int                      $minimum  Minimum matched elements.
	 */
	private static function style_property_required( array $rule, array $elements, string $select, int $minimum ): ?string {
		if ( count( $elements ) < $minimum ) {
			return sprintf( 'expected at least %d "%s" element(s) to check, found %d', $minimum, $select, count( $elements ) );
		}

		$property = (string) $rule['property'];
		foreach ( $elements as $element ) {
			$style = (string) ( $element->getAttribute( 'style' ) ?? '' );
			if ( ! self::declares( $style, $property ) ) {
				return sprintf( '"%s" element does not declare %s inline (style="%s")', $select, $property, $style );
			}
		}

		return null;
	}

	/**
	 * Forbid an inline CSS property on every matched element.
	 *
	 * @param array<string, mixed>     $rule     Expectation.
	 * @param array<int, \Dom\Element> $elements Matched elements.
	 * @param string                   $select   Selector, for the message.
	 */
	private static function style_property_forbidden( array $rule, array $elements, string $select ): ?string {
		$property = (string) $rule['property'];
		foreach ( $elements as $element ) {
			$style = (string) ( $element->getAttribute( 'style' ) ?? '' );
			if ( self::declares( $style, $property ) ) {
				return sprintf( '"%s" element declares the forbidden property %s (style="%s")', $select, $property, $style );
			}
		}

		return null;
	}

	/**
	 * Whether an inline style declaration list sets one property.
	 *
	 * @param string $style    Inline style attribute value.
	 * @param string $property CSS property name.
	 */
	private static function declares( string $style, string $property ): bool {
		foreach ( explode( ';', $style ) as $declaration ) {
			$name = explode( ':', $declaration, 2 )[0];
			if ( strtolower( trim( $name ) ) === strtolower( $property ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse compiled email HTML with the HTML5 parser.
	 *
	 * @param string $html Compiled email HTML.
	 */
	public static function parse( string $html ): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString( $html, LIBXML_NOERROR );
	}

	/** The client fixture directory. */
	private static function directory(): string {
		return dirname( __DIR__, 2 ) . '/Fixtures/Email/compatibility/clients';
	}
}
