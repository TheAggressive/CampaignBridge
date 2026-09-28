<?php
/**
 * Frozen input for reproducible email review.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

use CampaignBridge\Domain\Email\Token\Token_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Groups existing canonical objects without resolving or copying post values. */
final class Review_Input {
	public const SCHEMA_VERSION        = 1;
	private const MAX_CONTENT          = 524288;
	private const MAX_BLOCKS           = 500;
	private const MAX_SERIALIZED_BYTES = 2097152;
	private const METADATA_STRINGS     = array(
		'title'            => 512,
		'language'         => 20,
		'background_color' => 32,
		'unsubscribe_url'  => 2048,
	);

	/**
	 * Capture all inputs needed to repeat a review compilation.
	 *
	 * @param string                           $content          Serialized template.
	 * @param array<int, array<string, mixed>> $blocks           Parsed template.
	 * @param Render_Context                   $context          Frozen content and metadata.
	 * @param Resolved_Email_Design            $design           Frozen resolved design.
	 * @param int                              $revision         Explicit capture revision.
	 * @param string                           $compiler_version Compiler that captured this input.
	 *
	 * @throws \InvalidArgumentException When the input exceeds a storage bound.
	 */
	public function __construct(
		private readonly string $content,
		private readonly array $blocks,
		private readonly Render_Context $context,
		private readonly Resolved_Email_Design $design,
		private readonly int $revision,
		private readonly string $compiler_version
	) {
		if ( strlen( $content ) > self::MAX_CONTENT || count( $blocks ) > self::MAX_BLOCKS ) {
			throw new \InvalidArgumentException( 'Review input exceeds its storage bounds.' );
		}
		if ( 1 > $revision || '' === $compiler_version || strlen( $compiler_version ) > 32 ) {
			throw new \InvalidArgumentException( 'Review input revision or compiler version is invalid.' );
		}
		self::validate_metadata( $context->fingerprint_payload()['metadata'] );
	}

	/**
	 * Restore a frozen M1 review contract without reading WordPress content.
	 *
	 * @param array<string, mixed> $data Persisted review input.
	 *
	 * @throws \InvalidArgumentException When the stored contract is malformed or unsupported.
	 */
	public static function from_array( array $data ): self {
		self::validate_serialized_size( $data );

		$allowed = array( 'schema_version', 'content', 'blocks', 'context', 'design', 'design_fingerprint', 'revision', 'compiler_version' );
		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new \InvalidArgumentException( 'Unknown review-input field.' );
			}
		}
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported review-input schema version.' );
		}

		$content  = $data['content'] ?? null;
		$blocks   = $data['blocks'] ?? null;
		$context  = $data['context'] ?? null;
		$design   = $data['design'] ?? null;
		$revision = $data['revision'] ?? null;
		$compiler = $data['compiler_version'] ?? null;
		if ( ! is_string( $content ) || ! is_array( $blocks ) || ! array_is_list( $blocks ) || ! is_array( $context ) || ! is_array( $design ) || ! is_int( $revision ) || ! is_string( $compiler ) ) {
			throw new \InvalidArgumentException( 'Stored review input is malformed.' );
		}

		$context_allowed = array( 'metadata', 'snapshots', 'profile' );
		foreach ( array_keys( $context ) as $key ) {
			if ( ! in_array( $key, $context_allowed, true ) ) {
				throw new \InvalidArgumentException( 'Stored review context contains an unknown field.' );
			}
		}
		$metadata  = $context['metadata'] ?? null;
		$snapshots = $context['snapshots'] ?? null;
		$profile   = $context['profile'] ?? null;
		if ( ! is_array( $metadata ) || ! is_array( $snapshots ) || ! is_string( $profile ) || '' === $profile || strlen( $profile ) > 32 ) {
			throw new \InvalidArgumentException( 'Stored review context is malformed.' );
		}

		if ( isset( $metadata['brandKit'] ) ) {
			if ( ! is_array( $metadata['brandKit'] ) ) {
				throw new \InvalidArgumentException( 'Stored Brand Kit context is malformed.' );
			}
			$metadata['brandKit'] = Brand_Kit::from_array( $metadata['brandKit'] );
		}

		foreach ( array_keys( $snapshots ) as $collection ) {
			if ( 'posts' !== $collection ) {
				throw new \InvalidArgumentException( 'Stored review context contains an unsupported snapshot collection.' );
			}
		}
		$posts = array();
		foreach ( $snapshots['posts'] ?? array() as $id => $snapshot ) {
			if ( ! is_array( $snapshot ) ) {
				throw new \InvalidArgumentException( 'Stored post snapshot is malformed.' );
			}
			$post = Post_Snapshot::from_array( $snapshot );
			if ( (string) $post->source_id() !== (string) $id ) {
				throw new \InvalidArgumentException( 'Stored post snapshot identity is inconsistent.' );
			}
			$posts[ (string) $id ] = $post;
		}

		$design_fingerprint = $data['design_fingerprint'] ?? null;
		if ( ! is_string( $design_fingerprint ) ) {
			throw new \InvalidArgumentException( 'Stored design fingerprint is malformed.' );
		}

		return new self(
			$content,
			$blocks,
			new Render_Context( $metadata, array( 'posts' => $posts ), array(), $profile ),
			Resolved_Email_Design::from_array( $design, $design_fingerprint ),
			$revision,
			$compiler
		);
	}

	/** Original serialized template. */
	public function content(): string {
		return $this->content;
	}

	/**
	 * Get the frozen parsed template.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function blocks(): array {
		return $this->blocks;
	}

	/** Canonical snapshots and frozen metadata. */
	public function context(): Render_Context {
		return $this->context;
	}

	/** Resolved design, without rereading the active theme. */
	public function design(): Resolved_Email_Design {
		return $this->design;
	}

	/** Content capture revision, distinct from the post schema version. */
	public function revision(): int {
		return $this->revision;
	}

	/** Compiler required to reproduce this input. */
	public function compiler_version(): string {
		return $this->compiler_version;
	}

	/**
	 * Export the complete frozen persistence representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$context  = $this->context->fingerprint_payload();
		$metadata = $context['metadata'];
		if ( isset( $metadata['brandKit'] ) && $metadata['brandKit'] instanceof Brand_Kit ) {
			$metadata['brandKit'] = $metadata['brandKit']->to_array();
		}
		$context['metadata'] = $metadata;

		$serialized = array(
			'schema_version'     => self::SCHEMA_VERSION,
			'content'            => $this->content,
			'blocks'             => $this->blocks,
			'context'            => $context,
			'design'             => $this->design->to_array(),
			'design_fingerprint' => $this->design->fingerprint(),
			'revision'           => $this->revision,
			'compiler_version'   => $this->compiler_version,
		);
		self::validate_serialized_size( $serialized );

		return $serialized;
	}

	/**
	 * Bound the complete nested contract, not only its largest leaf fields.
	 *
	 * @param array<string, mixed> $data Review input persistence payload.
	 *
	 * @throws \InvalidArgumentException When the payload cannot be encoded or is too large.
	 */
	private static function validate_serialized_size( array $data ): void {
		try {
			$serialized = json_encode( $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain validation requires throwing, deterministic JSON.
		} catch ( \JsonException $exception ) {
			throw new \InvalidArgumentException( 'Review input cannot be serialized.', 0, $exception );
		}

		if ( strlen( $serialized ) > self::MAX_SERIALIZED_BYTES ) {
			throw new \InvalidArgumentException( 'Review input exceeds its serialized storage bound.' );
		}
	}

	/**
	 * Enforce the closed, non-subscriber metadata contract retained by snapshots.
	 *
	 * @param array<string, mixed> $metadata Frozen render metadata.
	 *
	 * @throws \InvalidArgumentException When metadata is unknown, unbounded, or contains provider-resolved values.
	 */
	private static function validate_metadata( array $metadata ): void {
		$allowed = array_merge( array( 'brandKit', Token_Resolver::CONTEXT_KEY ), array_keys( self::METADATA_STRINGS ) );
		foreach ( array_keys( $metadata ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed, true ) ) {
				throw new \InvalidArgumentException( 'Frozen review metadata contains an unsupported field.' );
			}
		}

		if ( isset( $metadata['brandKit'] ) && ! $metadata['brandKit'] instanceof Brand_Kit ) {
			throw new \InvalidArgumentException( 'Frozen review Brand Kit metadata is invalid.' );
		}
		foreach ( self::METADATA_STRINGS as $key => $maximum ) {
			if ( isset( $metadata[ $key ] ) && ( ! is_string( $metadata[ $key ] ) || strlen( $metadata[ $key ] ) > $maximum ) ) {
				throw new \InvalidArgumentException( 'Frozen review metadata string is invalid or unbounded.' );
			}
		}

		$tokens = Token_Resolver::default();
		if ( null !== $tokens->validate_context_values( $metadata[ Token_Resolver::CONTEXT_KEY ] ?? null ) ) {
			throw new \InvalidArgumentException( 'Frozen review token values cannot contain subscriber/provider data.' );
		}
	}
}
