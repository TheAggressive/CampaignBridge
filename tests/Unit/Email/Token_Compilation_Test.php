<?php
/**
 * Canonical token resolution and preservation through the email compiler.
 *
 * Fixtures are real Core block serialization parsed by parse_blocks(), so the
 * WordPress Native First normalization and token handling run together.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Token\Token_Parser;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Workflow\Email\Email_Compiler;
use PHPUnit\Framework\TestCase;

final class Token_Compilation_Test extends TestCase {
	private const SECTION_CHILD = 'blocks[0].innerBlocks[0].innerBlocks[0]';

	private const ORGANIZATION = array(
		'cb:organization.name'    => 'Example Company',
		'cb:organization.address' => '1 Main St, Springfield',
	);

	private const DOCUMENT = '<!-- wp:campaignbridge/container -->'
		. '<!-- wp:campaignbridge/preheader {"content":"News for {{cb:subscriber.first_name}} from {{cb:organization.name}}"} /-->'
		. '<!-- wp:campaignbridge/section -->'
		. '<!-- wp:heading --><h2 class="wp-block-heading">Welcome to {{cb:organization.name}}</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>Hello <strong>{{cb:subscriber.first_name}} {{cb:subscriber.last_name}}</strong>, this is for {{cb:subscriber.email}}. <a href="{{cb:campaign.unsubscribe_url}}">Unsubscribe</a></p><!-- /wp:paragraph -->'
		. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Sent by <em>{{cb:organization.name}}</em></li><!-- /wp:list-item --><!-- wp:list-item --><li>{{cb:organization.address}}</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
		. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{cb:campaign.view_online_url}}">View online, {{cb:subscriber.first_name}}</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

	public function test_core_blocks_resolve_local_tokens_and_preserve_provider_tokens(): void {
		$result = $this->compile_document( self::DOCUMENT );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		$html = $result->html();
		$text = $result->text();

		self::assertStringContainsString( 'News for {{cb:subscriber.first_name}} from Example Company', $html );
		self::assertStringContainsString( 'Welcome to Example Company</h2>', $html );
		self::assertStringContainsString( 'Hello <strong>{{cb:subscriber.first_name}} {{cb:subscriber.last_name}}</strong>, this is for {{cb:subscriber.email}}.', $html );
		self::assertStringContainsString( '<a href="{{cb:campaign.unsubscribe_url}}" style="color:inherit;text-decoration:underline">Unsubscribe</a>', $html );
		self::assertStringContainsString( 'Sent by <em>Example Company</em></li>', $html );
		self::assertStringContainsString( '1 Main St, Springfield</li>', $html );
		self::assertSame( 2, substr_count( $html, 'href="{{cb:campaign.view_online_url}}"' ), 'VML and HTML button links keep the canonical token.' );
		self::assertStringContainsString( '>View online, {{cb:subscriber.first_name}}</a>', $html );

		self::assertStringContainsString( "Welcome to Example Company\n", $text );
		self::assertStringContainsString( 'Hello {{cb:subscriber.first_name}} {{cb:subscriber.last_name}}, this is for {{cb:subscriber.email}}. Unsubscribe ({{cb:campaign.unsubscribe_url}})', $text );
		self::assertStringContainsString( '- Sent by Example Company', $text );
		self::assertStringContainsString( 'View online, {{cb:subscriber.first_name}}: {{cb:campaign.view_online_url}}', $text );

		foreach ( array( $html, $text ) as $artifact ) {
			self::assertStringNotContainsString( '{{cb:organization.', $artifact );
			self::assertStringNotContainsString( '*|', $artifact );
			self::assertStringNotContainsString( '|*', $artifact );
			self::assertDoesNotMatchRegularExpression( '/\{\{(?!cb:)/', $artifact );
		}
	}

	public function test_provider_tokens_compile_without_subscriber_or_provider_values(): void {
		$result = $this->compile(
			'<!-- wp:paragraph --><p>Hi {{cb:subscriber.first_name}} {{cb:subscriber.last_name}} {{cb:subscriber.email}} '
			. '<a href="{{cb:campaign.view_online_url}}">online</a> <a href="{{cb:campaign.unsubscribe_url}}">leave</a></p><!-- /wp:paragraph -->',
			array()
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		foreach ( array( 'subscriber.first_name', 'subscriber.last_name', 'subscriber.email', 'campaign.view_online_url', 'campaign.unsubscribe_url' ) as $id ) {
			self::assertStringContainsString( '{{cb:' . $id . '}}', $result->html() );
			self::assertStringContainsString( '{{cb:' . $id . '}}', $result->text() );
		}
	}

	public function test_same_template_and_context_produce_identical_artifacts(): void {
		$first  = $this->compile_document( self::DOCUMENT );
		$second = $this->compile_document( self::DOCUMENT );

		self::assertTrue( $first->is_success(), $this->diagnostics( $first ) );
		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->text(), $second->text() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
		self::assertSame( '7', $first->compiler_version() );
		self::assertSame( Email_Compiler::COMPILER_VERSION, $first->compiler_version() );
	}

	public function test_context_value_order_does_not_change_the_artifact(): void {
		$first  = $this->compile_document( self::DOCUMENT, self::ORGANIZATION );
		$second = $this->compile_document( self::DOCUMENT, array_reverse( self::ORGANIZATION, true ) );

		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_changed_local_value_changes_the_artifact_and_fingerprint(): void {
		$first  = $this->compile_document( self::DOCUMENT );
		$second = $this->compile_document( self::DOCUMENT, array( 'cb:organization.name' => 'Other Company' ) + self::ORGANIZATION );

		self::assertTrue( $second->is_success(), $this->diagnostics( $second ) );
		self::assertStringContainsString( 'Welcome to Other Company', $second->html() );
		self::assertNotSame( $first->html(), $second->html() );
		self::assertNotSame( $first->text(), $second->text() );
		self::assertNotSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_compilation_does_not_mutate_source_blocks_or_context(): void {
		$blocks   = parse_blocks( self::DOCUMENT );
		$context  = $this->context( self::ORGANIZATION );
		$original = array( serialize( $blocks ), serialize( $context ) );

		Compiler_Factory::create()->compile( $blocks, $context );

		self::assertSame( $original, array( serialize( $blocks ), serialize( $context ) ) );
	}

	public function test_local_values_are_escaped_and_cannot_add_markup(): void {
		$result = $this->compile(
			'<!-- wp:paragraph --><p>&copy; {{cb:organization.name}}</p><!-- /wp:paragraph -->',
			array( 'cb:organization.name' => 'A & B <script>alert(1)</script>' )
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '© A &amp; B &lt;script&gt;alert(1)&lt;/script&gt;</p>', $result->html() );
		self::assertStringNotContainsString( '<script>', $result->html() );
		self::assertStringContainsString( '© A & B <script>alert(1)</script>', $result->text() );
	}

	public function test_tokens_do_not_bypass_rich_text_validation(): void {
		$result = $this->compile( '<!-- wp:paragraph --><p><span>{{cb:subscriber.first_name}}</span></p><!-- /wp:paragraph -->' );

		$this->assertSingleDiagnostic( $result, 'text.content.invalid', self::SECTION_CHILD );
	}

	/**
	 * @dataProvider invalid_tokens
	 *
	 * @param string $markup Core block markup.
	 * @param string $code   Expected diagnostic code.
	 * @param string $path   Expected diagnostic path.
	 */
	public function test_token_failures_have_stable_codes_and_paths( string $markup, string $code, string $path ): void {
		$this->assertSingleDiagnostic( $this->compile( $markup ), $code, $path );
	}

	/** @return array<string, array{string, string, string}> */
	public static function invalid_tokens(): array {
		$content = self::SECTION_CHILD . '.attrs.content';
		$button  = self::SECTION_CHILD . '.innerBlocks[0].attrs.';

		return array(
			'unknown paragraph token'   => array( '<!-- wp:paragraph --><p>Hi {{cb:subscriber.phone}}</p><!-- /wp:paragraph -->', 'token.unknown', $content ),
			'malformed heading token'   => array( '<!-- wp:heading --><h2 class="wp-block-heading">{{cb:Organization.Name}}</h2><!-- /wp:heading -->', 'token.malformed', $content ),
			'nested token'              => array( '<!-- wp:paragraph --><p>{{cb:a{{cb:organization.name}}}}</p><!-- /wp:paragraph -->', 'token.nested', $content ),
			'token limit'               => array( '<!-- wp:paragraph --><p>' . str_repeat( '{{cb:organization.name}}', Token_Parser::MAX_TOKEN_COUNT + 1 ) . '</p><!-- /wp:paragraph -->', 'token.limit_exceeded', $content ),
			'split by markup'           => array( '<!-- wp:paragraph --><p>{{<strong>cb:subscriber.first_name}}</strong></p><!-- /wp:paragraph -->', 'token.malformed', $content ),
			'encoded unknown token'     => array( '<!-- wp:paragraph --><p>&#123;&#123;cb:subscriber.phone&#125;&#125;</p><!-- /wp:paragraph -->', 'token.unknown', $content ),
			'string token as link'      => array( '<!-- wp:paragraph --><p><a href="{{cb:subscriber.email}}">x</a></p><!-- /wp:paragraph -->', 'token.url.value_type', $content ),
			'partial link url'          => array( '<!-- wp:paragraph --><p><a href="https://example.com/?e={{cb:subscriber.email}}">x</a></p><!-- /wp:paragraph -->', 'token.url.invalid', $content ),
			'unknown list-item token'   => array( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>{{cb:organization.phone}}</li><!-- /wp:list-item --></ul><!-- /wp:list -->', 'token.unknown', self::SECTION_CHILD . '.innerBlocks[0].attrs.content' ),
			'string token button url'   => array( self::button( '{{cb:subscriber.first_name}}', 'Go' ), 'token.url.value_type', $button . 'url' ),
			'unknown token button url'  => array( self::button( '{{cb:campaign.archive_url}}', 'Go' ), 'token.unknown', $button . 'url' ),
			'partial button url'        => array( self::button( 'https://example.com/?email={{cb:subscriber.email}}', 'Go' ), 'token.url.invalid', $button . 'url' ),
			'javascript button token'   => array( self::button( 'javascript:{{cb:campaign.unsubscribe_url}}', 'Go' ), 'token.url.invalid', $button . 'url' ),
			'prefixed button token'     => array( self::button( 'prefix-{{cb:campaign.unsubscribe_url}}', 'Go' ), 'token.url.invalid', $button . 'url' ),
			'unknown button label'      => array( self::button( 'https://example.com', 'Hi {{cb:subscriber.phone}}' ), 'token.unknown', $button . 'label' ),
			'token in image alt'        => array( '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/a.jpg" alt="Hi {{cb:subscriber.first_name}}"/></figure><!-- /wp:image -->', 'token.context.unsupported', self::SECTION_CHILD . '.attrs.alt' ),
			'token in image url'        => array( '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/{{cb:subscriber.email}}.jpg" alt="x"/></figure><!-- /wp:image -->', 'token.context.unsupported', self::SECTION_CHILD . '.attrs.url' ),
			'token in image link'       => array( '<!-- wp:image {"linkDestination":"custom"} --><figure class="wp-block-image"><a href="{{cb:campaign.unsubscribe_url}}"><img src="https://example.com/a.jpg" alt="x"/></a></figure><!-- /wp:image -->', 'token.context.unsupported', self::SECTION_CHILD . '.attrs.linkUrl' ),
		);
	}

	public function test_unsafe_button_urls_keep_their_existing_diagnostic(): void {
		$this->assertSingleDiagnostic(
			$this->compile( self::button( 'javascript:alert(1)', 'Go' ) ),
			'button.url.invalid',
			self::SECTION_CHILD . '.innerBlocks[0]'
		);
	}

	public function test_missing_local_value_fails_closed_without_partial_artifact(): void {
		$result = $this->compile( '<!-- wp:paragraph --><p>&copy; {{cb:organization.name}}</p><!-- /wp:paragraph -->', array() );

		$this->assertSingleDiagnostic( $result, 'token.unresolved', self::SECTION_CHILD . '.attrs.content' );
		self::assertSame( '', $result->text() );
		self::assertSame( '', $result->fingerprint() );
	}

	public function test_one_diagnostic_per_code_per_attribute(): void {
		$result = $this->compile( '<!-- wp:paragraph --><p>{{cb:subscriber.phone}} {{cb:subscriber.fax}} {{cb:Bad}}</p><!-- /wp:paragraph -->' );

		self::assertSame(
			array( 'token.unknown', 'token.malformed' ),
			array_map( static fn ( $diagnostic ): string => $diagnostic->code(), $result->diagnostics() )
		);
	}

	/**
	 * @dataProvider invalid_context_values
	 *
	 * @param mixed $values Invalid token value map.
	 */
	public function test_invalid_context_values_fail_before_rendering( mixed $values ): void {
		$result = Compiler_Factory::create()->compile(
			parse_blocks( self::DOCUMENT ),
			new Render_Context( array( 'token_values' => $values ) )
		);

		$this->assertSingleDiagnostic( $result, 'token.values.invalid', 'context.token_values' );
		self::assertStringNotContainsString( 'alice', wp_json_encode( $result->diagnostics()[0]->to_array() ) );
	}

	/** @return array<string, array{mixed}> */
	public static function invalid_context_values(): array {
		return array(
			'subscriber value' => array( array( 'cb:subscriber.email' => 'alice@example.com' ) ),
			'unsubscribe url'  => array( array( 'cb:campaign.unsubscribe_url' => 'https://example.com/alice' ) ),
			'token injection'  => array( array( 'cb:organization.name' => 'alice {{cb:subscriber.email}}' ) ),
			'not a map'        => array( 'alice' ),
		);
	}

	public function test_diagnostics_never_echo_token_ids_or_values(): void {
		$result = $this->compile(
			'<!-- wp:paragraph --><p>{{cb:subscriber.ssn_alice}} {{cb:organization.name}}</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>{{cb:organization.address}}</p><!-- /wp:paragraph -->',
			array( 'cb:organization.name' => 'Secret Alice Corp' )
		);

		$json = (string) wp_json_encode( array_map( static fn ( $diagnostic ): array => $diagnostic->to_array(), $result->diagnostics() ) );
		self::assertFalse( $result->is_success() );
		foreach ( array( 'ssn', 'alice', 'Alice', 'organization', 'subscriber', '{{' ) as $fragment ) {
			self::assertStringNotContainsString( $fragment, $json );
		}
	}

	public function test_unsupported_core_block_behavior_is_unchanged_by_tokens(): void {
		$result = $this->compile( '<!-- wp:quote --><blockquote class="wp-block-quote"><p>{{cb:subscriber.first_name}}</p></blockquote><!-- /wp:quote -->' );

		$this->assertSingleDiagnostic( $result, 'block.child.unsupported', self::SECTION_CHILD );
	}

	public function test_post_blocks_do_not_accept_tokens_and_still_compile(): void {
		$card      = '<!-- wp:campaignbridge/post-card {"postId":42} -->'
			. '<!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"title"}}}}} --><h2></h2><!-- /wp:heading -->'
			. '%s<!-- /wp:campaignbridge/post-card -->';
		$excerpt   = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"excerpt"}}}}} --><p>%s</p><!-- /wp:paragraph -->';
		$plain     = $this->compile( sprintf( $card, sprintf( $excerpt, '' ) ) );
		$tokenized = $this->compile( sprintf( $card, sprintf( $excerpt, 'Read {{cb:subscriber.first_name}}' ) ) );

		self::assertTrue( $plain->is_success(), $this->diagnostics( $plain ) );
		self::assertStringContainsString( 'Snapshot title', $plain->html() );
		// A bound attribute cannot also carry authored text, token or not.
		$this->assertSingleDiagnostic( $tokenized, 'block.attribute.invalid', self::SECTION_CHILD . '.innerBlocks[1].attrs.content' );
	}

	/**
	 * Build one Core buttons group with one button.
	 *
	 * @param string $url   Button href.
	 * @param string $label Button label.
	 */
	private static function button( string $url, string $label ): string {
		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="'
			. $url . '">' . $label . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/**
	 * Compile Core markup inside the standard container and section.
	 *
	 * @param string                $markup Core block markup.
	 * @param array<string, string> $values Token values.
	 */
	private function compile( string $markup, array $values = self::ORGANIZATION ): Compile_Result {
		return $this->compile_document(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->' . $markup . '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			$values
		);
	}

	/**
	 * Compile one serialized document.
	 *
	 * @param string                $serialized Serialized blocks.
	 * @param array<string, string> $values     Token values.
	 */
	private function compile_document( string $serialized, array $values = self::ORGANIZATION ): Compile_Result {
		return Compiler_Factory::create()->compile( parse_blocks( $serialized ), $this->context( $values ) );
	}

	/**
	 * Build the immutable compile context.
	 *
	 * @param array<string, string> $values Token values.
	 */
	private function context( array $values ): Render_Context {
		return new Render_Context(
			array(
				'title'        => 'Token fixture',
				'language'     => 'en',
				'token_values' => $values,
			),
			array(
				'posts' => array(
					'42' => Post_Snapshot::create(
						42,
						'post',
						array(
							'title'   => 'Snapshot title',
							'excerpt' => 'Snapshot excerpt',
							'url'     => 'https://example.com/post',
						)
					),
				),
			)
		);
	}

	/**
	 * Assert a failed compile with exactly one diagnostic.
	 *
	 * @param Compile_Result $result Compile result.
	 * @param string         $code   Expected code.
	 * @param string         $path   Expected path.
	 */
	private function assertSingleDiagnostic( Compile_Result $result, string $code, string $path ): void {
		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
		self::assertCount( 1, $result->diagnostics(), $this->diagnostics( $result ) );
		self::assertSame( $code, $result->diagnostics()[0]->code() );
		self::assertSame( $path, $result->diagnostics()[0]->path() );
	}

	private function diagnostics( Compile_Result $result ): string {
		return implode(
			'; ',
			array_map(
				static fn ( $diagnostic ): string => $diagnostic->code() . ' @ ' . $diagnostic->path() . ': ' . $diagnostic->to_array()['message'],
				$result->diagnostics()
			)
		);
	}
}
