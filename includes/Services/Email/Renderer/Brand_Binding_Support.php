<?php
/**
 * Frozen Brand Kit binding support.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves validated bindings from the immutable Brand Kit render metadata. */
final class Brand_Binding_Support {
	public const ATTRIBUTE = 'brandBindings';

	/**
	 * Substitute the frozen logo fields and deterministic display dimensions.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public static function resolve( Block_Node $block, Render_Context $context ): Block_Node {
		$bindings = self::bindings( $block );
		if ( array() === $bindings ) {
			return $block;
		}

		$kit  = $context->metadata( 'brandKit' );
		$logo = $kit instanceof Brand_Kit ? $kit->logo() : null;
		if ( null === $logo ) {
			return $block;
		}

		$attributes = $block->attributes();
		$values     = array(
			'logoUrl'  => $logo['url'],
			'logoAlt'  => $logo['alt'],
			'logoLink' => $logo['link_url'],
		);
		foreach ( $bindings as $attribute => $binding ) {
			$attributes[ $attribute ] = $values[ $binding['field'] ] ?? '';
		}

		$display_width        = min( (int) ( $attributes['width'] ?? 600 ), $logo['width'], 600 );
		$attributes['width']  = max( 1, $display_width );
		$attributes['height'] = max( 1, (int) round( $logo['height'] * $attributes['width'] / $logo['width'] ) );

		return $block->with_attributes( $attributes );
	}

	/**
	 * Report a bound logo when the frozen Brand Kit has no asset.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, Compile_Diagnostic>
	 */
	public static function validate( Block_Node $block, Render_Context $context ): array {
		if ( array() === self::bindings( $block ) ) {
			return array();
		}

		$kit = $context->metadata( 'brandKit' );
		if ( $kit instanceof Brand_Kit && null !== $kit->logo() ) {
			return array();
		}

		return array(
			Compile_Diagnostic::error(
				'brand.logo.missing',
				$block->path(),
				'Import a WordPress Site Logo into the Brand Kit before using the Brand Logo variation.'
			),
		);
	}

	/**
	 * Read canonical Brand Kit bindings from one block.
	 *
	 * @param Block_Node $block Normalized block.
	 * @return array<string, array{field: string}>
	 */
	private static function bindings( Block_Node $block ): array {
		$value = $block->attributes()[ self::ATTRIBUTE ] ?? array();

		return is_array( $value ) ? $value : array();
	}
}
