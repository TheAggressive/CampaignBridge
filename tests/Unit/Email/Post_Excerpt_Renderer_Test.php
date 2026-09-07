<?php
/**
 * Post excerpt renderer unit tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Renderer\Renderer_Support;
use PHPUnit\Framework\TestCase;

final class Post_Excerpt_Renderer_Test extends TestCase {
	public function test_captures_the_excerpt_to_the_word_budget(): void {
		$result = Compiler_Factory::create()->compile( $this->document( 10 ), $this->context( 'one two three four five six seven eight nine ten eleven twelve' ) );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'one two three four five six seven eight nine ten…', $result->html() );
	}

	public function test_keeps_a_short_excerpt_unchanged(): void {
		$result = Compiler_Factory::create()->compile( $this->document( 10 ), $this->context( 'one two three' ) );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'one two three</p>', $result->html() );
		self::assertStringNotContainsString( '…</p>', $result->html() );
	}

	public function test_counts_a_trailing_auto_excerpt_ellipsis_as_a_word_not_as_budget(): void {
		$words  = implode( ' ', range( 1, 55 ) );
		$result = Compiler_Factory::create()->compile( $this->document( 50 ), $this->context( $words . '…' ) );

		self::assertTrue( $result->is_success() );
		// Exactly 50 visible words followed by a single ellipsis.
		self::assertStringContainsString( implode( ' ', range( 1, 50 ) ) . '…</p>', $result->html() );
		self::assertStringNotContainsString( '……</p>', $result->html() );
	}

	public function test_truncate_words_strips_markup_entities_and_counts_words(): void {
		self::assertSame( 'one two three…', Renderer_Support::truncate_words( '<p>one&nbsp;two <b>three</b> four</p>', 3 ) );
	}

	/**
	 * Build a post card document with a post excerpt at the given word budget.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function document( int $max_words ): array {
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
								'blockName'   => 'campaignbridge/post-excerpt',
								'attrs'       => array( 'maxWords' => $max_words ),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Build a context carrying one post snapshot with the given excerpt.
	 *
	 * @param string $excerpt Snapshot excerpt copy.
	 */
	private function context( string $excerpt ): Render_Context {
		return new Render_Context(
			array( 'title' => 'Post excerpt fixture' ),
			array(
				'posts' => array(
					'7' => array(
						'title'   => 'Snapshot title',
						'excerpt' => $excerpt,
						'url'     => 'https://example.com/posts/7',
					),
				),
			),
			array(),
			'universal@1'
		);
	}
}