<?php
/**
 * List block compiler tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use PHPUnit\Framework\TestCase;

final class List_Renderer_Test extends TestCase {
	public function test_registers_list_renderers_and_contracts(): void {
		$registry = Compiler_Factory::registry();

		self::assertNotNull( $registry->get( 'campaignbridge/list' ) );
		self::assertNotNull( $registry->get( 'campaignbridge/list-item' ) );
		self::assertSame( array( 'campaignbridge/list-item' ), $registry->get( 'campaignbridge/list' )->allowed_children() );
		self::assertSame( array(), $registry->get( 'campaignbridge/list-item' )->allowed_children() );
		self::assertContains( 'campaignbridge/list', $registry->get( 'campaignbridge/section' )->allowed_children() );
		self::assertContains( 'campaignbridge/list', $registry->get( 'campaignbridge/column' )->allowed_children() );
		self::assertNotContains( 'campaignbridge/list', $registry->get( 'campaignbridge/post-card' )->allowed_children() );
	}

	public function test_compiles_unordered_list_to_deterministic_html_and_text(): void {
		$result = $this->compile( false );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<ul style="margin:0;padding:0 0 0 24px"><li style="margin:0 0 8px 0">First item</li><li style="margin:0 0 8px 0"><strong>Second</strong> item</li></ul>', $result->html() );
		self::assertStringContainsString( "- First item\n- Second item\n", $result->text() );
	}

	public function test_compiles_ordered_list_to_deterministic_html_and_text(): void {
		$result = $this->compile( true );

		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( '<ol style="margin:0;padding:0 0 0 24px"><li style="margin:0 0 8px 0">First item</li><li style="margin:0 0 8px 0"><strong>Second</strong> item</li></ol>', $result->html() );
		self::assertStringContainsString( "1. First item\n2. Second item\n", $result->text() );
	}

	public function test_compiles_safe_rich_text_and_rejects_unsafe_rich_text(): void {
		$safe = $this->compile( false, '<a href="https://example.com">Read more</a>' );
		self::assertTrue( $safe->is_success() );
		self::assertStringContainsString( 'href="https://example.com"', $safe->html() );

		$unsafe = $this->compile( false, '<span style="color:red">Unsafe</span>' );
		self::assertFalse( $unsafe->is_success() );
		self::assertSame( 'list-item.content.invalid', $unsafe->diagnostics()[0]->code() );
		self::assertSame( '', $unsafe->html() );
	}

	public function test_rejects_invalid_types_attributes_and_nesting(): void {
		self::assertSame( 'block.attribute.invalid', $this->compile( 'yes' )->diagnostics()[0]->code() );
		self::assertSame( 'block.attributes.unsupported', $this->compile( false, 'First item', array( 'unexpected' => true ) )->diagnostics()[0]->code() );

		$item_outside_list = $this->document( array( $this->item( 'Outside' ) ) );
		$result            = Compiler_Factory::create()->compile( $item_outside_list, new Render_Context() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );

		$nested_list = $this->compile_document( array( $this->list( false, array( $this->list( false, array( $this->item( 'Nested' ) ) ) ) ) ) );
		self::assertSame( 'block.child.unsupported', $nested_list->diagnostics()[0]->code() );
	}

	public function test_rejects_direct_post_card_list_and_core_lists(): void {
		$post_card = array(
			'blockName'   => 'campaignbridge/post-card',
			'attrs'       => array( 'postId' => 42, 'postType' => 'post' ),
			'innerBlocks' => array( $this->list( false, array( $this->item( 'Wrong parent' ) ) ) ),
		);
		$result = Compiler_Factory::create()->compile( $this->document( array( $post_card ) ), new Render_Context() );
		self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );

		foreach ( array( 'core/list', 'core/list-item' ) as $name ) {
			self::assertNull( Compiler_Factory::registry()->get( $name ) );
			$result = Compiler_Factory::create()->compile( $this->document( array( array( 'blockName' => $name, 'attrs' => array(), 'innerBlocks' => array() ) ) ), new Render_Context() );
			self::assertFalse( $result->is_success() );
			self::assertSame( 'block.child.unsupported', $result->diagnostics()[0]->code() );
		}
	}

	public function test_serialized_list_blocks_compile_and_are_deterministic(): void {
		$serialized = '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:campaignbridge/list {"ordered":true} -->'
			. '<!-- wp:campaignbridge/list-item {"content":"First item"} /--><!-- wp:campaignbridge/list-item {"content":"Second item"} /-->'
			. '<!-- /wp:campaignbridge/list --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';
		$first  = Compiler_Factory::create()->compile( parse_blocks( $serialized ), new Render_Context() );
		$second = Compiler_Factory::create()->compile( parse_blocks( $serialized ), new Render_Context() );

		self::assertTrue( $first->is_success() );
		self::assertSame( $first->html(), $second->html() );
		self::assertSame( $first->text(), $second->text() );
		self::assertSame( $first->fingerprint(), $second->fingerprint() );
	}

	/** @param bool|string $ordered */
	private function compile( $ordered, string $content = 'First item', array $extra_attributes = array() ) {
		return $this->compile_document(
			array(
				$this->list(
					$ordered,
					array( $this->item( $content, $extra_attributes ), $this->item( '<strong>Second</strong> item' ) )
				),
			)
		);
	}

	/** @param array<int, array<string, mixed>> $children */
	private function compile_document( array $children ) {
		return Compiler_Factory::create()->compile( $this->document( $children ), new Render_Context() );
	}

	/** @param array<int, array<string, mixed>> $children */
	private function document( array $children ): array {
		return array(
			array(
				'blockName'   => 'campaignbridge/container',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'campaignbridge/section',
						'attrs'       => array(),
						'innerBlocks' => $children,
					),
				),
			),
		);
	}

	/** @param bool $ordered */
	private function list( $ordered, array $children ): array {
		return array(
			'blockName'   => 'campaignbridge/list',
			'attrs'       => is_bool( $ordered ) ? array( 'ordered' => $ordered ) : array( 'ordered' => $ordered ),
			'innerBlocks' => $children,
		);
	}

	private function item( string $content, array $attributes = array() ): array {
		return array(
			'blockName'   => 'campaignbridge/list-item',
			'attrs'       => array_merge( array( 'content' => $content ), $attributes ),
			'innerBlocks' => array(),
		);
	}
}
