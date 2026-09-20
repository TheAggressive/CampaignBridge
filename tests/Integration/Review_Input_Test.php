<?php
/**
 * Production-path frozen content and explicit refresh evidence.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Review_Input;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Email\Template_Preview;

final class Review_Input_Test extends Test_Case {
	public function test_live_edit_refresh_and_deletion_do_not_mutate_frozen_review(): void {
		$id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_title' => 'Reviewed title', 'post_excerpt' => 'Reviewed excerpt' ) );
		$preview = new Template_Preview( new Post_Snapshot_Repository() );
		$frozen = $preview->capture( $this->content( $id ) );
		$original = $preview->compile_frozen( $frozen );
		self::assertTrue( $original->is_success() );
		self::assertTrue( $preview->matches_review( $frozen, 1, $original->fingerprint() ) );

		wp_update_post( array( 'ID' => $id, 'post_title' => 'Edited title', 'post_excerpt' => 'Edited excerpt' ) );
		$this->assert_same_artifact( $original, $preview->compile_frozen( $frozen ) );
		$refreshed = $preview->refresh( $frozen );
		$updated = $preview->compile_frozen( $refreshed );
		self::assertTrue( $updated->is_success() );
		self::assertSame( 2, $refreshed->revision() );
		self::assertStringContainsString( 'Edited title', $updated->html() );
		self::assertNotSame( $original->fingerprint(), $updated->fingerprint() );
		self::assertFalse( $preview->matches_review( $refreshed, 1, $original->fingerprint() ) );
		self::assertFalse( $preview->matches_review( $refreshed, 2, $original->fingerprint() ) );
		self::assertTrue( $preview->matches_review( $refreshed, 2, $updated->fingerprint() ) );
		self::assertSame( $frozen->design(), $refreshed->design() );
		self::assertSame( 'Reviewed title', $frozen->context()->post_snapshot( (string) $id )->get( 'title' ) );

		wp_delete_post( $id, true );
		$this->assert_same_artifact( $original, $preview->compile_frozen( $frozen ) );
		$this->assert_same_artifact( $updated, $preview->compile_frozen( $refreshed ) );
		$missing = $preview->refresh( $refreshed );
		$result = $preview->compile_frozen( $missing );
		self::assertNull( $missing->context()->post_snapshot( (string) $id ) );
		self::assertFalse( $result->is_success() );
		self::assertSame( 'post.snapshot.missing', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->html() );
		self::assertSame( '', $result->fingerprint() );
		self::assertFalse( $preview->matches_review( $missing, 3, '' ) );
	}

	public function test_refresh_without_content_changes_still_requires_a_new_review_revision(): void {
		$id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$preview = new Template_Preview( new Post_Snapshot_Repository() );
		$frozen = $preview->capture( $this->content( $id ) );
		$artifact = $preview->compile_frozen( $frozen );
		$refreshed = $preview->refresh( $frozen );

		$this->assert_same_artifact( $artifact, $preview->compile_frozen( $refreshed ) );
		self::assertFalse( $preview->matches_review( $refreshed, $frozen->revision(), $artifact->fingerprint() ) );
		self::assertTrue( $preview->matches_review( $refreshed, $refreshed->revision(), $artifact->fingerprint() ) );
	}

	public function test_private_and_password_protected_sources_cannot_be_newly_captured(): void {
		$preview = new Template_Preview( new Post_Snapshot_Repository() );
		foreach ( array( array( 'post_status' => 'private' ), array( 'post_status' => 'publish', 'post_password' => 'secret' ), array( 'post_status' => 'draft' ), array( 'post_status' => 'trash' ) ) as $attributes ) {
			$id = $this->factory->post->create( array_merge( array( 'post_title' => 'Restricted content' ), $attributes ) );
			$input = $preview->capture( $this->content( $id ) );
			$result = $preview->compile_frozen( $input );
			self::assertNull( $input->context()->post_snapshot( (string) $id ) );
			self::assertFalse( $result->is_success() );
			self::assertSame( 'post.snapshot.missing', $result->diagnostics()[0]->code() );
			self::assertSame( '', $result->html() );
		}
	}

	public function test_source_becoming_private_does_not_rewrite_a_frozen_snapshot(): void {
		$id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$preview = new Template_Preview( new Post_Snapshot_Repository() );
		$input = $preview->capture( $this->content( $id ) );
		$artifact = $preview->compile_frozen( $input );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );

		$this->assert_same_artifact( $artifact, $preview->compile_frozen( $input ) );
		self::assertNull( $preview->refresh( $input )->context()->post_snapshot( (string) $id ) );
	}

	public function test_frozen_design_is_used_even_by_a_preview_with_a_different_brand_kit(): void {
		$id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$preview = new Template_Preview( new Post_Snapshot_Repository() );
		$input = $preview->capture( $this->content( $id ) );
		$other = new Template_Preview( new Post_Snapshot_Repository(), Brand_Kit::from_colors( array( 'brand' => '#ff0000' ) ) );

		$this->assert_same_artifact( $preview->compile_frozen( $input ), $other->compile_frozen( $input ) );
		self::assertNotSame( $input->design()->fingerprint(), $other->capture( $this->content( $id ) )->design()->fingerprint() );
	}

	public function test_pages_and_public_custom_post_types_use_the_same_repository(): void {
		register_post_type( 'cb_public', array( 'public' => true ) );
		register_post_type( 'cb_hidden', array( 'public' => false ) );
		try {
			$source = new Post_Snapshot_Repository();
			foreach ( array( 'page', 'cb_public', 'cb_hidden' ) as $type ) {
				$id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_type' => $type ) );
				$posts = $source->posts( array( array( 'id' => $id, 'type' => $type ) ) );
				if ( 'cb_hidden' === $type ) {
					self::assertSame( array(), $posts );
				} else {
					self::assertSame( $type, $posts[ $id ]->source_post_type() );
				}
			}
		} finally {
			unregister_post_type( 'cb_public' );
			unregister_post_type( 'cb_hidden' );
		}
	}

	public function test_unavailable_captured_compiler_fails_closed(): void {
		$preview = new Template_Preview( new Post_Snapshot_Repository() );
		$input = $preview->capture( '<!-- wp:campaignbridge/container /-->' );
		$unsupported = new Review_Input( $input->content(), $input->blocks(), $input->context(), $input->design(), 1, 'unavailable' );
		$result = $preview->compile_frozen( $unsupported );
		self::assertFalse( $result->is_success() );
		self::assertSame( 'snapshot.compiler.unsupported', $result->diagnostics()[0]->code() );
		self::assertSame( '', $result->fingerprint() );
	}

	private function content( int $id ): string {
		return '<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":' . $id . '} -->'
			. '<!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"title"}}}}} --><h2></h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"excerpt"}}}}} --><p></p><!-- /wp:paragraph -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons">'
			. '<!-- wp:button {"metadata":{"bindings":{"url":{"source":"campaignbridge/post-data","args":{"field":"url"}}}}} -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Read more</a></div>'
			. '<!-- /wp:button --></div><!-- /wp:buttons -->'
			. '<!-- /wp:campaignbridge/post-card -->'
			. '<!-- /wp:campaignbridge/container -->';
	}

	private function assert_same_artifact( Compile_Result $expected, Compile_Result $actual ): void {
		self::assertTrue( $expected->is_success() );
		self::assertTrue( $actual->is_success() );
		self::assertSame( $expected->html(), $actual->html() );
		self::assertSame( $expected->text(), $actual->text() );
		self::assertSame( $expected->assets(), $actual->assets() );
		self::assertSame( $expected->fingerprint(), $actual->fingerprint() );
	}
}
