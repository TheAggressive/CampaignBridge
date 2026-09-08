<?php
/**
 * Post link renderer unit tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class Post_Link_Renderer_Test extends TestCase {
	public function test_renders_an_inline_link_to_the_article(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString(
			'<a href="https://example.com/posts/7" style="color:#111111;text-decoration:underline">Read more</a>',
			$result->html()
		);
	}

	public function test_renders_with_a_custom_link_color(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['linkColor'] = '#0000ee';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString(
			'<a href="https://example.com/posts/7" style="color:#0000ee;text-decoration:underline">Read more</a>',
			$result->html()
		);
	}

	public function test_renders_a_custom_destination_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['destination'] = 'custom';
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['customUrl']   = 'https://example.com/custom';
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['label']       = 'Go now';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString(
			'<a href="https://example.com/custom" style="color:#111111;text-decoration:underline">Go now</a>',
			$result->html()
		);
	}

	public function test_centres_the_link_with_alignment(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['align'] = 'center';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'align="center"', $result->html() );
	}

	public function test_rejects_an_empty_label(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['label'] = '';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.link.label_empty', $result->diagnostics()[0]->code() );
	}

	public function test_accepts_an_http_custom_url(): void {
		$document = $this->document();
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['destination'] = 'custom';
		$document[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['customUrl']   = 'http://example.com/landing';

		$result = Compiler_Factory::create()->compile( $document, $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'href="http://example.com/landing"', $result->html() );
	}

	public function test_accepts_an_http_snapshot_url_for_an_article_link(): void {
		// HTTP-only development sites emit http:// permalinks; those must render, not fail post.url.invalid.
		$context = new Render_Context(
			array( 'title' => 'Post link fixture' ),
			array(
				'posts' => array(
					'7' => array(
						'title'   => 'Snapshot title',
						'excerpt' => 'Snapshot excerpt copy.',
						'url'     => 'http://localhost:8882/posts/7',
					),
				),
			),
			array(),
			'universal@1'
		);

		$result = Compiler_Factory::create()->compile( $this->document(), $context );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'href="http://localhost:8882/posts/7"', $result->html() );
	}

	public function test_renders_to_plain_text(): void {
		$result = Compiler_Factory::create()->compile( $this->document(), $this->context() );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'Read more: https://example.com/posts/7', $result->text() );
	}

	/**
	 * Build a post card document with a single post link.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function document(): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/post-card',
						'attrs'       => array(
							'postId'   => 7,
							'postType' => 'post',
						),
						'innerBlocks' => array(
							array(
								'blockName'   => 'campaignbridge/post-title',
								'attrs'       => array(),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'campaignbridge/post-link',
								'attrs'       => array(),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);
	}

	/** Build a context carrying one immutable post snapshot. */
	private function context(): Render_Context {
		return new Render_Context(
			array( 'title' => 'Post link fixture' ),
			array(
				'posts' => array(
					'7' => array(
						'title'   => 'Snapshot title',
						'excerpt' => 'Snapshot excerpt copy.',
						'url'     => 'https://example.com/posts/7',
					),
				),
			),
			array(),
			'universal@1'
		);
	}
}
