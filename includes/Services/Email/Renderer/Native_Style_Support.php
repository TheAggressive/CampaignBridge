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
		'campaignbridge/post-card'         => array( 'color.background', 'spacing.padding', ...Box_Style::BORDER_PATHS ),
		'core/paragraph'                   => array( 'color.text', 'color.background', 'typography.fontSize', 'typography.fontFamily', 'typography.fontStyle', 'typography.fontWeight', 'typography.lineHeight', 'spacing.padding', 'spacing.margin' ),
		'core/button'                      => array( 'color.text', 'color.background', 'typography.fontFamily', 'typography.fontSize', 'typography.fontStyle', 'typography.fontWeight', 'typography.lineHeight', 'spacing.padding', ...Box_Style::BORDER_PATHS ),
		'core/buttons'                     => array( 'spacing.margin' ),
		'campaignbridge/preheader'         => array(),
		'core/spacer'                      => array(),
		'core/image'                       => array( 'spacing.margin', ...Box_Style::BORDER_PATHS ),
		'core/list'                        => array( 'spacing.margin', 'spacing.padding' ),
		'campaignbridge/columns'           => array( 'spacing.blockGap' ),
		'campaignbridge/section'           => array( 'color.background', 'spacing.padding', 'spacing.margin', ...Box_Style::BORDER_PATHS ),
		'core/heading'                     => array( 'color.text', 'color.background', 'typography.fontSize', 'typography.fontFamily', 'typography.fontStyle', 'typography.fontWeight', 'typography.lineHeight', 'spacing.margin', 'spacing.padding' ),
		'campaignbridge/container'         => array( 'color.text', 'color.background', 'spacing.padding', 'spacing.margin' ),
		'core/separator'                   => array( 'spacing.margin' ),
		'core/social-links'                => array( 'spacing.margin' ),
		'campaignbridge/navigation'        => array( 'color.text' ),
		'campaignbridge/column'            => array( 'color.background', 'spacing.padding', ...Box_Style::BORDER_PATHS ),
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
		if ( isset( $style['typography']['fontFamily'] ) && ! isset( $attributes['fontFamily'] ) ) {
			$attributes['fontFamily'] = $style['typography']['fontFamily'];
		}
		// Core stores a palette border colour as a top-level preset slug.
		if ( isset( $attributes['borderColor'] ) && is_string( $attributes['borderColor'] ) && '' !== $attributes['borderColor'] && ! isset( $style['border']['color'] ) ) {
			$style['border']['color'] = 'var:preset|color|' . $attributes['borderColor'];
		}
		unset( $attributes['borderColor'] );
		foreach ( array(
			'text'       => 'textColor',
			'background' => 'backgroundColor',
		) as $slot => $key ) {
			if ( isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) && '' !== $attributes[ $key ] ) {
				$value                   = $attributes[ $key ];
				$style['color'][ $slot ] = str_starts_with( $value, '#' ) || str_starts_with( $value, 'var:' ) ? $value : 'var:preset|color|' . $value;
			}
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
		if ( isset( $attributes['layout']['contentSize'] ) || isset( $attributes['layout']['wideSize'] ) ) {
			$attributes['maxWidth'] = Style_Resolver::length( $attributes['layout']['contentSize'] ?? $attributes['layout']['wideSize'], 'layout.contentSize', 320, 900 );
		}

		$attributes['style'] = $style;
		return $attributes;
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
					self::validate_keys( $value, Box_Style::SIDES, 'style.' . $property, 'is not a spacing side.' );
				} elseif ( is_array( $value ) && 'border.radius' === $property ) {
					self::validate_keys( $value, Box_Style::CORNERS, 'style.' . $property, 'is not a border corner.' );
				} elseif ( is_array( $value ) && in_array( $key, Box_Style::SIDES, true ) && str_starts_with( $property, 'border.' ) ) {
					self::validate_keys( $value, array( 'color', 'style', 'width' ), 'style.' . $property, 'is not a border property.' );
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

	/**
	 * Reject unknown keys and non-scalar values inside one native style object.
	 *
	 * @param array<mixed, mixed> $values  Style object.
	 * @param array<int, string>  $known   Accepted keys.
	 * @param string              $path    Diagnostic path.
	 * @param string              $message Diagnostic for an unknown key.
	 * @throws Invalid_Block_Attribute When a key or value is unsupported.
	 */
	private static function validate_keys( array $values, array $known, string $path, string $message ): void {
		foreach ( $values as $key => $value ) {
			if ( ! in_array( $key, $known, true ) ) {
				throw new Invalid_Block_Attribute( $path . '.' . $key, $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic paths are internal.
			}
			if ( ! is_scalar( $value ) || is_bool( $value ) ) {
				throw new Invalid_Block_Attribute( $path . '.' . $key, 'must be a portable style value.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic paths are internal.
			}
		}
	}
}
