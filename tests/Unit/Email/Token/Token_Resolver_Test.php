<?php
/**
 * Unit tests for Token_Resolver.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email\Token;

use CampaignBridge\Domain\Email\Token\Token_Diagnostic;
use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Domain\Email\Token\Token_Resolution;
use CampaignBridge\Domain\Email\Token\Token_Resolver;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for provider-neutral token resolution and preservation.
 *
 * @covers \CampaignBridge\Domain\Email\Token\Token_Resolver
 * @covers \CampaignBridge\Domain\Email\Token\Token_Resolution
 */
final class Token_Resolver_Test extends Test_Case {

	private const VALUES = array(
		'cb:organization.name'    => 'Example Company',
		'cb:organization.address' => '1 Main St, Springfield',
	);

	/**
	 * Resolver under test.
	 *
	 * @var Token_Resolver
	 */
	private Token_Resolver $resolver;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->resolver = Token_Resolver::default();
	}

	public function test_organization_name_resolves(): void {
		$this->assertResolved( 'Copyright © Example Company', $this->text( 'Copyright © {{cb:organization.name}}' ) );
	}

	public function test_organization_address_resolves(): void {
		$this->assertResolved( 'Visit 1 Main St, Springfield', $this->text( 'Visit {{cb:organization.address}}' ) );
	}

	public function test_multiple_local_tokens_in_one_value_resolve(): void {
		$this->assertResolved(
			'Example Company, 1 Main St, Springfield. Example Company',
			$this->text( '{{cb:organization.name}}, {{cb:organization.address}}. {{cb:organization.name}}' )
		);
	}

	public function test_local_and_provider_tokens_mix_in_one_value(): void {
		$this->assertResolved(
			'Hi {{cb:subscriber.first_name}}, welcome to Example Company',
			$this->text( 'Hi {{cb:subscriber.first_name}}, welcome to {{cb:organization.name}}' )
		);
	}

	/**
	 * @dataProvider provider_tokens
	 *
	 * @param string $expression Canonical provider-resolved expression.
	 */
	public function test_provider_tokens_are_preserved_without_values( string $expression ): void {
		$result = $this->resolver->resolve( Token_Resolver::CONTEXT_TEXT, 'Value: ' . $expression, array() );

		$this->assertResolved( 'Value: ' . $expression, $result );
	}

	/** @return array<string, array{string}> */
	public static function provider_tokens(): array {
		return array(
			'first name'  => array( '{{cb:subscriber.first_name}}' ),
			'last name'   => array( '{{cb:subscriber.last_name}}' ),
			'email'       => array( '{{cb:subscriber.email}}' ),
			'view online' => array( '{{cb:campaign.view_online_url}}' ),
			'unsubscribe' => array( '{{cb:campaign.unsubscribe_url}}' ),
		);
	}

	public function test_missing_local_value_fails_closed_without_echoing(): void {
		$result = $this->resolver->resolve( Token_Resolver::CONTEXT_TEXT, 'By {{cb:organization.name}}', array() );

		$this->assertFailedWith( Token_Diagnostic::CODE_UNRESOLVED_TOKEN, $result );
		$this->assertNull( $result->value() );
		$this->assertSame( 3, $result->diagnostics()[0]->get_position() );
		$this->assertStringNotContainsString( 'organization', $result->diagnostics()[0]->get_message() );
	}

	/**
	 * @dataProvider parser_failures
	 *
	 * @param string $value Invalid value.
	 * @param string $code  Expected diagnostic code.
	 */
	public function test_parser_diagnostics_are_the_source_contract( string $value, string $code ): void {
		$this->assertFailedWith( $code, $this->text( $value ) );
	}

	/** @return array<string, array{string, string}> */
	public static function parser_failures(): array {
		return array(
			'unknown'       => array( 'Hi {{cb:subscriber.nickname}}', Token_Diagnostic::CODE_UNKNOWN_TOKEN ),
			'malformed'     => array( 'Hi {{cb:Subscriber.First}}', Token_Diagnostic::CODE_MALFORMED_TOKEN ),
			'unclosed'      => array( 'Hi {{cb:subscriber.first_name', Token_Diagnostic::CODE_MALFORMED_TOKEN ),
			'nested'        => array( 'Hi {{cb:a{{cb:organization.name}}}}', Token_Diagnostic::CODE_NESTED_TOKEN ),
			'limit'         => array( str_repeat( '{{cb:organization.name}}', Token_Parser::MAX_TOKEN_COUNT + 1 ), Token_Diagnostic::CODE_TOKEN_LIMIT ),
			'brace run'     => array( 'Hi {{{cb:subscriber.nickname}}', Token_Diagnostic::CODE_UNKNOWN_TOKEN ),
		);
	}

	public function test_foreign_double_braces_remain_literal_text(): void {
		$this->assertResolved( 'Hi {{FNAME}} *|FNAME|*', $this->text( 'Hi {{FNAME}} *|FNAME|*' ) );
	}

	public function test_resolution_is_deterministic_and_does_not_mutate_inputs(): void {
		$value  = 'Hi {{cb:subscriber.first_name}} from {{cb:organization.name}}';
		$values = self::VALUES;

		$first  = $this->resolver->resolve( Token_Resolver::CONTEXT_TEXT, $value, $values );
		$second = $this->resolver->resolve( Token_Resolver::CONTEXT_TEXT, $value, $values );

		$this->assertEquals( $first, $second );
		$this->assertSame( 'Hi {{cb:subscriber.first_name}} from {{cb:organization.name}}', $value );
		$this->assertSame( self::VALUES, $values );
	}

	public function test_rich_text_tokens_resolve_inside_supported_markup(): void {
		$this->assertResolved(
			'Hello <strong>{{cb:subscriber.first_name}}</strong>, from <em>Example Company</em>!',
			$this->rich( 'Hello <strong>{{cb:subscriber.first_name}}</strong>, from <em>{{cb:organization.name}}</em>!' )
		);
	}

	public function test_rich_text_local_values_are_html_encoded(): void {
		$result = $this->resolver->resolve(
			Token_Resolver::CONTEXT_RICH_TEXT,
			'&copy; {{cb:organization.name}}',
			array( 'cb:organization.name' => 'A & B <script>' )
		);

		$this->assertResolved( '© A &amp; B &lt;script&gt;', $result );
	}

	public function test_rich_text_link_can_use_a_provider_url_token(): void {
		$html = '<a href="{{cb:campaign.unsubscribe_url}}">Unsubscribe</a> or <a href="{{cb:campaign.view_online_url}}" target="_blank" rel="noopener">view online</a>';

		$this->assertResolved( $html, $this->rich( $html ) );
	}

	/**
	 * @dataProvider invalid_rich_text
	 *
	 * @param string $html Invalid rich text.
	 * @param string $code Expected diagnostic code.
	 */
	public function test_rich_text_token_misuse_fails_closed( string $html, string $code ): void {
		$this->assertFailedWith( $code, $this->rich( $html ) );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_rich_text(): array {
		return array(
			'unknown in text'        => array( '<strong>{{cb:subscriber.phone}}</strong>', Token_Diagnostic::CODE_UNKNOWN_TOKEN ),
			'encoded braces'         => array( '&#123;&#123;cb:subscriber.phone&#125;&#125;', Token_Diagnostic::CODE_UNKNOWN_TOKEN ),
			'split by markup'        => array( '{{cb:<strong>subscriber.first_name</strong>}}', Token_Diagnostic::CODE_MALFORMED_TOKEN ),
			'opener split by markup' => array( '{{<strong>cb:subscriber.first_name}}</strong>', Token_Diagnostic::CODE_MALFORMED_TOKEN ),
			'string token as href'   => array( '<a href="{{cb:subscriber.first_name}}">x</a>', Token_Diagnostic::CODE_URL_VALUE_TYPE ),
			'partial href'           => array( '<a href="https://example.com/?e={{cb:subscriber.email}}">x</a>', Token_Diagnostic::CODE_URL_CONTEXT ),
			'token outside href'     => array( '<a href="https://example.com" title="{{cb:organization.name}}">x</a>', Token_Diagnostic::CODE_UNSUPPORTED_CONTEXT ),
			'token in other tag'     => array( '<strong data-x="{{cb:organization.name}}">x</strong>', Token_Diagnostic::CODE_UNSUPPORTED_CONTEXT ),
		);
	}

	public function test_rich_text_missing_local_value_fails_closed(): void {
		$result = $this->resolver->resolve( Token_Resolver::CONTEXT_RICH_TEXT, 'By <em>{{cb:organization.address}}</em>', array( 'cb:organization.name' => 'Example Company' ) );

		$this->assertFailedWith( Token_Diagnostic::CODE_UNRESOLVED_TOKEN, $result );
	}

	public function test_rich_text_limit_applies_across_runs(): void {
		$html = str_repeat( '<em>{{cb:subscriber.first_name}}</em>', Token_Parser::MAX_TOKEN_COUNT + 1 );

		$this->assertFailedWith( Token_Diagnostic::CODE_TOKEN_LIMIT, $this->rich( $html ) );
	}

	/**
	 * @dataProvider valid_urls
	 *
	 * @param string $url Accepted link destination.
	 */
	public function test_url_context_accepts_literal_urls_and_exact_url_tokens( string $url ): void {
		$this->assertResolved( $url, $this->resolver->resolve( Token_Resolver::CONTEXT_URL, $url, array() ) );
	}

	/** @return array<string, array{string}> */
	public static function valid_urls(): array {
		return array(
			'https'       => array( 'https://example.com/path?x=1' ),
			'view online' => array( '{{cb:campaign.view_online_url}}' ),
			'unsubscribe' => array( '{{cb:campaign.unsubscribe_url}}' ),
		);
	}

	/**
	 * @dataProvider invalid_urls
	 *
	 * @param string $url  Rejected link destination.
	 * @param string $code Expected diagnostic code.
	 */
	public function test_url_context_rejects_token_misuse( string $url, string $code ): void {
		$this->assertFailedWith( $code, $this->resolver->resolve( Token_Resolver::CONTEXT_URL, $url, self::VALUES ) );
	}

	/** @return array<string, array{string, string}> */
	public static function invalid_urls(): array {
		return array(
			'string token'         => array( '{{cb:subscriber.first_name}}', Token_Diagnostic::CODE_URL_VALUE_TYPE ),
			'local string token'   => array( '{{cb:organization.name}}', Token_Diagnostic::CODE_URL_VALUE_TYPE ),
			'unknown token'        => array( '{{cb:campaign.archive_url}}', Token_Diagnostic::CODE_UNKNOWN_TOKEN ),
			'malformed token'      => array( '{{cb:Campaign.URL}}', Token_Diagnostic::CODE_MALFORMED_TOKEN ),
			'query interpolation'  => array( 'https://example.com/?email={{cb:subscriber.email}}', Token_Diagnostic::CODE_URL_CONTEXT ),
			'javascript prefix'    => array( 'javascript:{{cb:campaign.unsubscribe_url}}', Token_Diagnostic::CODE_URL_CONTEXT ),
			'text prefix'          => array( 'prefix-{{cb:campaign.unsubscribe_url}}', Token_Diagnostic::CODE_URL_CONTEXT ),
			'two tokens'           => array( '{{cb:campaign.unsubscribe_url}}{{cb:campaign.view_online_url}}', Token_Diagnostic::CODE_URL_CONTEXT ),
			'foreign braces'       => array( '{{unsubscribe}}', Token_Diagnostic::CODE_URL_CONTEXT ),
			'padded token'         => array( ' {{cb:campaign.unsubscribe_url}}', Token_Diagnostic::CODE_URL_CONTEXT ),
		);
	}

	public function test_reject_flags_token_syntax_in_unsupported_fields(): void {
		$this->assertResolved( 'Plain {{FNAME}} alt', $this->resolver->reject( 'Plain {{FNAME}} alt' ) );
		$this->assertFailedWith( Token_Diagnostic::CODE_UNSUPPORTED_CONTEXT, $this->resolver->reject( 'Alt {{cb:organization.name}}' ) );
	}

	public function test_is_url_token_accepts_only_exact_provider_url_tokens(): void {
		$this->assertTrue( $this->resolver->is_url_token( '{{cb:campaign.unsubscribe_url}}' ) );
		$this->assertTrue( $this->resolver->is_url_token( '{{cb:campaign.view_online_url}}' ) );
		$this->assertFalse( $this->resolver->is_url_token( '{{cb:subscriber.email}}' ) );
		$this->assertFalse( $this->resolver->is_url_token( '{{cb:organization.name}}' ) );
		$this->assertFalse( $this->resolver->is_url_token( '{{cb:campaign.archive_url}}' ) );
		$this->assertFalse( $this->resolver->is_url_token( 'x{{cb:campaign.unsubscribe_url}}' ) );
	}

	public function test_context_values_accept_only_bounded_campaignbridge_values(): void {
		$this->assertNull( $this->resolver->validate_context_values( null ) );
		$this->assertNull( $this->resolver->validate_context_values( array() ) );
		$this->assertNull( $this->resolver->validate_context_values( array( 'cb:organization.address' => "1 Main St\nSpringfield" ) ) );
		$this->assertNull( $this->resolver->validate_context_values( self::VALUES ) );
	}

	/**
	 * @dataProvider invalid_context_values
	 *
	 * @param mixed $values Rejected context map.
	 */
	public function test_context_values_reject_provider_unknown_or_unsafe_values( mixed $values ): void {
		$diagnostic = $this->resolver->validate_context_values( $values );

		$this->assertNotNull( $diagnostic );
		$this->assertSame( Token_Diagnostic::CODE_INVALID_CONTEXT_VALUE, $diagnostic->get_code() );
		$this->assertStringNotContainsString( 'alice', $diagnostic->get_message() );
	}

	/** @return array<string, array{mixed}> */
	public static function invalid_context_values(): array {
		return array(
			'not a map'           => array( 'Example Company' ),
			'subscriber value'    => array( array( 'cb:subscriber.email' => 'alice@example.com' ) ),
			'provider url value'  => array( array( 'cb:campaign.unsubscribe_url' => 'https://example.com/u' ) ),
			'unknown id'          => array( array( 'cb:organization.phone' => '555' ) ),
			'bare id'             => array( array( 'organization.name' => 'alice' ) ),
			'list'                => array( array( 'alice' ) ),
			'non-string'          => array( array( 'cb:organization.name' => 42 ) ),
			'blank'               => array( array( 'cb:organization.name' => '   ' ) ),
			'token injection'     => array( array( 'cb:organization.name' => 'alice {{cb:subscriber.email}}' ) ),
			'closing braces'      => array( array( 'cb:organization.name' => 'alice }}' ) ),
			'control character'   => array( array( 'cb:organization.name' => "alice\x07" ) ),
			'invalid utf-8'       => array( array( 'cb:organization.name' => "alice\xC3\x28" ) ),
			'too long'            => array( array( 'cb:organization.name' => str_repeat( 'a', Token_Resolver::MAX_VALUE_LENGTH + 1 ) ) ),
		);
	}

	public function test_unsupported_context_is_a_programming_error(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->resolver->resolve( 'css', 'x', array() );
	}

	/**
	 * Resolve plain text with the default organization values.
	 *
	 * @param string $value Plain text.
	 */
	private function text( string $value ): Token_Resolution {
		return $this->resolver->resolve( Token_Resolver::CONTEXT_TEXT, $value, self::VALUES );
	}

	/**
	 * Resolve rich text with the default organization values.
	 *
	 * @param string $html Rich text.
	 */
	private function rich( string $html ): Token_Resolution {
		return $this->resolver->resolve( Token_Resolver::CONTEXT_RICH_TEXT, $html, self::VALUES );
	}

	/**
	 * Assert a successful resolution with an exact value.
	 *
	 * @param string           $expected Expected value.
	 * @param Token_Resolution $result   Resolution.
	 */
	private function assertResolved( string $expected, Token_Resolution $result ): void {
		$this->assertTrue( $result->is_successful(), implode( ', ', array_map( static fn ( Token_Diagnostic $d ): string => $d->get_code(), $result->diagnostics() ) ) );
		$this->assertSame( $expected, $result->value() );
		$this->assertSame( array(), $result->diagnostics() );
	}

	/**
	 * Assert a failed resolution whose first diagnostic has the code.
	 *
	 * @param string           $code   Expected code.
	 * @param Token_Resolution $result Resolution.
	 */
	private function assertFailedWith( string $code, Token_Resolution $result ): void {
		$this->assertFalse( $result->is_successful() );
		$this->assertNull( $result->value() );
		$this->assertSame( $code, $result->diagnostics()[0]->get_code() );
		foreach ( $result->diagnostics() as $diagnostic ) {
			$this->assertTrue( $diagnostic->is_error() );
		}
	}
}
