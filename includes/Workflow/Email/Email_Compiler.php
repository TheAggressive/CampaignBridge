<?php
/**
 * Deterministic email compilation workflow.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Email;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Document_Renderer_Interface;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Renderer_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Compiles a bounded native block tree into one deterministic artifact. */
final class Email_Compiler {
	public const COMPILER_VERSION = '3';
	public const PROFILE_VERSION  = 'universal@1';

	private const MAX_BLOCKS = 500;
	private const MAX_DEPTH  = 20;

	/**
	 * Blocks visited during the current compilation.
	 *
	 * @var int
	 */
	private int $block_count = 0;

	/**
	 * Create the workflow with explicit pure dependencies.
	 *
	 * @param Renderer_Registry           $registry          Immutable renderer registry.
	 * @param Document_Renderer_Interface $document_renderer Document shell renderer.
	 * @param Artifact_Fingerprinter      $fingerprinter     Deterministic fingerprinter.
	 */
	public function __construct(
		private readonly Renderer_Registry $registry,
		private readonly Document_Renderer_Interface $document_renderer,
		private readonly Artifact_Fingerprinter $fingerprinter
	) {}

	/**
	 * Compile parsed WordPress block data into one immutable artifact.
	 *
	 * @param array<int, mixed> $blocks  Parsed block tree.
	 * @param Render_Context    $context Immutable render context.
	 */
	public function compile( array $blocks, Render_Context $context ): Compile_Result {
		$diagnostics       = array();
		$this->block_count = 0;

		if ( self::PROFILE_VERSION !== $context->profile() ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'profile.unsupported',
				'document',
				'Only the universal@1 email profile is supported.'
			);
		}

		$nodes = $this->parse_nodes( $blocks, 'blocks', 0, $diagnostics );

		$root = $nodes[0] ?? null;
		if ( 1 !== count( $nodes ) || 'campaignbridge/container' !== $root?->name() ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'document.root.invalid',
				'document',
				'Email documents require exactly one campaignbridge/container root block.'
			);
		}

		if ( $this->has_errors( $diagnostics ) ) {
			return $this->failure( $diagnostics );
		}

		$fragment = $this->render_node( $nodes[0], $context, $diagnostics );
		if ( $this->has_errors( $diagnostics ) ) {
			return $this->failure( $diagnostics );
		}

		$html = $this->document_renderer->render( $fragment['html'], $context );
		$text = trim( $fragment['text'] ) . "\n";

		$fingerprint = $this->fingerprinter->fingerprint(
			array(
				'compiler_version' => self::COMPILER_VERSION,
				'profile_version'  => self::PROFILE_VERSION,
				'source'           => $nodes[0]->fingerprint_payload(),
				'context'          => $context->fingerprint_payload(),
				'html'             => $html,
				'text'             => $text,
				'assets'           => $fragment['assets'],
			)
		);

		return new Compile_Result(
			$html,
			$text,
			$diagnostics,
			$fragment['assets'],
			$fingerprint,
			self::COMPILER_VERSION,
			self::PROFILE_VERSION
		);
	}

	/**
	 * Normalize a bounded list of parsed blocks.
	 *
	 * @param array<int, mixed>              $blocks      Parsed blocks.
	 * @param string                         $parent_path Parent diagnostic path.
	 * @param int                            $depth       Current nesting depth.
	 * @param array<int, Compile_Diagnostic> $diagnostics Compiler diagnostics.
	 * @return array<int, Block_Node>
	 */
	private function parse_nodes( array $blocks, string $parent_path, int $depth, array &$diagnostics ): array {
		if ( self::MAX_DEPTH < $depth ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'document.depth.exceeded',
				$parent_path,
				sprintf( 'Email block nesting cannot exceed %d levels.', self::MAX_DEPTH )
			);

			return array();
		}

		$nodes = array();
		foreach ( $blocks as $index => $block ) {
			$path = sprintf( '%s[%d]', $parent_path, $index );
			if ( ! is_array( $block ) ) {
				$diagnostics[] = Compile_Diagnostic::error(
					'block.malformed',
					$path,
					'Every parsed block must use the documented array schema.'
				);
				continue;
			}

			$name = $block['blockName'] ?? null;
			if ( null === $name ) {
				$freeform = $block['innerHTML'] ?? '';
				if ( is_string( $freeform ) && '' === trim( $freeform ) ) {
					continue;
				}

				$diagnostics[] = Compile_Diagnostic::error(
					'block.freeform.unsupported',
					$path,
					'Freeform HTML is not supported by the email compiler.'
				);
				continue;
			}

			++$this->block_count;

			if ( self::MAX_BLOCKS < $this->block_count ) {
				$diagnostics[] = Compile_Diagnostic::error(
					'document.blocks.exceeded',
					$path,
					sprintf( 'Email documents cannot exceed %d blocks.', self::MAX_BLOCKS )
				);

				break;
			}

			$attributes = $block['attrs'] ?? array();
			$children   = $block['innerBlocks'] ?? array();

			if ( ! is_string( $name ) || '' === $name || ! is_array( $attributes ) || ! is_array( $children ) ) {
				$diagnostics[] = Compile_Diagnostic::error(
					'block.malformed',
					$path,
					'Block name, attributes, and children must use the documented schema.'
				);
				continue;
			}

			$child_nodes = $this->parse_nodes( $children, $path . '.innerBlocks', $depth + 1, $diagnostics );
			/**
			 * Parsed semantic attributes.
			 *
			 * @var array<string, mixed> $attributes
			 */
			$nodes[] = new Block_Node( $name, $attributes, $child_nodes, $path );
		}

		return $nodes;
	}

	/**
	 * Render one normalized node recursively.
	 *
	 * @param Block_Node                     $block       Normalized block node.
	 * @param Render_Context                 $context     Immutable scoped context.
	 * @param array<int, Compile_Diagnostic> $diagnostics Compiler diagnostics.
	 * @return array{html: string, text: string, assets: array<int, array<string, mixed>>}
	 */
	private function render_node( Block_Node $block, Render_Context $context, array &$diagnostics ): array {
		$renderer = $this->registry->get( $block->name() );
		if ( null === $renderer ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'block.unsupported',
				$block->path(),
				sprintf( 'Block %s is not supported by the email compiler.', $block->name() )
			);

			return array(
				'html'   => '',
				'text'   => '',
				'assets' => array(),
			);
		}

		$unsupported_attributes = array_diff( array_keys( $block->attributes() ), $renderer->attribute_names() );
		if ( array() !== $unsupported_attributes ) {
			sort( $unsupported_attributes, SORT_STRING );
			$diagnostics[] = Compile_Diagnostic::error(
				'block.attributes.unsupported',
				$block->path(),
				sprintf(
					'Block %s contains unsupported attributes: %s.',
					$block->name(),
					implode( ', ', $unsupported_attributes )
				)
			);

			return array(
				'html'   => '',
				'text'   => '',
				'assets' => array(),
			);
		}

		$allowed_children = $renderer->allowed_children();
		if ( null !== $allowed_children ) {
			foreach ( $block->children() as $child ) {
				if ( ! in_array( $child->name(), $allowed_children, true ) ) {
					$diagnostics[] = Compile_Diagnostic::error(
						'block.child.unsupported',
						$child->path(),
						sprintf( 'Block %s cannot be nested inside %s.', $child->name(), $block->name() )
					);
				}
			}
		}

		if ( $this->has_errors( $diagnostics ) ) {
			return array(
				'html'   => '',
				'text'   => '',
				'assets' => array(),
			);
		}

		try {
			$block = $renderer->normalize( $block );
		} catch ( Invalid_Block_Attribute $exception ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'block.attribute.invalid',
				$block->path() . '.attrs.' . $exception->attribute(),
				$exception->getMessage()
			);

			return array(
				'html'   => '',
				'text'   => '',
				'assets' => array(),
			);
		}

		$diagnostics = array_merge( $diagnostics, $renderer->validate( $block, $context ) );
		if ( $this->has_errors( $diagnostics ) ) {
			return array(
				'html'   => '',
				'text'   => '',
				'assets' => array(),
			);
		}

		$child_context = $renderer->context_for_children( $block, $context );
		$html          = '';
		$text          = '';
		$assets        = $renderer->referenced_assets( $block, $context );

		foreach ( $block->children() as $child ) {
			$fragment = $this->render_node( $child, $child_context, $diagnostics );
			$html    .= $fragment['html'];
			$text    .= $fragment['text'];
			$assets   = array_merge( $assets, $fragment['assets'] );
		}

		if ( $this->has_errors( $diagnostics ) ) {
			return array(
				'html'   => '',
				'text'   => '',
				'assets' => array(),
			);
		}

		return array(
			'html'   => $renderer->render_html( $block, $html, $context ),
			'text'   => $renderer->render_text( $block, $text, $context ),
			'assets' => $assets,
		);
	}

	/**
	 * Determine whether diagnostics contain an error.
	 *
	 * @param array<int, Compile_Diagnostic> $diagnostics Compiler diagnostics.
	 */
	private function has_errors( array $diagnostics ): bool {
		foreach ( $diagnostics as $diagnostic ) {
			if ( $diagnostic->is_error() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create a fail-closed result.
	 *
	 * @param array<int, Compile_Diagnostic> $diagnostics Compiler diagnostics.
	 */
	private function failure( array $diagnostics ): Compile_Result {
		return new Compile_Result(
			'',
			'',
			$diagnostics,
			array(),
			'',
			self::COMPILER_VERSION,
			self::PROFILE_VERSION
		);
	}
}
