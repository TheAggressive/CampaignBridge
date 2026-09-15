<?php
/**
 * Typed post renderer binding tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Renderer\Post_Card_Renderer;
use PHPUnit\Framework\TestCase;

final class Post_Snapshot_Renderer_Test extends TestCase {
	public function test_card_scopes_the_same_object_and_sibling_scopes_do_not_leak(): void {
		$first = $this->post( 7, 'First title' );
		$second = $this->post( 9, 'Second title' );
		$context = new Render_Context( array(), array( 'posts' => array( 7 => $first, 9 => $second ) ) );
		$renderer = new Post_Card_Renderer();
		$first_scope = $renderer->context_for_children( $renderer->normalize( new Block_Node( 'campaignbridge/post-card', array( 'postId' => 7 ), array(), 'blocks[0]' ) ), $context );
		$second_scope = $renderer->context_for_children( $renderer->normalize( new Block_Node( 'campaignbridge/post-card', array( 'postId' => 9 ), array(), 'blocks[1]' ) ), $context );

		self::assertSame( $first, $first_scope->post_binding() );
		self::assertSame( $second, $second_scope->post_binding() );
		self::assertNull( $context->post_binding() );
		self::assertNull( $first_scope->binding( 'post' ) );
		self::assertSame( $context->fingerprint_payload(), $first_scope->fingerprint_payload() );
		$result = Compiler_Factory::create()->compile( parse_blocks( $this->document( array( 7, 9 ) ) ), $context );
		self::assertTrue( $result->is_success() );
		self::assertStringContainsString( 'First title', $result->html() );
		self::assertStringContainsString( 'Second title', $result->html() );
		self::assertLessThan( strpos( $result->text(), 'Second title' ), strpos( $result->text(), 'First title' ) );
	}

	public function test_an_ad_hoc_array_cannot_substitute_for_a_canonical_snapshot(): void {
		$context = new Render_Context( array(), array( 'posts' => array( 7 => array( 'title' => 'Raw', 'excerpt' => '', 'url' => 'https://example.com/7' ) ) ) );
		$result = Compiler_Factory::create()->compile( parse_blocks( $this->document( array( 7 ) ) ), $context );
		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.snapshot.missing', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	public function test_wrong_source_identity_and_unsupported_binding_fail_with_stable_diagnostics(): void {
		$context = new Render_Context( array(), array( 'posts' => array( 7 => $this->post( 9, 'Wrong source' ) ) ) );
		$result = Compiler_Factory::create()->compile( parse_blocks( $this->document( array( 7 ) ) ), $context );
		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.snapshot.mismatch', $result->diagnostics()[0]->code() );

		$blocks = parse_blocks( $this->document( array( 7 ) ) );
		$blocks[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['binding'] = 'secret_meta';
		$context = new Render_Context( array(), array( 'posts' => array( 7 => $this->post( 7, 'Title' ) ) ) );
		$result = Compiler_Factory::create()->compile( $blocks, $context );
		self::assertFalse( $result->is_success() );
		self::assertSame( 'block.attributes.unsupported', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
	}

	private function post( int $id, string $title ): Post_Snapshot {
		return Post_Snapshot::create( $id, 'post', array( 'title' => $title, 'excerpt' => 'Excerpt', 'url' => 'https://example.com/' . $id ) );
	}

	private function document( array $ids ): string {
		$content = '<!-- wp:campaignbridge/container -->';
		foreach ( $ids as $id ) {
			$content .= '<!-- wp:campaignbridge/post-card {"postId":' . $id . '} -->'
				. '<!-- wp:campaignbridge/post-title /--><!-- wp:campaignbridge/post-link /-->'
				. '<!-- /wp:campaignbridge/post-card -->';
		}
		return $content . '<!-- /wp:campaignbridge/container -->';
	}
}
