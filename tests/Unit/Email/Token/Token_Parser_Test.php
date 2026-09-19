<?php
/**
 * Unit tests for Token_Parser.
 *
 * @package CampaignBridge\Tests
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email\Token;

use CampaignBridge\Domain\Email\Token\Token_Definition;
use CampaignBridge\Domain\Email\Token\Token_Diagnostic;
use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Domain\Email\Token\Token_Parse_Result;
use CampaignBridge\Domain\Email\Token\Token_Registry;
use CampaignBridge\Tests\Helpers\Test_Case;

/**
 * Tests for the Token_Parser.
 *
 * @covers \CampaignBridge\Domain\Email\Token\Token_Parser
 */
final class Token_Parser_Test extends Test_Case {

	/**
	 * Parser under test.
	 *
	 * @var Token_Parser
	 */
	private Token_Parser $parser;

	/**
	 * Default registry for parser tests.
	 *
	 * @var Token_Registry
	 */
	private Token_Registry $registry;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->parser   = new Token_Parser();
		$this->registry = Token_Registry::default();
	}

	/**
	 * Parse text using the default registry.
	 *
	 * @param string $text Input text.
	 * @return Token_Parse_Result Parse result.
	 */
	private function parse( string $text ): Token_Parse_Result {
		return $this->parser->parse( $text, $this->registry );
	}

	/**
	 * Test that a single valid token is parsed correctly.
	 */
	public function test_parse_single_valid_token(): void {
		$result = $this->parse( 'Hello {{cb:subscriber.first_name}}' );

		$this->assertTrue( $result->is_successful() );
		$tokens = $result->get_tokens();
		$this->assertCount( 1, $tokens );
		$this->assertSame( 'cb:subscriber.first_name', $tokens[0]->get_id() );
	}

	/**
	 * Test that multiple tokens are parsed.
	 */
	public function test_parse_multiple_tokens(): void {
		$text   = '{{cb:subscriber.first_name}}, {{cb:subscriber.last_name}}';
		$result = $this->parse( $text );

		$this->assertTrue( $result->is_successful() );
		$tokens = $result->get_tokens();
		$this->assertCount( 2, $tokens );
		$this->assertSame( 'cb:subscriber.first_name', $tokens[0]->get_id() );
		$this->assertSame( 'cb:subscriber.last_name', $tokens[1]->get_id() );
	}

	/**
	 * Test that adjacent tokens (no separator) are parsed.
	 */
	public function test_parse_adjacent_tokens(): void {
		$text   = '{{cb:subscriber.first_name}}{{cb:subscriber.last_name}}';
		$result = $this->parse( $text );

		$this->assertTrue( $result->is_successful() );
		$this->assertCount( 2, $result->get_tokens() );
	}

	/**
	 * Test that an unknown token (valid format, not in registry) fails closed.
	 */
	public function test_parse_unknown_token_fails_closed(): void {
		$result = $this->parse( 'Hello {{cb:subscriber.nonexistent}}' );

		$this->assertFalse( $result->is_successful() );
		$diagnostics = $result->get_diagnostics();
		$this->assertNotEmpty( $diagnostics );
		$this->assertTrue( $diagnostics[0]->is_error() );
		$this->assertSame( Token_Diagnostic::CODE_UNKNOWN_TOKEN, $diagnostics[0]->get_code() );
		$this->assertSame( 6, $diagnostics[0]->get_position() );
		$this->assertSame( array(), $result->get_tokens() );
	}

	/**
	 * Test that a malformed token (invalid format) fails closed.
	 */
	public function test_parse_malformed_token_fails_closed(): void {
		// Uppercase in ID is invalid per the strict pattern.
		$result = $this->parse( 'Hello {{cb:Subscriber.First_Name}}' );

		$this->assertFalse( $result->is_successful() );
		$diagnostics = $result->get_diagnostics();
		$this->assertNotEmpty( $diagnostics );
		$this->assertTrue( $diagnostics[0]->is_error() );
		$this->assertSame( Token_Diagnostic::CODE_MALFORMED_TOKEN, $diagnostics[0]->get_code() );
		$this->assertSame( 'Malformed token expression', $diagnostics[0]->get_message() );
	}

	/**
	 * Test that a token without the cb: prefix is treated as literal text.
	 */
	public function test_parse_token_without_prefix_fails_closed(): void {
		// `{{cb:subscriber.first_name` (missing closing braces) is malformed.
		$result = $this->parse( 'Hello {{cb:subscriber.first_name' );

		$this->assertFalse( $result->is_successful() );
	}

	/**
	 * Test that literal single braces are preserved (not treated as tokens).
	 */
	public function test_parse_literal_single_braces_preserved(): void {
		$text   = 'Use {single} braces and {{double}} non-token';
		$result = $this->parse( $text );

		// `{{double}}` is not followed by `cb:` so it's literal text.
		// No tokens should be found.
		$this->assertTrue( $result->is_successful() );
		$this->assertCount( 0, $result->get_tokens() );
	}

	/**
	 * Test that diagnostics never contain PII.
	 */
	public function test_diagnostics_contain_no_pii(): void {
		// Use an unknown token to force a diagnostic.
		$result = $this->parse( 'Hello {{cb:subscriber.secret_ssn}}' );

		$diagnostics = $result->get_diagnostics();
		$this->assertNotEmpty( $diagnostics, 'Expected at least one diagnostic for unknown token' );

		foreach ( $diagnostics as $diagnostic ) {
			$this->assertStringNotContainsString( '@', $diagnostic->get_message() );
			$this->assertStringNotContainsString( 'alice', $diagnostic->get_message() );
			$this->assertStringNotContainsString( '123-45-6789', $diagnostic->get_message() );
		}
	}

	/**
	 * Test that parsing an empty string succeeds with no tokens.
	 */
	public function test_parse_empty_string(): void {
		$result = $this->parse( '' );

		$this->assertTrue( $result->is_successful() );
		$this->assertCount( 0, $result->get_tokens() );
		$this->assertCount( 0, $result->get_diagnostics() );
	}

	/**
	 * Test that a token with internal whitespace is rejected.
	 */
	public function test_parse_token_with_internal_whitespace_rejected(): void {
		$result = $this->parse( 'Hello {{cb:subscriber. first_name}}' );

		$this->assertFalse( $result->is_successful() );
	}

	/**
	 * Test that nested braces within a token are rejected.
	 */
	public function test_parse_nested_braces_rejected(): void {
		$result = $this->parse( '{{cb:subscriber.{{nested}}}}' );

		$this->assertFalse( $result->is_successful() );
	}

	/**
	 * Malformed input that contains subscriber-like data is never echoed.
	 */
	public function test_malformed_diagnostics_do_not_echo_input(): void {
		$result = $this->parse( 'Hi {{cb:alice.smith@example.com 123-45-6789}} and {{cb:subscriber.first_name' );

		$this->assertFalse( $result->is_successful() );
		$this->assertCount( 2, $result->get_diagnostics() );
		foreach ( $result->get_diagnostics() as $diagnostic ) {
			$this->assertSame( Token_Diagnostic::CODE_MALFORMED_TOKEN, $diagnostic->get_code() );
			foreach ( array( 'alice', '@', 'example.com', '123-45-6789', 'Hi ' ) as $fragment ) {
				$this->assertStringNotContainsString( $fragment, $diagnostic->get_message() );
			}
		}
	}

	/**
	 * Non-CampaignBridge double-brace syntax is literal text, not a token.
	 */
	public function test_foreign_double_brace_syntax_is_literal(): void {
		$result = $this->parse( 'Hi {{ first_name }} {{FNAME}} *|FNAME|* {{#if x}}' );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( array(), $result->get_tokens() );
		$this->assertSame( array(), $result->get_diagnostics() );
	}

	/**
	 * Identical input produces identical tokens and diagnostics.
	 */
	public function test_parse_is_deterministic(): void {
		$input = '{{cb:organization.name}} {{cb:campaign.unsubscribe_url}} {{cb:nope.x}}';

		$this->assertEquals( $this->parse( $input ), $this->parse( $input ) );
	}
}
