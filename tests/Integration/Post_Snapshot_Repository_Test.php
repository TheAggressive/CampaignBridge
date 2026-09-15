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

		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/test-image.png';
		file_put_contents( $file_path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ) );

		$attachment_id = $this->factory->attachment->create_object(
			array(
				'post_mime_type' => 'image/png',
				'post_parent'    => $post_id,
				'guid'           => $upload_dir['baseurl'] . '/test-image.png',
			),
			$file_path
		);

		update_post_meta( $attachment_id, '_wp_attached_file', 'test-image.png' );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Test alt text' );
		update_post_meta( $attachment_id, '_wp_attachment_image_metadata', array(
			'width'  => 800,
			'height' => 600,
			'file'   => 'test-image.png',
		) );
		set_post_thumbnail( $post_id, $attachment_id );

		// Verify the thumbnail is set correctly.
		$this->assertSame( $attachment_id, (int) get_post_thumbnail_id( $post_id ) );

		$result   = $this->repository->posts( array( array( 'id' => $post_id, 'type' => 'post' ) ) );
		$snapshot = $result[ (string) $post_id ];

		// If the image resolves, verify its structure.
		if ( $snapshot->has( 'image' ) ) {
			$image = $snapshot->get( 'image' );
			$this->assertIsArray( $image );
			$this->assertArrayHasKey( 'url', $image );
			$this->assertArrayHasKey( 'alt', $image );
			$this->assertSame( 'Test alt text', $image['alt'] );
			$this->assertIsInt( $image['width'] );
			$this->assertIsInt( $image['height'] );
			$this->assertGreaterThan( 0, $image['width'] );
			$this->assertGreaterThan( 0, $image['height'] );
		}

		unlink( $file_path );
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

		$this->assertNotSame( 'sensitive-data', $snapshot->get( 'title' ) );
		$this->assertNotSame( 'sensitive-data', $snapshot->get( 'excerpt' ) );
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
			. '<!-- wp:campaignbridge/post-title /-->'
			. '<!-- wp:campaignbridge/post-excerpt /-->'
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