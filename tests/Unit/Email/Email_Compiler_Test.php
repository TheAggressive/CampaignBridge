<?php
/**
 * Deterministic email compiler tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Renderer_Registry;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Renderer\Container_Renderer;
use CampaignBridge\Workflow\Email\Email_Compiler;
use PHPUnit\Framework\TestCase;

final class Email_Compiler_Test extends TestCase {
	public function test_compiles_native_document_to_golden_html_and_text(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertSame( $this->fixture( 'post-card.html' ), $result->html() );
		self::assertSame( $this->fixture( 'post-card.txt' ), $result->text() );
		self::assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', $result->fingerprint() );
		self::assertSame( Email_Compiler::COMPILER_VERSION, $result->compiler_version() );
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

	public function test_post_card_compiles_native_editor_padding_and_background(): void {
		$document                                        = $this->document();
		$document[0]['innerBlocks'][0]['attrs']['style'] = array(
			'spacing' => array(
				'padding' => array(
					'top'    => '0',
					'right'  => 'var:preset|spacing|20',
					'bottom' => '12px',
					'left'   => '0',
				),
			),
			'color'   => array( 'background' => '#abcdef' ),
		);
		$result = Compiler_Factory::create()->compile( $document, $this->context() );
		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'background-color:#abcdef', $result->html() );
		self::assertStringContainsString( '<td style="padding:0px 8px 12px 0px">', $result->html() );
	}

	public function test_links_stable_stylesheet_for_referenced_web_fonts(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['style'] = array(
			'typography' => array( 'fontFamily' => 'var:preset|font-family|inter' ),
		);
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;display=swap">', $result->html() );
		self::assertStringNotContainsString( 'fonts.gstatic.com', $result->html() );
		self::assertStringNotContainsString( '@font-face', $result->text() );
	}

	public function test_system_fonts_emit_no_font_face_css_or_stylesheet_links(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['style'] = array(
			'typography' => array( 'fontFamily' => 'var:preset|font-family|arial' ),
		);
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringNotContainsString( '@font-face', $result->html() );
		self::assertStringNotContainsString( '<link rel="stylesheet"', $result->html() );
	}

	public function test_post_card_rejects_invalid_native_padding(): void {
		$document                                        = $this->document();
		$document[0]['innerBlocks'][0]['attrs']['style'] = array( 'spacing' => array( 'padding' => array( 'top' => 'bogus' ) ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context() );
		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
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
						'42' => Post_Snapshot::create(
							42,
							'post',
							array(
								'postParentUrl'      => 'https://example.com/parent-page',
								'postTypeArchiveUrl' => 'https://example.com/news',
								'url'                => 'https://example.com/posts/42',
								'excerpt'            => '<strong>This</strong> excerpt has safe text.',
								'content'            => 'The full post body, frozen as plain text.',
								'title'              => 'Enterprise & safe',
								'image'              => array(
									'height' => 400,
									'alt'    => 'A "safe" image',
									'url'    => 'https://example.com/image.jpg',
									'width'  => 600,
								),
							),
						),
					),
				)
			)
		);

		self::assertSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_canonical_snapshot_collection_order_does_not_change_the_artifact(): void {
		$post = $this->context()->post_snapshot( '42' );
		self::assertInstanceOf( Post_Snapshot::class, $post );
		$other = Post_Snapshot::create( 9, 'post', array( 'title' => 'Other', 'excerpt' => '', 'url' => 'https://example.com/9' ) );
		$first = Compiler_Factory::create()->compile(
			$this->document(),
			new Render_Context( array(), array( 'posts' => array( 42 => $post, 9 => $other ) ) )
		);
		$second = Compiler_Factory::create()->compile(
			$this->document(),
			new Render_Context( array(), array( 'posts' => array( 9 => $other, 42 => Post_Snapshot::from_array( $post->to_array() ) ) ) )
		);

		self::assertTrue( $first->is_success() );
		self::assertTrue( $second->is_success() );
		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->text(), $second->text() );
		self::assertSame( $first->assets(), $second->assets() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_changed_canonical_snapshot_changes_the_artifact_fingerprint(): void {
		$context = $this->context();
		$post = $context->post_snapshot( '42' );
		self::assertInstanceOf( Post_Snapshot::class, $post );
		$data = $post->to_array();
		$data['values']['title'] = 'Updated snapshot title';
		$first = Compiler_Factory::create()->compile( $this->document(), $context );
		$second = Compiler_Factory::create()->compile(
			$this->document(),
			new Render_Context(
				$context->fingerprint_payload()['metadata'],
				array( 'posts' => array( 42 => Post_Snapshot::from_array( $data ) ) )
			)
		);

		self::assertTrue( $first->is_success() );
		self::assertTrue( $second->is_success() );
		self::assertStringContainsString( 'Updated snapshot title', $second->html() );
		self::assertNotSame( $first->html(), $second->html() );
		self::assertNotSame( $first->fingerprint(), $second->fingerprint() );
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

	public function test_post_button_can_target_immutable_post_parent(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = self::bound_button( array( 'field' => 'postParentUrl' ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'href="https://example.com/parent-page"', $result->html() );
		self::assertStringContainsString( 'Read more: https://example.com/parent-page', $result->text() );
	}

	public function test_post_button_rejects_post_parent_target_without_snapshot_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = self::bound_button( array( 'field' => 'postParentUrl' ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context( false ) );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.binding.missing', $result->diagnostics()[0]->code() );
	}

	public function test_post_button_can_target_immutable_post_type_archive(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = self::bound_button( array( 'field' => 'postTypeArchiveUrl' ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'href="https://example.com/news"', $result->html() );
		self::assertStringContainsString( 'Read more: https://example.com/news', $result->text() );
	}

	public function test_post_button_rejects_archive_target_without_snapshot_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = self::bound_button( array( 'field' => 'postTypeArchiveUrl' ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context( true, false ) );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.binding.missing', $result->diagnostics()[0]->code() );
	}

	public function test_post_button_can_target_custom_https_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = self::bound_button( null, array( 'url' => 'https://example.com/landing?source=email&campaign=weekly' ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'href="https://example.com/landing?source=email&amp;campaign=weekly"', $result->html() );
		self::assertStringContainsString( 'Read more: https://example.com/landing?source=email&campaign=weekly', $result->text() );
	}

	public function test_post_button_rejects_unsafe_custom_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][3] = self::bound_button( null, array( 'url' => 'javascript:alert(1)' ) );
		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'button.url.invalid', $result->diagnostics()[0]->code() );
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
		$nested = self::bound_title();

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
			. '<!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"title"}}}}} --><h2></h2><!-- /wp:heading -->' . "\n"
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

	/**
	 * A `core/heading` bound read-only to the snapshot title.
	 *
	 * @param bool $linked Whether the title links to the snapshot post.
	 * @param int  $level  Heading level.
	 * @return array<string, mixed>
	 */
	public static function bound_title( bool $linked = false, int $level = 2 ): array {
		return array(
			'blockName'   => 'core/heading',
			'attrs'       => array(
				'level'    => $level,
				'metadata' => array(
					'bindings' => array(
						'content' => array(
							'source' => 'campaignbridge/post-data',
							'args'   => array( 'field' => $linked ? 'titleLink' : 'title' ),
						),
					),
				),
			),
			'innerHTML'   => '<h' . $level . '></h' . $level . '>',
			'innerBlocks' => array(),
		);
	}

	/**
	 * A `core/paragraph` bound read-only to snapshot post text.
	 *
	 * @param int    $max_words Bounded word cap carried by the binding.
	 * @param string $field     Bound field: excerpt or content.
	 * @return array<string, mixed>
	 */
	public static function bound_excerpt( int $max_words = 50, string $field = 'excerpt' ): array {
		return array(
			'blockName'   => 'core/paragraph',
			'attrs'       => array(
				'metadata' => array(
					'bindings' => array(
						'content' => array(
							'source' => 'campaignbridge/post-data',
							'args'   => array(
								'field'    => $field,
								'maxWords' => $max_words,
							),
						),
					),
				),
			),
			'innerHTML'   => '<p></p>',
			'innerBlocks' => array(),
		);
	}

	/**
	 * A `core/buttons` group holding one post-bound `core/button`.
	 *
	 * @param array<string, mixed>|null $binding Binding args, or null for a literal URL.
	 * @param array<string, mixed>      $extra   Additional button attributes.
	 * @return array<string, mixed>
	 */
	public static function bound_button( ?array $binding, array $extra = array() ): array {
		$attributes = $extra + array(
			'text'            => 'Read more',
			'backgroundColor' => '#111111',
			'textColor'       => '#ffffff',
		);
		if ( null !== $binding ) {
			$attributes['metadata'] = array(
				'bindings' => array(
					'url' => array(
						'source' => 'campaignbridge/post-data',
						'args'   => $binding,
					),
				),
			);
		}

		return array(
			'blockName'   => 'core/buttons',
			'attrs'       => array(),
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/button',
					'attrs'       => $attributes,
					'innerBlocks' => array(),
				),
			),
		);
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
							self::bound_title(),
							self::bound_excerpt( 10 ),
							self::bound_button( array( 'field' => 'url' ) ),
						),
					),
				),
			),
		);
	}

	private function context( bool $include_parent_url = true, bool $include_archive_url = true ): Render_Context {
		$post = array(
			'title'   => 'Enterprise & safe',
			'excerpt' => '<strong>This</strong> excerpt has safe text.',
			'content' => 'The full post body, frozen as plain text.',
			'url'     => 'https://example.com/posts/42',
			'image'   => array(
				'url'    => 'https://example.com/image.jpg',
				'alt'    => 'A "safe" image',
				'width'  => 600,
				'height' => 400,
			),
		);

		if ( $include_parent_url ) {
			$post['postParentUrl'] = 'https://example.com/parent-page';
		}

		if ( $include_archive_url ) {
			$post['postTypeArchiveUrl'] = 'https://example.com/news';
		}

		return new Render_Context(
			array(
				'title'            => 'Compiler fixture',
				'language'         => 'en',
				'background_color' => '#f4f4f4',
			),
			array(
				'posts' => array(
					'42' => Post_Snapshot::create( 42, 'post', $post ),
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
