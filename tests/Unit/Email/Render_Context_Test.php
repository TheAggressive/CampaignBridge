<?php
/**
 * Canonical snapshot ownership and fingerprint tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Workflow\Email\Artifact_Fingerprinter;
use PHPUnit\Framework\TestCase;

final class Render_Context_Test extends TestCase {
	public function test_typed_post_binding_keeps_the_canonical_instance_without_an_array_binding(): void {
		$post    = $this->post();
		$context = new Render_Context( array(), array( 'posts' => array( 7 => $post ) ) );
		$scoped  = $context->with_post_binding( $post );

		self::assertNull( $context->post_binding() );
		self::assertSame( $post, $scoped->post_binding() );
		self::assertSame( $scoped->post_snapshot( '7' ), $scoped->post_binding() );
		self::assertNull( $scoped->binding( 'post' ), 'Typed scope must not create a second values-array binding.' );
		self::assertNull( $scoped->snapshot( 'posts', '7' ) );
		self::assertSame( 'Snapshot title', $scoped->post_binding()->get( 'title' ) );
	}

	public function test_context_copies_preserve_typed_scope_and_generic_bindings_independently(): void {
		$post    = $this->post();
		$other   = $this->post( 9 );
		$context = new Render_Context(
			array( 'title' => 'Original' ),
			array( 'posts' => array( 7 => $post, 9 => $other ) ),
			array( 'section' => array( 'width' => 600 ) ),
			'custom@1'
		);
		$scoped   = $context->with_post_binding( $post );
		$metadata = $scoped->with_metadata( 'title', 'Updated' );
		$generic  = $metadata->with_binding( 'section', array( 'width' => 400 ) );
		$rebound  = $generic->with_post_binding( $other );

		self::assertSame( $post, $metadata->post_binding() );
		self::assertSame( $post, $generic->post_binding() );
		self::assertSame( $other, $rebound->post_binding() );
		self::assertSame( 'Original', $scoped->metadata( 'title' ) );
		self::assertSame( 'Updated', $rebound->metadata( 'title' ) );
		self::assertSame( array( 'width' => 600 ), $scoped->binding( 'section' ) );
		self::assertSame( array( 'width' => 400 ), $rebound->binding( 'section' ) );
		self::assertSame( 'custom@1', $rebound->profile() );
		self::assertSame( $post, $rebound->post_snapshot( '7' ) );
		self::assertSame( $other, $rebound->post_snapshot( '9' ) );
	}

	public function test_adding_replacing_and_clearing_typed_scope_does_not_affect_fingerprints(): void {
		$post    = $this->post();
		$other   = $this->post( 9, 'Different scoped content' );
		$context = new Render_Context( array(), array( 'posts' => array( 7 => $post, 9 => $other ) ) );
		$scoped  = $context->with_post_binding( $post );
		$rebound = $scoped->with_post_binding( $other );
		$cleared = $rebound->with_post_binding( null );
		$hasher  = new Artifact_Fingerprinter();

		self::assertNull( $cleared->post_binding() );
		self::assertSame( $post, $scoped->post_binding() );
		self::assertSame( $other, $rebound->post_binding() );
		self::assertSame( $post, $cleared->post_snapshot( '7' ) );
		self::assertSame( $other, $cleared->post_snapshot( '9' ) );
		foreach ( array( $scoped, $rebound, $cleared ) as $copy ) {
			self::assertSame( $context->fingerprint_payload(), $copy->fingerprint_payload() );
			self::assertSame(
				$hasher->fingerprint( $context->fingerprint_payload() ),
				$hasher->fingerprint( $copy->fingerprint_payload() )
			);
		}
	}

	public function test_retains_the_same_snapshot_through_immutable_context_copies(): void {
		$snapshot = $this->post();
		$posts    = array( 7 => $snapshot );
		$context  = new Render_Context( array(), array( 'posts' => $posts ) );
		$metadata = $context->with_metadata( 'title', 'Preview' );
		$binding  = $context->with_binding( 'section', array( 'width' => 600 ) );
		$posts[7] = $this->post( 7, 'Changed outside the context' );

		self::assertSame( $snapshot, $context->post_snapshot( '7' ) );
		self::assertSame( $snapshot, $metadata->post_snapshot( '7' ) );
		self::assertSame( $snapshot, $binding->post_snapshot( '7' ) );
		self::assertNull( $context->metadata( 'title' ) );
		self::assertNull( $context->binding( 'section' ) );
		self::assertSame( 'Preview', $metadata->metadata( 'title' ) );
		self::assertSame( array( 'width' => 600 ), $binding->binding( 'section' ) );
	}

	public function test_serialized_export_cannot_mutate_the_canonical_snapshot(): void {
		$snapshot = $this->post();
		$context  = new Render_Context( array(), array( 'posts' => array( 7 => $snapshot ) ) );
		$view     = $snapshot->to_array()['values'];

		self::assertSame( $snapshot->to_array()['values'], $view );
		$view['title'] = 'Changed view';

		self::assertSame( $snapshot, $context->post_snapshot( '7' ) );
		self::assertNull( $context->snapshot( 'posts', '7' ) );
		self::assertSame( 'Snapshot title', $snapshot->get( 'title' ) );
	}

	public function test_missing_snapshots_stay_absent(): void {
		$context = new Render_Context();

		self::assertNull( $context->post_snapshot( '7' ) );
		self::assertNull( $context->snapshot( 'posts', '7' ) );
		self::assertNull( $context->snapshot( 'other', '7' ) );
		self::assertSame( array(), $context->fingerprint_payload()['snapshots'] );
	}

	public function test_non_post_collections_and_bindings_keep_their_existing_behavior(): void {
		$record  = array( 'label' => 'Other content', 'items' => array( 'b', 'a' ) );
		$context = new Render_Context(
			array( 'title' => 'Preview' ),
			array( 'other' => array( 'selected' => $record ) ),
			array( 'section' => array( 'width' => 600 ) ),
			'universal@1'
		);

		self::assertSame( $record, $context->snapshot( 'other', 'selected' ) );
		self::assertNull( $context->post_snapshot( 'selected' ) );
		self::assertSame( array( 'width' => 600 ), $context->binding( 'section' ) );
		self::assertSame(
			array(
				'metadata'  => array( 'title' => 'Preview' ),
				'snapshots' => array( 'other' => array( 'selected' => $record ) ),
				'profile'   => 'universal@1',
			),
			$context->fingerprint_payload()
		);
	}

	public function test_fingerprint_uses_the_canonical_payload_instead_of_raw_objects(): void {
		$snapshot = $this->post();
		$context  = new Render_Context( array(), array( 'posts' => array( 7 => $snapshot ) ) );
		$payload  = $context->fingerprint_payload();

		self::assertSame( array( 7 => $snapshot->fingerprint_payload() ), $payload['snapshots']['posts'] );
		array_walk_recursive(
			$payload,
			static function ( $value ): void {
				self::assertFalse( is_object( $value ), 'Fingerprint snapshot data must not contain PHP objects.' );
			}
		);
		self::assertSame( $snapshot, $context->post_snapshot( '7' ) );
	}

	public function test_equivalent_instances_and_reversed_collection_order_have_identical_fingerprint_data(): void {
		$first_post  = $this->post();
		$second_post = Post_Snapshot::create(
			7,
			'post',
			array(
				'url'     => 'https://example.com/posts/7',
				'excerpt' => 'Snapshot excerpt.',
				'title'   => 'Snapshot title',
			)
		);
		$first  = new Render_Context( array(), array( 'posts' => array( 9 => $this->post( 9 ), 7 => $first_post ) ) );
		$second = new Render_Context( array(), array( 'posts' => array( 7 => $second_post, 9 => $this->post( 9 ) ) ) );

		self::assertNotSame( $first_post, $second_post );
		self::assertSame( array( 7, 9 ), array_keys( $first->fingerprint_payload()['snapshots']['posts'] ) );
		self::assertSame( $first->fingerprint_payload(), $second->fingerprint_payload() );
		$fingerprinter = new Artifact_Fingerprinter();
		self::assertSame(
			$fingerprinter->fingerprint( $first->fingerprint_payload() ),
			$fingerprinter->fingerprint( $second->fingerprint_payload() )
		);
	}

	public function test_output_affecting_values_change_fingerprint_data(): void {
		$first  = new Render_Context( array(), array( 'posts' => array( 7 => $this->post() ) ) );
		$second = new Render_Context( array(), array( 'posts' => array( 7 => $this->post( 7, 'Updated title' ) ) ) );

		self::assertNotSame( $first->fingerprint_payload(), $second->fingerprint_payload() );
		$fingerprinter = new Artifact_Fingerprinter();
		self::assertNotSame(
			$fingerprinter->fingerprint( $first->fingerprint_payload() ),
			$fingerprinter->fingerprint( $second->fingerprint_payload() )
		);
	}

	public function test_rejects_a_non_canonical_snapshot_instance_with_equal_data(): void {
		$canonical = $this->post();
		$context   = new Render_Context( array(), array( 'posts' => array( 7 => $canonical ) ) );
		$impostor  = Post_Snapshot::create(
			7,
			'post',
			array(
				'title'   => 'Snapshot title',
				'excerpt' => 'Snapshot excerpt.',
				'url'     => 'https://example.com/posts/7',
			)
		);

		self::assertNotSame( $canonical, $impostor );
		self::assertSame( $canonical->to_array(), $impostor->to_array() );

		try {
			$context->with_post_binding( $impostor );
			self::fail( 'Expected InvalidArgumentException for non-canonical snapshot instance.' );
		} catch ( \InvalidArgumentException $e ) {
			self::assertStringContainsString( 'canonical', $e->getMessage() );
		}
	}

	public function test_rejects_a_snapshot_whose_source_id_is_not_in_the_collection(): void {
		$context = new Render_Context( array(), array( 'posts' => array( 7 => $this->post() ) ) );
		$orphan  = $this->post( 99, 'Orphan post' );

		self::assertNull( $context->post_snapshot( '99' ) );

		try {
			$context->with_post_binding( $orphan );
			self::fail( 'Expected InvalidArgumentException for snapshot with absent source ID.' );
		} catch ( \InvalidArgumentException $e ) {
			self::assertStringContainsString( 'canonical', $e->getMessage() );
		}
	}

	private function post( int $id = 7, string $title = 'Snapshot title' ): Post_Snapshot {
		return Post_Snapshot::create(
			$id,
			'post',
			array(
				'title'   => $title,
				'excerpt' => 'Snapshot excerpt.',
				'url'     => 'https://example.com/posts/' . $id,
			)
		);
	}
}
