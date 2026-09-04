<?php
/**
 * Template preview compilation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Email;

use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Post_Snapshot_Source;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Compiler_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles unsaved editor content into the canonical email artifact.
 *
 * A preview resolves the same snapshot and runs the same compiler a send
 * would, so what the operator inspects is the artifact rather than an
 * approximation of it. Nothing here persists state or contacts a provider.
 */
final class Template_Preview {
	/**
	 * Build a preview compiler.
	 *
	 * @param Post_Snapshot_Source $snapshots Immutable post source.
	 */
	public function __construct( private readonly Post_Snapshot_Source $snapshots ) {}

	/**
	 * Compile serialized block content.
	 *
	 * @param string               $content  Serialized block markup.
	 * @param array<string, mixed> $metadata Document metadata and design tokens.
	 */
	public function compile( string $content, array $metadata = array() ): Compile_Result {
		$blocks = $this->parse( $content );

		$context = new Render_Context(
			$metadata,
			array( 'posts' => $this->snapshots->posts( Snapshot_References::collect( $blocks ) ) ),
			array(),
			Email_Compiler::PROFILE_VERSION
		);

		return Compiler_Factory::create()->compile( $blocks, $context );
	}

	/**
	 * Parse serialized markup into the compiler's input shape.
	 *
	 * `parse_blocks` emits whitespace-only freeform entries between blocks.
	 * They carry no block name and would otherwise each become an unsupported
	 * block diagnostic, so they are dropped before compilation.
	 *
	 * @param string $content Serialized block markup.
	 * @return array<int, array<string, mixed>>
	 */
	private function parse( string $content ): array {
		$blocks = parse_blocks( $content );

		return array_values(
			array_filter(
				$blocks,
				static function ( $block ): bool {
					if ( ! is_array( $block ) ) {
						return false;
					}

					$name = $block['blockName'] ?? null;
					if ( null !== $name ) {
						return true;
					}

					return '' !== trim( (string) ( $block['innerHTML'] ?? '' ) );
				}
			)
		);
	}
}
