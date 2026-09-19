<?php
/**
 * Canonical token provenance: snapshot content is never a token context.
 *
 * Every `{{cb:...}}` in a successful artifact must originate from a semantic
 * attribute a renderer declares through token_attributes(). Snapshot content
 * captured from WordPress that contains token syntax fails closed.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Snapshot_Token_Provenance_Test extends TestCase {
	private const CARD = 'blocks[0].innerBlocks[0].innerBlocks[0]';

	private const TOKEN = '{{cb:subscriber.first_name}}';

	private const SNAPSHOT = array(
		'title'   => 'Snapshot title',
		'excerpt' => 'Snapshot excerpt',
		'url'     => 'https://example.com/post',
		'image'   => array(
			'url'    => 'https://example.com/image.jpg',
			'alt'    => 'Snapshot alt',
			'width'  => 600,
			'height' => 300,
		),
	);

	/**
	 * Snapshot fields that reach the artifact reject canonical token syntax.
	 *
	 * @dataProvider rendered_snapshot_fields
	 *
	 * @param array<string, mixed> $values Snapshot values.
	 * @param string               $child  Post-card child markup.
	 * @param string               $path   Expected diagnostic path.
	 */
	public function test_rendered_snapshot_token_fails_closed( array $values, string $child, string $path ): void {
		$result = $this->compile( self::card( $child ), $values );

		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
		self::assertSame( '', $result->text() );
		self::assertSame( '', $result->fingerprint() );
		self::assertCount( 1, $result->diagnostics(), $this->diagnostics( $result ) );
		self::assertSame( 'token.snapshot.unsupported', $result->diagnostics()[0]->code() );
		self::assertSame( $path, $result->diagnostics()[0]->path() );
	}

	/** @return array<string, array{0: array<string, mixed>, 1: string, 2: string}> */
	public static function rendered_snapshot_fields(): array {
		$image          = self::SNAPSHOT['image'];
		$image['alt']   = 'Photo of ' . self::TOKEN;
		$tokenized_url  = 'https://example.com/post?name=' . self::TOKEN;
		$tokenized_link = array( 'postParentUrl' => 'https://example.com/' . self::TOKEN ) + self::SNAPSHOT;

		return array(
			'title'             => array(
				array( 'title' => 'Hello ' . self::TOKEN ) + self::SNAPSHOT,
				'<!-- wp:campaignbridge/post-title /-->',
				self::CARD . '.innerBlocks[0].snapshot.posts[42].title',
			),
			'excerpt'           => array(
				array( 'excerpt' => 'Read this, ' . self::TOKEN ) + self::SNAPSHOT,
				'<!-- wp:campaignbridge/post-excerpt /-->',
				self::CARD . '.innerBlocks[0].snapshot.posts[42].excerpt',
			),
			'image alt'         => array(
				array( 'image' => $image ) + self::SNAPSHOT,
				'<!-- wp:campaignbridge/post-image /-->',
				self::CARD . '.innerBlocks[0].snapshot.posts[42].image.alt',
			),
			'linked title url'  => array(
				array( 'url' => $tokenized_url ) + self::SNAPSHOT,
				'<!-- wp:campaignbridge/post-title {"linkToPost":true} /-->',
				self::CARD . '.innerBlocks[0].snapshot.posts[42].url',
			),
			'post button url'   => array(
				array( 'url' => $tokenized_url ) + self::SNAPSHOT,
				'<!-- wp:campaignbridge/post-button {"label":"Read more"} /-->',
				self::CARD . '.innerBlocks[0].snapshot.posts[42].url',
			),
			'post link parent'  => array(
				$tokenized_link,
				'<!-- wp:campaignbridge/post-link {"label":"More","destination":"postParent"} /-->',
				self::CARD . '.innerBlocks[0].snapshot.posts[42].postParentUrl',
			),
		);
	}

	public function test_unrendered_snapshot_fields_do_not_fail(): void {
		$image        = self::SNAPSHOT['image'];
		$image['alt'] = self::TOKEN;
		$values       = array(
			'excerpt' => self::TOKEN,
			'image'   => $image,
			'url'     => 'https://example.com/post?name=' . self::TOKEN,
		) + self::SNAPSHOT;

		$result = $this->compile(
			self::card( '<!-- wp:campaignbridge/post-title /--><!-- wp:campaignbridge/post-image {"decorative":true} /-->' ),
			$values
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringNotContainsString( '{{cb:', $result->html() . $result->text() );
	}

	public function test_non_campaignbridge_braces_in_snapshot_content_remain_valid(): void {
		$literal = 'Use {{ braces }}, {cb:x}, {{ cb:x }}, {{CB:x}} and *|FNAME|*';
		$result  = $this->compile(
			self::card( '<!-- wp:campaignbridge/post-title /--><!-- wp:campaignbridge/post-excerpt /-->' ),
			array(
				'title'   => $literal,
				'excerpt' => $literal,
			) + self::SNAPSHOT
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertSame( 2, substr_count( $result->text(), $literal ) );
		self::assertStringNotContainsString( '{{cb:', $result->html() . $result->text() );
	}

	public function test_post_card_compilation_is_unchanged_by_the_provenance_check(): void {
		$result = $this->compile(
			self::card(
				'<!-- wp:campaignbridge/post-image {"linkToPost":true} /-->'
				. '<!-- wp:campaignbridge/post-title {"linkToPost":true} /-->'
				. '<!-- wp:campaignbridge/post-excerpt /-->'
				. '<!-- wp:campaignbridge/post-link {"label":"Read the post"} /-->'
				. '<!-- wp:campaignbridge/post-button {"label":"Read more"} /-->'
			)
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertSame( array(), $result->diagnostics() );
		self::assertStringContainsString( 'alt="Snapshot alt"', $result->html() );
		self::assertStringContainsString( 'Snapshot title</a></h2>', $result->html() );
		self::assertStringContainsString( 'Snapshot excerpt</p>', $result->html() );
		self::assertStringContainsString( "Snapshot title\nSnapshot excerpt\nRead the post: https://example.com/post\nRead more: https://example.com/post", $result->text() );
	}

	public function test_every_canonical_token_in_the_artifact_was_authored_in_a_token_attribute(): void {
		$authored = '<!-- wp:paragraph --><p>Hi ' . self::TOKEN . ' <a href="{{cb:campaign.unsubscribe_url}}">leave</a></p><!-- /wp:paragraph -->'
			. '<!-- wp:heading --><h2 class="wp-block-heading">For {{cb:subscriber.email}}</h2><!-- /wp:heading -->';
		$result   = $this->compile(
			$authored . self::card( '<!-- wp:campaignbridge/post-title /--><!-- wp:campaignbridge/post-excerpt /--><!-- wp:campaignbridge/post-button {"label":"Read more"} /-->' )
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );

		$expected = array( '{{cb:campaign.unsubscribe_url}}', '{{cb:subscriber.email}}', self::TOKEN );
		foreach ( array( $result->html(), $result->text() ) as $artifact ) {
			preg_match_all( '/\{\{cb:[^{}]*\}\}/', $artifact, $matches );
			$found = array_values( array_unique( $matches[0] ) );
			sort( $found );
			self::assertSame( $expected, $found );
		}
	}

	public function test_snapshot_diagnostic_never_echoes_the_token_or_content(): void {
		$result = $this->compile(
			self::card( '<!-- wp:campaignbridge/post-title /-->' ),
			array( 'title' => 'Secret Alice ' . self::TOKEN ) + self::SNAPSHOT
		);

		$json = (string) wp_json_encode( array_map( static fn ( $diagnostic ): array => $diagnostic->to_array(), $result->diagnostics() ) );
		self::assertFalse( $result->is_success() );
		foreach ( array( 'Secret', 'Alice', 'subscriber', 'first_name', '{{' ) as $fragment ) {
			self::assertStringNotContainsString( $fragment, $json );
		}
	}

	/**
	 * Wrap post-card children in a card bound to post 42.
	 *
	 * @param string $children Child block markup.
	 */
	private static function card( string $children ): string {
		return '<!-- wp:campaignbridge/post-card {"postId":42} -->' . $children . '<!-- /wp:campaignbridge/post-card -->';
	}

	/**
	 * Compile markup inside the standard container and section.
	 *
	 * @param string               $markup Block markup.
	 * @param array<string, mixed> $values Snapshot values.
	 */
	private function compile( string $markup, array $values = self::SNAPSHOT ): Compile_Result {
		$context = new Render_Context(
			array(
				'title'        => 'Provenance fixture',
				'language'     => 'en',
				'token_values' => array( 'cb:organization.name' => 'Example Company' ),
			),
			array( 'posts' => array( '42' => Post_Snapshot::create( 42, 'post', $values ) ) )
		);

		return Compiler_Factory::create()->compile(
			parse_blocks( '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->' . $markup . '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->' ),
			$context
		);
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
