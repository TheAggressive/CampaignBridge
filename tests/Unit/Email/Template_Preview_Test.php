<?php
/**
 * Preview compilation and snapshot reference tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Post_Snapshot_Source;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;
use CampaignBridge\Workflow\Email\Email_Compiler;
use CampaignBridge\Workflow\Email\Snapshot_References;
use CampaignBridge\Workflow\Email\Template_Preview;
use PHPUnit\Framework\TestCase;

final class Template_Preview_Test extends TestCase {
	public function test_collects_each_distinct_post_binding_once(): void {
		$blocks = parse_blocks(
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":7,"postType":"post"} /-->'
			. '<!-- wp:campaignbridge/post-card {"postId":7,"postType":"post"} /-->'
			. '<!-- wp:campaignbridge/post-card {"postId":9,"postType":"page"} /-->'
			. '<!-- /wp:campaignbridge/container -->'
		);

		$references = Snapshot_References::collect( $blocks );

		self::assertCount( 2, $references );
		self::assertContains(
			array(
				'id'   => 7,
				'type' => 'post',
			),
			$references 
		);
		self::assertContains(
			array(
				'id'   => 9,
				'type' => 'page',
			),
			$references 
		);
	}

	public function test_ignores_a_card_with_no_post_selected_yet(): void {
		$blocks = parse_blocks( '<!-- wp:campaignbridge/post-card {"postId":0} /-->' );

		self::assertSame( array(), Snapshot_References::collect( $blocks ) );
	}

	public function test_references_preserve_first_seen_order_and_distinct_post_types(): void {
		$blocks = parse_blocks(
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":9,"postType":"page"} /-->'
			. '<!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/post-card {"postId":7,"postType":"post"} /-->'
			. '<!-- wp:campaignbridge/post-card {"postId":9,"postType":"page"} /-->'
			. '<!-- wp:campaignbridge/post-card {"postId":7,"postType":"page"} /-->'
			. '<!-- /wp:campaignbridge/section -->'
			. '<!-- /wp:campaignbridge/container -->'
		);

		self::assertSame(
			array(
				array( 'id' => 9, 'type' => 'page' ),
				array( 'id' => 7, 'type' => 'post' ),
				array( 'id' => 7, 'type' => 'page' ),
			),
			Snapshot_References::collect( $blocks )
		);
	}

	public function test_preview_preserves_canonical_snapshot_data_through_the_compiler(): void {
		$content = '<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":7,"postType":"post"} -->'
			. '<!-- wp:campaignbridge/post-title /-->'
			. '<!-- /wp:campaignbridge/post-card -->'
			. '<!-- /wp:campaignbridge/container -->';
		$values = array(
			'title'   => 'Snapshot title',
			'excerpt' => 'Snapshot excerpt.',
			'url'     => 'https://example.com/posts/7',
		);
		$snapshot = Post_Snapshot::create( 7, 'post', $values );
		$source = $this->createMock( Post_Snapshot_Source::class );
		$source->expects( self::once() )
			->method( 'posts' )
			->with( array( array( 'id' => 7, 'type' => 'post' ) ) )
			->willReturn( array( 7 => $snapshot ) );
		$kit = Brand_Kit::defaults();
		$metadata = array( 'title' => 'Canonical preview' );
		$actual = ( new Template_Preview( $source, $kit ) )->compile( $content, $metadata );
		$expected = Compiler_Factory::create( Email_Design_Factory::resolve( $kit ) )->compile(
			parse_blocks( $content ),
			new Render_Context(
				array_merge( array( 'brandKit' => $kit ), $metadata ),
				array( 'posts' => array( 7 => $snapshot ) ),
				array(),
				Email_Compiler::PROFILE_VERSION
			)
		);

		self::assertTrue( $actual->is_success() );
		self::assertTrue( $expected->is_success() );
		self::assertSame( $expected->html(), $actual->html() );
		self::assertSame( $expected->text(), $actual->text() );
		self::assertSame( $expected->fingerprint(), $actual->fingerprint() );

		// Source identity must survive preview even when the rendered values match.
		$other_source = $this->createMock( Post_Snapshot_Source::class );
		$other_source->method( 'posts' )->willReturn( array( 7 => Post_Snapshot::create( 7, 'page', $values ) ) );
		$other = ( new Template_Preview( $other_source, $kit ) )->compile( $content, $metadata );

		self::assertTrue( $other->is_success() );
		self::assertSame( $actual->html(), $other->html() );
		self::assertSame( $actual->text(), $other->text() );
		self::assertNotSame( $actual->fingerprint(), $other->fingerprint() );
	}

	public function test_finds_bindings_nested_below_layout_blocks(): void {
		$blocks = parse_blocks(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/columns --><!-- wp:campaignbridge/column -->'
			. '<!-- wp:campaignbridge/post-card {"postId":4,"postType":"post"} /-->'
			. '<!-- /wp:campaignbridge/column --><!-- /wp:campaignbridge/columns -->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->'
		);

		self::assertSame(
			array(
				array(
					'id'   => 4,
					'type' => 'post',
				),
			),
			Snapshot_References::collect( $blocks )
		);
	}

	public function test_compiles_serialized_content_into_the_canonical_artifact(): void {
		$result = $this->preview()->compile(
			'<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/heading {"content":"Hello","level":2} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
			array( 'title' => 'Preview' )
		);

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<!doctype html>', $result->html() );
		self::assertStringContainsString( 'Hello', $result->html() );
		self::assertSame( 'universal@1', $result->profile_version() );
		self::assertNotSame( '', $result->fingerprint() );
	}

	public function test_whitespace_between_blocks_is_not_an_unsupported_block(): void {
		$result = $this->preview()->compile(
			"<!-- wp:campaignbridge/container -->\n\n"
			. "<!-- wp:campaignbridge/section -->\n"
			. "<!-- wp:campaignbridge/spacer {\"height\":8} /-->\n"
			. "<!-- /wp:campaignbridge/section -->\n\n"
			. '<!-- /wp:campaignbridge/container -->'
		);

		self::assertTrue( $result->is_success() );
	}

	public function test_resolves_the_snapshot_a_post_card_binds_to(): void {
		$result = $this->preview()->compile(
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":7,"postType":"post"} -->'
			. '<!-- wp:campaignbridge/post-title /-->'
			. '<!-- /wp:campaignbridge/post-card -->'
			. '<!-- /wp:campaignbridge/container -->'
		);

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'Snapshot title', $result->html() );
	}

	public function test_reports_a_missing_snapshot_rather_than_inventing_one(): void {
		$result = $this->preview()->compile(
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":999,"postType":"post"} -->'
			. '<!-- wp:campaignbridge/post-title /-->'
			. '<!-- /wp:campaignbridge/post-card -->'
			. '<!-- /wp:campaignbridge/container -->'
		);

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.snapshot.missing', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	public function test_unsupported_blocks_fail_with_an_actionable_block_path(): void {
		$result = $this->preview()->compile(
			'<!-- wp:campaignbridge/container --><!-- wp:core/paragraph -->'
			. '<p>Nope</p><!-- /wp:core/paragraph --><!-- /wp:campaignbridge/container -->'
		);

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
		self::assertStringContainsString( 'innerBlocks', $result->diagnostics()[0]->path() );
	}

	public function test_the_same_source_and_snapshot_produce_the_same_fingerprint(): void {
		$content = '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/text {"content":"Stable"} /-->'
			. '<!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

		self::assertSame(
			$this->preview()->compile( $content )->fingerprint(),
			$this->preview()->compile( $content )->fingerprint()
		);
	}

	public function test_a_brand_kit_passed_into_the_preview_is_honoured(): void {
		$kit = Brand_Kit::from_colors(
			array(
				'brand' => '#ff0000',
			)
		);
		$preview = new Template_Preview(
			new class() implements Post_Snapshot_Source {
				/**
				 * {@inheritDoc}
				 *
				 * @param array<int, array{id: int, type: string}> $references Requested posts.
				 * @return array<int|string, Post_Snapshot>
				 */
				public function posts( array $references ): array {
					return array();
				}
			},
			$kit
		);

		// The heading's style tree references the "brand" preset slug via the
		// standard `var:preset|color|<slug>` form. With the custom kit,
		// "brand" resolves to #ff0000 instead of the default #1a6dcc.
		$result = $preview->compile(
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/section -->'
			. '<!-- wp:campaignbridge/heading {"content":"Hello","level":2,"style":{"color":{"text":"var:preset|color|brand"}}} /-->'
			. '<!-- /wp:campaignbridge/section -->'
			. '<!-- /wp:campaignbridge/container -->'
		);

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'color:#ff0000', $result->html() );
	}

	/** Build a preview bound to a fixed in-memory snapshot source. */
	private function preview(): Template_Preview {
		return new Template_Preview(
			new class() implements Post_Snapshot_Source {
				/**
				 * {@inheritDoc}
				 *
				 * @param array<int, array{id: int, type: string}> $references Requested posts.
				 * @return array<int|string, Post_Snapshot>
				 */
				public function posts( array $references ): array {
					$snapshots = array();

					foreach ( $references as $reference ) {
						if ( 7 !== $reference['id'] ) {
							continue;
						}

						$snapshots[ (string) $reference['id'] ] = Post_Snapshot::create(
							7,
							'post',
							array(
								'title'   => 'Snapshot title',
								'excerpt' => 'Snapshot excerpt.',
								'url'     => 'https://example.com/posts/7',
							)
						);
					}

					return $snapshots;
				}
			}
		);
	}
}
