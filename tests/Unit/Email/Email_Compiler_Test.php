<?php
/**
 * Deterministic email compiler tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Renderer_Registry;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Renderer\Container_Renderer;
use PHPUnit\Framework\TestCase;

final class Email_Compiler_Test extends TestCase {
	public function test_compiles_native_document_to_golden_html_and_text(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertSame( $this->fixture( 'post-card.html' ), $result->html() );
		self::assertSame( $this->fixture( 'post-card.txt' ), $result->text() );
		self::assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', $result->fingerprint() );
		self::assertSame( '2', $result->compiler_version() );
		self::assertSame( 'universal@1', $result->profile_version() );
		self::assertSame(
			array(
				array(
					'type'   => 'image',
					'url'    => 'https://example.com/image.jpg',
					'width'  => 600,
					'height' => 400,
					'alt'    => 'A "safe" image',
				),
			),
			$result->assets()
		);
	}

	public function test_fingerprint_is_stable_for_equivalent_map_order(): void {
		$compiler = Compiler_Factory::create();
		$first    = $compiler->compile( $this->document(), $this->context() );
		$second   = $compiler->compile(
			$this->document(),
			new Render_Context(
				array(
					'language'         => 'en',
					'background_color' => '#f4f4f4',
					'title'            => 'Compiler fixture',
				),
				array(
					'posts' => array(
						'42' => array(
							'parentUrl' => 'https://example.com/parent-page',
							'url'       => 'https://example.com/posts/42',
							'excerpt'   => '<strong>This</strong> excerpt has safe text.',
							'title'     => 'Enterprise & safe',
							'id'        => 42,
							'image'     => array(
								'height' => 400,
								'alt'    => 'A "safe" image',
								'url'    => 'https://example.com/image.jpg',
								'width'  => 600,
							),
						),
					),
				)
			)
		);

		self::assertSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_rejects_unsupported_core_block_without_partial_artifact(): void {
		$document                      = $this->document();
		$document[0]['innerBlocks'][0] = array(
			'blockName'   => 'core/paragraph',
			'attrs'       => array(),
			'innerBlocks' => array(),
		);
		$result                        = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
		self::assertSame( '', $result->text() );
		self::assertSame( '', $result->fingerprint() );
		self::assertSame( array(), $result->assets() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
		self::assertSame( 'blocks[0].innerBlocks[0]', $result->diagnostics()[0]->path() );
	}

	public function test_rejects_unknown_attributes(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['attrs']['slotId'] = 'prototype';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attributes.unsupported', $result->diagnostics()[0]->code() );
	}

	public function test_requires_immutable_post_snapshot(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), new Render_Context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.snapshot.missing', $result->diagnostics()[0]->code() );
	}

	public function test_post_cta_can_target_immutable_parent_page(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3]['attrs']['destination'] = 'parent';
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'href="https://example.com/parent-page"', $result->html() );
		self::assertStringContainsString( 'Read more: https://example.com/parent-page', $result->text() );
	}

	public function test_post_cta_rejects_parent_target_without_snapshot_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3]['attrs']['destination'] = 'parent';
		$result = Compiler_Factory::create()->compile( $document, $this->context( false ) );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.cta.parent_url_missing', $result->diagnostics()[0]->code() );
	}

	public function test_rejects_documents_over_block_budget(): void {
		$document                   = $this->document();
		$document[0]['innerBlocks'] = array_fill( 0, 501, $document[0]['innerBlocks'][0] );
		$result                     = Compiler_Factory::create()->compile( $document, $this->context() );
		$codes                      = array_map(
			static fn ( $diagnostic ): string => $diagnostic->code(),
			$result->diagnostics()
		);

		self::assertFalse( $result->is_success() );
		self::assertContains( 'document.blocks.exceeded', $codes );
	}

	public function test_rejects_documents_over_depth_budget(): void {
		$nested = array(
			'blockName'   => 'campaignbridge/post-title',
			'attrs'       => array(),
			'innerBlocks' => array(),
		);

		for ( $depth = 0; $depth < 21; ++$depth ) {
			$nested = array(
				'blockName'   => 'campaignbridge/post-card',
				'attrs'       => array( 'postId' => 42 ),
				'innerBlocks' => array( $nested ),
			);
		}

		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array( $nested ),
			),
		);
		$result   = Compiler_Factory::create()->compile( $document, $this->context() );
		$codes    = array_map(
			static fn ( $diagnostic ): string => $diagnostic->code(),
			$result->diagnostics()
		);

		self::assertFalse( $result->is_success() );
		self::assertContains( 'document.depth.exceeded', $codes );
	}

	public function test_accepts_whitespace_emitted_by_wordpress_parser(): void {
		$serialized = "\n<!-- wp:campaignbridge/container -->\n"
			. '<!-- wp:campaignbridge/post-card {"postId":42,"postType":"post"} -->' . "\n"
			. '<!-- wp:campaignbridge/post-title {"level":2} /-->' . "\n"
			. '<!-- /wp:campaignbridge/post-card -->' . "\n"
			. '<!-- /wp:campaignbridge/container -->' . "\n";
		$result     = Compiler_Factory::create()->compile( parse_blocks( $serialized ), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'Enterprise &amp; safe', $result->html() );
	}

	public function test_rejects_non_whitespace_freeform_html(): void {
		$result = Compiler_Factory::create()->compile( parse_blocks( '<p>Unsafe browser markup</p>' ), $this->context() );
		$codes  = array_map(
			static fn ( $diagnostic ): string => $diagnostic->code(),
			$result->diagnostics()
		);

		self::assertFalse( $result->is_success() );
		self::assertContains( 'block.freeform.unsupported', $codes );
		self::assertSame( '', $result->html() );
	}

	public function test_rejects_unknown_profile_as_a_compiler_diagnostic(): void {
		$context = new Render_Context( array(), array(), array(), 'future@1' );
		$result  = Compiler_Factory::create()->compile( $this->document(), $context );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'profile.unsupported', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	public function test_renderer_registry_rejects_duplicate_ownership(): void {
		$this->expectException( \DomainException::class );
		$this->expectExceptionMessage( 'Duplicate email renderer: campaignbridge/container' );

		new Renderer_Registry( array( new Container_Renderer(), new Container_Renderer() ) );
	}

	/** @return array<int, array<string, mixed>> */
	private function document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(
					'maxWidth' => 600,
					'style'    => array(
						'color' => array(
							'background' => '#ffffff',
							'text'       => '#111111',
						),
					),
				),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/post-card',
						'attrs'       => array(
							'postId'   => 42,
							'postType' => 'post',
						),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/post-image',
								'attrs'       => array(),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/post-title',
								'attrs'       => array( 'level' => 2 ),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/post-excerpt',
								'attrs'       => array( 'maxWords' => 10 ),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/post-cta',
								'attrs'       => array(
									'label'           => 'Read more',
									'backgroundColor' => '#111111',
									'textColor'       => '#ffffff',
								),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);
	}

	private function context( bool $include_parent_url = true ): Render_Context {
		$post = array(
			'id'      => 42,
			'title'   => 'Enterprise & safe',
			'excerpt' => '<strong>This</strong> excerpt has safe text.',
			'url'     => 'https://example.com/posts/42',
			'image'   => array(
				'url'    => 'https://example.com/image.jpg',
				'alt'    => 'A "safe" image',
				'width'  => 600,
				'height' => 400,
			),
		);

		if ( $include_parent_url ) {
			$post['parentUrl'] = 'https://example.com/parent-page';
		}

		return new Render_Context(
			array(
				'title'            => 'Compiler fixture',
				'language'         => 'en',
				'background_color' => '#f4f4f4',
			),
			array(
				'posts' => array(
					'42' => $post,
				),
			)
		);
	}

	private function fixture( string $name ): string {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Email/golden/' . $name );
		self::assertIsString( $contents );

		return $contents;
	}
}
