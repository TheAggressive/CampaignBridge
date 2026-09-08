<?php
/**
 * Native WordPress style input for the email renderers.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);
namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/** Resolves native values before each renderer applies its semantic defaults. */
final class Native_Style_Support {
	/**
	 * Supported native style paths, matching the block supports declarations.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const SUPPORTED = array(
		'campaignbridge/compliance-footer' => array( 'color.text', 'spacing.padding' ),
		'campaignbridge/post-image'        => array(),
		'campaignbridge/post-card'         => array( 'color.background', 'spacing.padding' ),
		'campaignbridge/text'              => array( 'color.text', 'color.background', 'typography.fontSize', 'typography.lineHeight', 'spacing.padding', 'spacing.margin' ),
		'campaignbridge/button'            => array( 'color.text', 'color.background' ),
		'campaignbridge/preheader'         => array(),
		'campaignbridge/spacer'            => array( 'dimensions.minHeight' ),
		'campaignbridge/post-title'        => array( 'color.text', 'typography.fontSize', 'typography.lineHeight' ),
		'campaignbridge/post-excerpt'      => array( 'color.text', 'typography.fontSize', 'typography.lineHeight' ),
		'campaignbridge/image'             => array( 'spacing.margin' ),
		'campaignbridge/columns'           => array( 'spacing.blockGap' ),
		'campaignbridge/section'           => array( 'color.background', 'spacing.padding', 'spacing.margin' ),
		'campaignbridge/post-button'       => array( 'color.text', 'color.background', 'elements.link.color.text', 'elements.link.:hover.color.text' ),
		'campaignbridge/heading'           => array( 'color.text', 'typography.fontSize', 'typography.lineHeight' ),
		'campaignbridge/container'         => array( 'color.text', 'color.background', 'spacing.padding', 'spacing.margin' ),
		'campaignbridge/divider'           => array( 'border.color', 'border.width', 'border.style', 'border.top.color', 'border.top.width', 'border.top.style', 'border.right.color', 'border.right.width', 'border.right.style', 'border.bottom.color', 'border.bottom.width', 'border.bottom.style', 'border.left.color', 'border.left.width', 'border.left.style' ),
		'campaignbridge/column'            => array( 'color.background' ),
		'campaignbridge/post-link'         => array( 'elements.link.color.text', 'elements.link.:hover.color.text' ),
	);

	/**
	 * Resolve supported native attributes while retaining legacy input compatibility.
	 *
	 * @param Block_Node $block Source block.
	 * @throws Invalid_Block_Attribute When a native value is unsupported.
	 * @return array<string, mixed>
	 */
	public static function attributes( Block_Node $block ): array {
		$attributes = $block->attributes();
		$style      = $attributes['style'] ?? array();
		if ( is_string( $style ) && in_array( $block->name(), array( 'campaignbridge/button', 'campaignbridge/post-button', 'campaignbridge/post-link', 'campaignbridge/divider' ), true ) ) {
			$attributes['variant'] = $style;
			$style                 = array();
		}
		if ( ! is_array( $style ) ) {
			throw new Invalid_Block_Attribute( 'style', 'must be a native WordPress style object.' );
		}
		self::validate_tree( $style, self::SUPPORTED[ $block->name() ] ?? array() );
		foreach ( array(
			'text'       => 'textColor',
			'background' => 'backgroundColor',
		) as $slot => $key ) {
			if ( isset( $style['color'][ $slot ] ) && ! isset( $attributes[ $key ] ) ) {
				$attributes[ $key ] = $style['color'][ $slot ];
			}
		}
		foreach ( array(
			'text'       => 'textColor',
			'background' => 'backgroundColor',
		) as $slot => $key ) {
			if ( isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) && '' !== $attributes[ $key ] ) {
				$value                   = $attributes[ $key ];
				$style['color'][ $slot ] = str_starts_with( $value, '#' ) || str_starts_with( $value, 'var:' ) ? $value : 'var:preset|color|' . $value;
			}
		}
		if ( isset( $style['elements']['link']['color']['text'] ) ) {
			$attributes['linkColor'] = $style['elements']['link']['color']['text'];
		}
		if ( isset( $style['elements']['link'][':hover']['color']['text'] ) ) {
			$attributes['hoverColor'] = $style['elements']['link'][':hover']['color']['text'];
		}
		if ( ( isset( $attributes['fontSize'] ) && ! is_int( $attributes['fontSize'] ) ) || isset( $style['typography']['fontSize'] ) ) {
			$input          = $attributes;
			$input['style'] = $style;
			if ( is_int( $input['fontSize'] ?? null ) ) {
				unset( $input['fontSize'] ); }
			$attributes['fontSize']          = Style_Resolver::font_size( $input );
			$style['typography']['fontSize'] = $attributes['fontSize'] . 'px';
		}
		if ( isset( $style['spacing']['blockGap'] ) ) {
			$attributes['gap'] = Style_Resolver::length( $style['spacing']['blockGap'], 'style.spacing.blockGap', 0, 48 );
		}
		if ( isset( $style['dimensions']['minHeight'] ) ) {
			$attributes['height'] = Style_Resolver::length( $style['dimensions']['minHeight'], 'style.dimensions.minHeight', 0, 600 );
		}
		if ( 'campaignbridge/divider' === $block->name() && isset( $attributes['borderColor'] ) ) {
			$attributes['color'] = $attributes['borderColor'];
		}
		if ( 'campaignbridge/divider' === $block->name() && isset( $style['border'] ) ) {
			$border = $style['border'];
			if ( isset( $border['color'] ) ) {
				$attributes['color'] = $border['color']; }
			if ( isset( $border['width'] ) ) {
				$attributes['thickness'] = Style_Resolver::length( $border['width'], 'style.border.width', 0, 8 ); }
			if ( isset( $border['style'] ) ) {
				$attributes['variant'] = $border['style']; }
		}
		if ( isset( $attributes['layout']['contentSize'] ) || isset( $attributes['layout']['wideSize'] ) ) {
			$attributes['maxWidth'] = Style_Resolver::length( $attributes['layout']['contentSize'] ?? $attributes['layout']['wideSize'], 'layout.contentSize', 320, 900 );
		}

		$attributes['style'] = $style;
		return $attributes;
	}
	/**
	 * Preserve the native link hover color as progressive enhancement.
	 * Only renderer-generated anchors pass through this helper.
	 *
	 * @param string                                      $html Canonical link/button markup.
	 * @param Block_Node                                  $block Normalized block.
	 * @param \CampaignBridge\Domain\Email\Render_Context $context Compile context.
	 */
	public static function link_output( string $html, Block_Node $block, \CampaignBridge\Domain\Email\Render_Context $context ): string {
		$hover = $block->attributes()['hoverColor'] ?? null;
		if ( null === $hover ) {
			return $html; }
		$color = Renderer_Support::resolve_color( $hover, Renderer_Support::brand_kit( $context ) );
		$class = 'cb-link-' . substr( hash( 'sha256', $block->path() ), 0, 12 );
		return '<style>.' . $class . ':hover{color:' . $color . '!important}</style>' . str_replace( '<a ', '<a class="' . $class . '" ', $html );
	}

	/**
	 * Reject properties that the email renderer cannot represent.
	 *
	 * @param array<string, mixed> $values Source style tree.
	 * @throws Invalid_Block_Attribute When a property is unsupported.
	 * @param array<int, string>   $allowed Supported native properties.
	 * @param string               $path Attribute path for diagnostics.
	 */
	private static function validate_tree( array $values, array $allowed, string $path = '' ): void {
		foreach ( $values as $key => $value ) {
			$property = '' === $path ? $key : $path . '.' . $key;
			if ( in_array( $property, $allowed, true ) ) {
				if ( is_array( $value ) && in_array( $key, array( 'padding', 'margin' ), true ) ) {
					foreach ( array_keys( $value ) as $side ) {
						if ( ! in_array( $side, array( 'top', 'right', 'bottom', 'left' ), true ) ) {
							throw new Invalid_Block_Attribute( 'style.' . $property . '.' . $side, 'is not a spacing side.' );
						}
					}
				} elseif ( ! is_scalar( $value ) || is_bool( $value ) ) {
					throw new Invalid_Block_Attribute( 'style.' . $property, 'must be a portable style value.' );
				}
				continue;
			}
			$nested = array_filter( $allowed, static fn( string $candidate ): bool => str_starts_with( $candidate, $property . '.' ) );
			if ( array() === $nested || ! is_array( $value ) ) {
				throw new Invalid_Block_Attribute( 'style.' . $property, 'is not supported by this email block.' );
			}
			self::validate_tree( $value, $allowed, $property );
		}
	}
}
