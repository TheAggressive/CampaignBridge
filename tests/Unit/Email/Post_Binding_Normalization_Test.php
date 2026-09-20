<?php
/**
 * Bounded post Block Bindings normalization and snapshot resolution.
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

/**
 * Proves the Core normalizer stays a bounded email grammar rather than a
 * general Block Bindings interpreter, and that compilation resolves bound
 * values only from the immutable snapshot.
 */
final class Post_Binding_Normalization_Test extends TestCase {
	private const BOUND_PATH = 'blocks[0].innerBlocks[0].innerBlocks[0]';

	public function test_a_bound_paragraph_renders_the_snapshot_excerpt(): void {
		$result = $this->compile( array( $this->paragraph() ), 'Snapshot excerpt copy.' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'Snapshot excerpt copy.', $result->html() );
		self::assertStringContainsString( 'Snapshot excerpt copy.', $result->text() );
	}

	public function test_a_bound_excerpt_honours_its_word_cap_in_html_and_text(): void {
		$result = $this->compile( array( $this->paragraph( array( 'field' => 'excerpt', 'maxWords' => 10 ) ) ) );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'One two three four five six seven eight nine ten…', $result->html() );
		self::assertStringNotContainsString( 'eleven', $result->text() );
	}

	public function test_a_bound_excerpt_is_escaped_rather_than_treated_as_rich_text(): void {
		$result = $this->compile( array( $this->paragraph() ), 'Sale: <b>50%</b> off & more' );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'Sale: 50% off &amp; more', $result->html() );
		self::assertStringNotContainsString( '<b>', $result->html() );
	}

	public function test_a_bound_heading_renders_the_snapshot_title(): void {
		$result = $this->compile( array( Email_Compiler_Test::bound_title() ) );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( '>Snapshot title</h2>', $result->html() );
		self::assertStringContainsString( 'Snapshot title', $result->text() );
		self::assertStringNotContainsString( '<a ', $result->html() );
	}

	public function test_a_linked_bound_heading_wraps_the_title_in_the_snapshot_url(): void {
		$result = $this->compile( array( Email_Compiler_Test::bound_title( true ) ) );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		// The anchor repeats the heading's own resolved colour: email clients
		// recolour bare links.
		self::assertStringContainsString(
			'<a href="https://example.com/posts/7" style="color:#111111;text-decoration:none">Snapshot title</a>',
			$result->html()
		);
	}

	public function test_a_linked_bound_heading_reads_both_snapshot_fields(): void {
		$registry = Compiler_Factory::registry()->get( 'core/heading' );
		self::assertNotNull( $registry );

		$plain  = $this->normalized_heading( false );
		$linked = $this->normalized_heading( true );

		self::assertSame( array( 'title' ), $registry->snapshot_fields( $plain ) );
		self::assertSame( array( 'title', 'url' ), $registry->snapshot_fields( $linked ) );
	}

	public function test_a_bound_paragraph_renders_the_snapshot_post_content(): void {
		$result = $this->compile(
			array( Email_Compiler_Test::bound_excerpt( 50, 'content' ) ),
			'Unused excerpt.',
			'One two three four five.'
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'One two three four five.', $result->html() );
		self::assertStringNotContainsString( 'Unused excerpt.', $result->html() );
	}

	public function test_bound_post_content_honours_its_word_cap(): void {
		$result = $this->compile(
			array( Email_Compiler_Test::bound_excerpt( 10, 'content' ) ),
			'Unused excerpt.',
			'One two three four five six seven eight nine ten eleven twelve.'
		);

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'One two three four five six seven eight nine ten…', $result->html() );
		self::assertStringNotContainsString( 'eleven', $result->text() );
	}

	public function test_a_bound_button_renders_the_snapshot_url(): void {
		$result = $this->compile( array( Email_Compiler_Test::bound_button( array( 'field' => 'url' ) ) ) );

		self::assertTrue( $result->is_success(), $this->diagnostics( $result ) );
		self::assertStringContainsString( 'href="https://example.com/posts/7"', $result->html() );
		self::assertStringContainsString( 'Read more: https://example.com/posts/7', $result->text() );
	}

	public function test_a_later_edit_to_the_live_post_cannot_reach_a_compiled_artifact(): void {
		// The compiler is handed only the frozen snapshot, so the same document
		// compiles to a different artifact only when the snapshot itself changes.
		$document = array( $this->paragraph() );
		$first    = $this->compile( $document, 'Snapshot excerpt copy.' );
		$second   = $this->compile( $document, 'Snapshot excerpt copy.' );
		$edited   = $this->compile( $document, 'Live edit that never reaches a sent campaign.' );

		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
		self::assertNotSame( $first->fingerprint(), $edited->fingerprint() );
		self::assertStringNotContainsString( 'Live edit', $first->html() );
	}

	/** @dataProvider rejected_bindings */
	public function test_unsupported_binding_metadata_fails_closed( array $block, string $attribute_path ): void {
		$result = $this->compile( array( $block ) );

		self::assertFalse( $result->is_success() );
		self::assertSame( '', $result->html() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( self::BOUND_PATH . '.attrs.' . $attribute_path, $result->diagnostics()[0]->path() );
	}

	/** @return array<string, array{0: array<string, mixed>, 1: string}> */
	public static function rejected_bindings(): array {
		$paragraph = static function ( mixed $bindings ): array {
			return array(
				'blockName'   => 'core/paragraph',
				'attrs'       => array( 'metadata' => array( 'bindings' => $bindings ) ),
				'innerHTML'   => '<p></p>',
				'innerBlocks' => array(),
			);
		};

		return array(
			'unknown binding source'      => array(
				$paragraph( array( 'content' => array( 'source' => 'core/post-meta', 'args' => array( 'key' => 'secret' ) ) ) ),
				'metadata.bindings.content.source',
			),
			'core post data source'       => array(
				$paragraph( array( 'content' => array( 'source' => 'core/post-data', 'args' => array( 'field' => 'title' ) ) ) ),
				'metadata.bindings.content.source',
			),
			'unsupported field'           => array(
				$paragraph( array( 'content' => array( 'source' => 'campaignbridge/post-data', 'args' => array( 'field' => 'title' ) ) ) ),
				'metadata.bindings.content.args.field',
			),
			'missing field'               => array(
				$paragraph( array( 'content' => array( 'source' => 'campaignbridge/post-data', 'args' => array() ) ) ),
				'metadata.bindings.content.args.field',
			),
			'unsupported attribute'       => array(
				$paragraph( array( 'align' => array( 'source' => 'campaignbridge/post-data', 'args' => array( 'field' => 'excerpt' ) ) ) ),
				'metadata.bindings.align',
			),
			'unknown binding argument'    => array(
				$paragraph( array( 'content' => array( 'source' => 'campaignbridge/post-data', 'args' => array( 'field' => 'excerpt', 'meta' => 'x' ) ) ) ),
				'metadata.bindings.content.args.meta',
			),
			'argument out of range'       => array(
				$paragraph( array( 'content' => array( 'source' => 'campaignbridge/post-data', 'args' => array( 'field' => 'excerpt', 'maxWords' => 9999 ) ) ) ),
				'metadata.bindings.content.args.maxWords',
			),
			'malformed binding'           => array(
				$paragraph( array( 'content' => 'campaignbridge/post-data' ) ),
				'metadata.bindings.content',
			),
			'extra binding property'      => array(
				$paragraph( array( 'content' => array( 'source' => 'campaignbridge/post-data', 'args' => array( 'field' => 'excerpt' ), 'label' => 'Excerpt' ) ) ),
				'metadata.bindings.content',
			),
			'pattern overrides default'   => array(
				$paragraph( array( '__default' => array( 'source' => 'core/pattern-overrides' ) ) ),
				'metadata.bindings.__default.source',
			),
		);
	}

	public function test_a_bound_attribute_cannot_also_carry_authored_content(): void {
		$block                 = $this->paragraph();
		$block['innerHTML']    = '<p>Authored copy</p>';
		$result                = $this->compile( array( $block ) );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame( self::BOUND_PATH . '.attrs.content', $result->diagnostics()[0]->path() );
	}

	public function test_a_binding_on_an_unbindable_core_block_fails_closed(): void {
		// core/image is inside the email grammar and bindable in WordPress, but
		// the CampaignBridge contract does not bind it, so it fails closed.
		$image = array(
			'blockName'   => 'core/image',
			'attrs'       => array(
				'url'      => 'https://example.com/image.jpg',
				'alt'      => 'Image',
				'metadata' => array(
					'bindings' => array(
						'url' => array(
							'source' => 'campaignbridge/post-data',
							'args'   => array( 'field' => 'url' ),
						),
					),
				),
			),
			'innerHTML'   => '',
			'innerBlocks' => array(),
		);

		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => array( $image ),
					),
				),
			),
		);

		$result = Compiler_Factory::create()->compile( $document, new Render_Context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attribute.invalid', $result->diagnostics()[0]->code() );
		self::assertSame(
			'blocks[0].innerBlocks[0].innerBlocks[0].attrs.metadata.bindings.url',
			$result->diagnostics()[0]->path()
		);
	}

	/**
	 * Normalize one bound heading far enough to inspect its snapshot fields.
	 *
	 * @param bool $linked Whether the title links to the snapshot post.
	 */
	private function normalized_heading( bool $linked ): \CampaignBridge\Domain\Email\Block_Node {
		$source = Email_Compiler_Test::bound_title( $linked );
		$node   = new \CampaignBridge\Domain\Email\Block_Node(
			$source['blockName'],
			$source['attrs'],
			array(),
			'blocks[0]',
			$source['innerHTML']
		);

		return ( new \CampaignBridge\Services\Email\Core_Block_Normalizer() )->normalize( $node );
	}

	/**
	 * Build a `core/paragraph` bound to the post excerpt.
	 *
	 * @param array<string, mixed>|null $args Binding arguments.
	 * @return array<string, mixed>
	 */
	private function paragraph( ?array $args = null ): array {
		return array(
			'blockName'   => 'core/paragraph',
			'attrs'       => array(
				'metadata' => array(
					'bindings' => array(
						'content' => array(
							'source' => 'campaignbridge/post-data',
							'args'   => $args ?? array( 'field' => 'excerpt' ),
						),
					),
				),
			),
			'innerHTML'   => '<p></p>',
			'innerBlocks' => array(),
		);
	}

	/**
	 * Compile one post card holding the supplied children.
	 *
	 * @param array<int, array<string, mixed>> $children Card children.
	 * @param string                           $excerpt  Snapshot excerpt.
	 * @param string|null                      $content  Snapshot post content.
	 */
	private function compile( array $children, string $excerpt = 'One two three four five six seven eight nine ten eleven twelve.', ?string $content = null ): Compile_Result {
		$document = array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/post-card',
						'attrs'       => array( 'postId' => 7 ),
						'innerBlocks' => $children,
					),
				),
			),
		);

		return Compiler_Factory::create()->compile(
			$document,
			new Render_Context(
				array(),
				array(
					'posts' => array(
						'7' => Post_Snapshot::create(
							7,
							'post',
							array(
								'title'   => 'Snapshot title',
								'excerpt' => $excerpt,
								'content' => $content ?? 'Snapshot post body.',
								'url'     => 'https://example.com/posts/7',
							)
						),
					),
				)
			)
		);
	}

	/**
	 * Summarize diagnostics for a failed assertion message.
	 *
	 * @param Compile_Result $result Compile result.
	 */
	private function diagnostics( Compile_Result $result ): string {
		return implode(
			', ',
			array_map( static fn ( $diagnostic ): string => $diagnostic->code() . '@' . $diagnostic->path(), $result->diagnostics() )
		);
	}
}
