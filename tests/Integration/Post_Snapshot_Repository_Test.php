<?php
/**
 * Post snapshot repository integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Email\Post_Snapshot;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Email\Template_Preview;

final class Post_Snapshot_Repository_Test extends Test_Case {
	private Post_Snapshot_Repository $repository;

	public function set_up(): void {
		parent::set_up();
		$this->repository = new Post_Snapshot_Repository();
	}

	public function test_published_post_resolves_to_post_snapshot(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Hello World',
				'post_status' => 'publish',
			)
		);

		$result = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );

		$this->assertArrayHasKey( (string) $post_id, $result );
		$this->assertInstanceOf( Post_Snapshot::class, $result[ (string) $post_id ] );
	}

	public function test_source_id_is_correct(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'ID check',
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$this->assertSame( $post_id, $snapshot->source_id() );
	}

	public function test_source_post_type_is_correct(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Type check',
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$this->assertSame( 'post', $snapshot->source_post_type() );
	}

	public function test_title_resolves(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'The Title',
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$this->assertSame( 'The Title', $snapshot->get( 'title' ) );
	}

	public function test_empty_title_is_allowed(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => '',
				'post_status' => 'publish',
			)
		);

		$result = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );

		$this->assertArrayHasKey( (string) $post_id, $result );
		$this->assertSame( '', $result[ (string) $post_id ]->get( 'title' ) );
	}

	public function test_excerpt_resolves(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Excerpt test',
				'post_excerpt' => 'A manual excerpt.',
				'post_status'  => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$this->assertSame( 'A manual excerpt.', $snapshot->get( 'excerpt' ) );
	}

	public function test_empty_excerpt_is_allowed(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'No excerpt',
				'post_excerpt' => '',
				'post_content' => '',
				'post_status'  => 'publish',
			)
		);

		$result = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );

		$this->assertArrayHasKey( (string) $post_id, $result );
		$this->assertSame( '', $result[ (string) $post_id ]->get( 'excerpt' ) );
	}

	public function test_permalink_resolves(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Permalink test',
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$url = $snapshot->get( 'url' );
		$this->assertIsString( $url );
		$this->assertStringStartsWith( 'http', $url );
	}

	public function test_featured_image_resolves_with_url_alt_and_dimensions(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Image test',
				'post_status' => 'publish',
			)
		);

		$attachment_id = $this->factory->attachment->create_object(
			array(
				'post_mime_type' => 'image/png',
				'post_parent'    => $post_id,
			)
		);

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Test alt text' );
		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );

		// Provide a deterministic image source so the fixture is reliable
		// regardless of file-system state in the test environment.
		$image_src_filter = static function () {
			return array( 'https://example.com/uploads/test-image.png', 800, 600, false );
		};
		add_filter( 'wp_get_attachment_image_src', $image_src_filter, 10, 3 );

		try {
			$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
			$snapshot = $result[ (string) $post_id ];

			$this->assertTrue( $snapshot->has( 'image' ), 'Snapshot must contain an image binding when a featured image is set.' );

			$image = $snapshot->get( 'image' );
			$this->assertIsArray( $image );
			$this->assertSame( 'https://example.com/uploads/test-image.png', $image['url'] );
			$this->assertSame( 'Test alt text', $image['alt'] );
			$this->assertSame( 800, $image['width'] );
			$this->assertSame( 600, $image['height'] );
		} finally {
			remove_filter( 'wp_get_attachment_image_src', $image_src_filter, 10 );
		}
	}

	public function test_missing_image_is_allowed(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'No image',
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$this->assertFalse( $snapshot->has( 'image' ) );
	}

	public function test_parent_url_resolves_when_applicable(): void {
		$parent_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Parent post',
				'post_status' => 'publish',
			)
		);

		$child_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Child post',
				'post_parent' => $parent_id,
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $child_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $child_id ];

		$this->assertTrue( $snapshot->has( 'postParentUrl' ) );
		$this->assertSame( get_permalink( $parent_id ), $snapshot->get( 'postParentUrl' ) );
	}

	public function test_archive_url_resolves_when_applicable(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Archive test',
				'post_status' => 'publish',
			)
		);

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		$this->assertTrue( $snapshot->has( 'postTypeArchiveUrl' ) );
		$this->assertSame( get_post_type_archive_link( 'post' ), $snapshot->get( 'postTypeArchiveUrl' ) );
	}

	public function test_wrong_requested_post_type_is_omitted(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Type mismatch',
				'post_status' => 'publish',
			)
		);

		$result = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'page' ) ) );

		$this->assertArrayNotHasKey( (string) $post_id, $result );
	}

	public function test_unresolvable_post_is_not_represented(): void {
		$result = $this->repository->posts( array( array( 'id' => 99999999, 'type' => 'post' ) ) );

		$this->assertArrayNotHasKey( '99999999', $result );
	}

	public function test_draft_post_is_omitted(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Draft post',
				'post_status' => 'draft',
			)
		);

		$result = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );

		$this->assertArrayNotHasKey( (string) $post_id, $result );
	}

	public function test_arbitrary_post_meta_does_not_enter_the_snapshot(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Meta test',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'secret_meta', 'sensitive-data' );
		update_post_meta( $post_id, 'another_meta', 'more-data' );

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		// Arbitrary post meta keys must be absent from the snapshot.
		$values = $snapshot->to_array()['values'];
		$this->assertArrayNotHasKey( 'secret_meta', $values, 'Arbitrary meta key "secret_meta" must not appear in the snapshot.' );
		$this->assertArrayNotHasKey( 'another_meta', $values, 'Arbitrary meta key "another_meta" must not appear in the snapshot.' );

		// The snapshot must contain only the canonical allowlisted fields.
		$allowed_fields = array( 'title', 'excerpt', 'content', 'url', 'image', 'postParentUrl', 'postTypeArchiveUrl' );

		foreach ( array_keys( $values ) as $key ) {
			$this->assertContains( $key, $allowed_fields, sprintf( 'Snapshot contains non-canonical field "%s".', $key ) );
		}

		// For this fixture (no thumbnail, no parent), only these fields should be present.
		$expected_keys = array( 'title', 'excerpt', 'content', 'url', 'postTypeArchiveUrl' );
		$this->assertSame( $expected_keys, array_keys( $values ) );
	}

	public function test_multiple_references_preserve_deterministic_order(): void {
		$post_a = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Post A',
				'post_status' => 'publish',
			)
		);
		$post_b = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Post B',
				'post_status' => 'publish',
			)
		);

		$references = array(
			array( 'id' => $post_a, 'type' => 'post' ),
			array( 'id' => $post_b, 'type' => 'post' ),
		);

		$result = $this->repository->posts( $references );

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( (string) $post_a, $result );
		$this->assertArrayHasKey( (string) $post_b, $result );

		$second = $this->repository->posts( $references );
		$this->assertSame(
			array_map( static fn( Post_Snapshot $s ) => $s->to_array(), array_values( $result ) ),
			array_map( static fn( Post_Snapshot $s ) => $s->to_array(), array_values( $second ) )
		);
	}

	/**
	 * Production compatibility: Template_Preview → Post_Snapshot_Repository → Post_Snapshot → compiler.
	 */
	public function test_production_path_preview_repository_snapshot_compiler(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Production test post',
				'post_excerpt' => 'A real excerpt for the production path.',
				'post_status'  => 'publish',
			)
		);

		$preview = new Template_Preview( new Post_Snapshot_Repository() );

		$result = $preview->compile(
			'<!-- wp:campaignbridge/container -->'
			. '<!-- wp:campaignbridge/post-card {"postId":' . $post_id . ',"postType":"post"} -->'
			. '<!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"title"}}}}} --><h2></h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"excerpt"}}}}} --><p></p><!-- /wp:paragraph -->'
			. '<!-- /wp:campaignbridge/post-card -->'
			. '<!-- /wp:campaignbridge/container -->'
		);

		$this->assertTrue(
			$result->is_success(),
			'Production path should compile successfully: ' . wp_json_encode( array_map( static fn( $d ) => $d->to_array(), $result->diagnostics() ) )
		);
		$this->assertStringContainsString( 'Production test post', $result->html() );
		$this->assertStringContainsString( 'A real excerpt for the production path.', $result->html() );
	}
}
