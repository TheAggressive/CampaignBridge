<?php
/**
 * Template preview compilation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Compile_Result;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Post_Snapshot_Source;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Review_Input;
use CampaignBridge\Services\Email\Compiler_Factory;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;

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
	 * @param Post_Snapshot_Source $snapshots   Immutable post source.
	 * @param Brand_Kit|null       $brand_kit   Active brand kit. Null uses defaults.
	 */
	public function __construct(
		private readonly Post_Snapshot_Source $snapshots,
		private readonly ?Brand_Kit $brand_kit = null
	) {}

	/**
	 * Compile serialized block content.
	 *
	 * @param string               $content  Serialized block markup.
	 * @param array<string, mixed> $metadata Document metadata and design tokens.
	 */
	public function compile( string $content, array $metadata = array() ): Compile_Result {
		return $this->compile_frozen( $this->capture( $content, $metadata ) );
	}

	/**
	 * Explicitly capture current content and design for repeatable review.
	 *
	 * @param string               $content Serialized template.
	 * @param array<string, mixed> $metadata Document metadata.
	 */
	public function capture( string $content, array $metadata = array() ): Review_Input {
		$blocks = $this->parse( $content );

		$kit = $this->brand_kit ?? Brand_Kit::defaults();

		$snapshots = $this->snapshots->posts( Snapshot_References::collect( $blocks ) );

		$context = new Render_Context(
			array_merge( array( 'brandKit' => $kit ), $metadata ),
			array( 'posts' => $snapshots ),
			array(),
			Email_Compiler::PROFILE_VERSION
		);

		return new Review_Input( $content, $blocks, $context, Email_Design_Factory::resolve( $kit ), 1, Email_Compiler::COMPILER_VERSION );
	}

	/**
	 * Refresh content explicitly, retaining the reviewed template and design.
	 *
	 * @param Review_Input $previous Previous frozen input, which remains unchanged.
	 */
	public function refresh( Review_Input $previous ): Review_Input {
		$snapshots = $this->snapshots->posts( Snapshot_References::collect( $previous->blocks() ) );
		$context   = new Render_Context(
			$previous->context()->fingerprint_payload()['metadata'],
			array( 'posts' => $snapshots ),
			array(),
			$previous->context()->profile()
		);

		return new Review_Input(
			$previous->content(),
			$previous->blocks(),
			$context,
			$previous->design(),
			$previous->revision() + 1,
			$previous->compiler_version()
		);
	}

	/**
	 * Compile only frozen inputs; never resolve WordPress content or design here.
	 *
	 * @param Review_Input $input Captured review input.
	 */
	public function compile_frozen( Review_Input $input ): Compile_Result {
		if ( Email_Compiler::COMPILER_VERSION !== $input->compiler_version() ) {
			return new Compile_Result(
				'',
				'',
				array( Compile_Diagnostic::error( 'snapshot.compiler.unsupported', 'document', 'The captured compiler version is unavailable; capture a new review input.' ) ),
				array(),
				'',
				Email_Compiler::COMPILER_VERSION,
				$input->context()->profile()
			);
		}

		return Compiler_Factory::create( $input->design() )->compile( $input->blocks(), $input->context() );
	}

	/**
	 * Check whether a prior review still authorizes this exact input revision.
	 *
	 * This is an input comparison, not a persisted campaign approval operation.
	 *
	 * @param Review_Input $input                Current frozen input.
	 * @param int          $reviewed_revision    Revision accepted by the reviewer.
	 * @param string       $reviewed_fingerprint Artifact fingerprint accepted by the reviewer.
	 */
	public function matches_review( Review_Input $input, int $reviewed_revision, string $reviewed_fingerprint ): bool {
		if ( $input->revision() !== $reviewed_revision || '' === $reviewed_fingerprint ) {
			return false;
		}

		$result = $this->compile_frozen( $input );

		return $result->is_success() && hash_equals( $result->fingerprint(), $reviewed_fingerprint );
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
