<?php
/**
 * Native border, corner radius, and outer spacing for email boxes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads Core's `style.border` and `style.spacing` shapes into bounded email CSS.
 *
 * Core stores a border either once for every side (`color`, `style`, `width`)
 * or per side (`top`, `right`, `bottom`, `left`), and a radius either once or
 * per corner. Both shapes are accepted. Values outside the email bounds fail
 * with a diagnostic naming the exact style path; nothing is clamped.
 */
final class Box_Style {
	/** Native style paths a block declares to accept a border. */
	public const BORDER_PATHS = array( 'border.color', 'border.style', 'border.width', 'border.radius', 'border.top', 'border.right', 'border.bottom', 'border.left' );

	/** Largest corner radius; a radius of at least half the box height is a pill. */
	public const RADIUS_MAX = 999;

	/** Thickest portable border. */
	public const WIDTH_MAX = 8;

	/** Border sides in CSS order. */
	public const SIDES = array( 'top', 'right', 'bottom', 'left' );

	/** Core's radius corners in CSS shorthand order. */
	public const CORNERS = array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' );

	/** Supported border line styles; `none` hides a side. */
	private const LINE_STYLES = array( 'solid', 'dashed', 'dotted', 'none' );

	/**
	 * Core draws a border with a colour but no width at the browser's medium width.
	 */
	private const IMPLIED_WIDTH = 3;

	/**
	 * Resolve the corner radius in pixels, or null when none was set.
	 *
	 * @param array<string, mixed> $style Native style tree.
	 * @return array{topLeft: int, topRight: int, bottomRight: int, bottomLeft: int}|null Pixels keyed by corner.
	 * @throws Invalid_Block_Attribute When a radius is not a bounded length.
	 */
	public static function radius( array $style ): ?array {
		$radius = $style['border']['radius'] ?? null;
		if ( null === $radius ) {
			return null;
		}
		$corner = static fn ( string $name ): int => is_array( $radius )
			? ( isset( $radius[ $name ] ) ? Style_Resolver::length( $radius[ $name ], 'style.border.radius.' . $name, 0, self::RADIUS_MAX ) : 0 )
			: Style_Resolver::length( $radius, 'style.border.radius', 0, self::RADIUS_MAX );

		return array(
			'topLeft'     => $corner( 'topLeft' ),
			'topRight'    => $corner( 'topRight' ),
			'bottomRight' => $corner( 'bottomRight' ),
			'bottomLeft'  => $corner( 'bottomLeft' ),
		);
	}

	/**
	 * Resolve the visible border of each side.
	 *
	 * @param array<string, mixed> $style Native style tree.
	 * @param Brand_Kit            $kit   Active palette.
	 * @return array<string, array{width: int, style: string, color: string|null}> Visible sides only.
	 * @throws Invalid_Block_Attribute When a border value is unsupported.
	 */
	public static function borders( array $style, Brand_Kit $kit ): array {
		$border = is_array( $style['border'] ?? null ) ? $style['border'] : array();
		$sides  = array();

		if ( array() !== array_intersect( self::SIDES, array_keys( $border ) ) ) {
			foreach ( self::SIDES as $side ) {
				if ( isset( $border[ $side ] ) ) {
					$sides[ $side ] = self::side( $border[ $side ], 'style.border.' . $side, $kit );
				}
			}
		} elseif ( array() !== array_intersect( array( 'color', 'style', 'width' ), array_keys( $border ) ) ) {
			$all   = self::side( $border, 'style.border', $kit );
			$sides = array_fill_keys( self::SIDES, $all );
		}

		return array_filter( $sides );
	}

	/**
	 * Whether every side has the same visible border.
	 *
	 * @param array<string, array{width: int, style: string, color: string|null}> $borders Resolved borders.
	 */
	public static function is_uniform( array $borders ): bool {
		return 4 === count( $borders ) && 1 === count( array_unique( array_map( 'serialize', $borders ) ) );
	}

	/**
	 * CSS declarations for borders and corner radius, without a leading separator.
	 *
	 * @param array<string, mixed> $style Native style tree.
	 * @param Brand_Kit            $kit   Active palette.
	 * @throws Invalid_Block_Attribute When a border or radius value is unsupported.
	 */
	public static function css( array $style, Brand_Kit $kit ): string {
		$declarations = array();
		$borders      = self::borders( $style, $kit );
		if ( self::is_uniform( $borders ) ) {
			$declarations[] = 'border:' . self::line( $borders['top'] );
		} else {
			foreach ( $borders as $side => $border ) {
				$declarations[] = 'border-' . $side . ':' . self::line( $border );
			}
		}

		$radius = self::radius_css( self::radius( $style ) );
		if ( '' !== $radius ) {
			$declarations[] = $radius;
		}

		return implode( ';', $declarations );
	}

	/**
	 * CSS for a resolved radius, or an empty string when every corner is square.
	 *
	 * @param array{topLeft: int, topRight: int, bottomRight: int, bottomLeft: int}|null $radius Pixels keyed by corner.
	 */
	public static function radius_css( ?array $radius ): string {
		if ( null === $radius || 0 === max( $radius ) ) {
			return '';
		}
		if ( 1 === count( array_unique( $radius ) ) ) {
			return sprintf( 'border-radius:%dpx', $radius['topLeft'] );
		}

		return sprintf( 'border-radius:%dpx %dpx %dpx %dpx', $radius['topLeft'], $radius['topRight'], $radius['bottomRight'], $radius['bottomLeft'] );
	}

	/**
	 * Resolve one spacing group with zero for every side the author left unset.
	 *
	 * @param array<string, mixed> $style Native style tree.
	 * @param string               $group `margin` or `padding`.
	 * @return array{top: int, right: int, bottom: int, left: int}
	 * @throws Invalid_Block_Attribute When a length is not portable.
	 */
	public static function spacing( array $style, string $group ): array {
		$sides = Style_Resolver::spacing( array( 'style' => $style ), $group, array_fill_keys( self::SIDES, 0 ) );

		return array(
			'top'    => $sides['top'] ?? 0,
			'right'  => $sides['right'] ?? 0,
			'bottom' => $sides['bottom'] ?? 0,
			'left'   => $sides['left'] ?? 0,
		);
	}

	/**
	 * CSS shorthand for one resolved spacing group.
	 *
	 * @param string             $property `margin` or `padding`.
	 * @param array<string, int> $sides    Pixels keyed by side; every side is present.
	 */
	public static function spacing_css( string $property, array $sides ): string {
		return sprintf( '%s:%dpx %dpx %dpx %dpx', $property, $sides['top'], $sides['right'], $sides['bottom'], $sides['left'] );
	}

	/**
	 * Apply an authored margin as the padding of a wrapping presentation cell.
	 *
	 * Email clients disagree on margins of tables and block elements, so outer
	 * spacing is rendered as cell padding, which every client honors.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $style Native style tree.
	 * @throws Invalid_Block_Attribute When a margin is not portable.
	 */
	public static function with_margin( string $html, array $style ): string {
		$margin = self::spacing( $style, 'margin' );
		if ( 0 === max( $margin ) ) {
			return $html;
		}

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse"><tr><td style="%1$s">%2$s</td></tr></table>',
			self::spacing_css( 'padding', $margin ),
			$html
		);
	}

	/**
	 * Resolve one side's border.
	 *
	 * @param mixed     $value Core border object for one side or all sides.
	 * @param string    $path  Diagnostic style path.
	 * @param Brand_Kit $kit   Active palette.
	 * @return array{width: int, style: string, color: string|null}|null Null when the side is hidden.
	 * @throws Invalid_Block_Attribute When the value is unsupported.
	 */
	private static function side( mixed $value, string $path, Brand_Kit $kit ): ?array {
		if ( ! is_array( $value ) ) {
			throw new Invalid_Block_Attribute( $path, 'must be a native border object.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic paths are internal.
		}

		$color = isset( $value['color'] ) && '' !== $value['color']
			? Renderer_Support::resolve_color( (string) $value['color'], $kit, $path . '.color' )
			: null;
		$line  = $value['style'] ?? 'solid';
		if ( ! in_array( $line, self::LINE_STYLES, true ) ) {
			throw new Invalid_Block_Attribute( $path . '.style', 'must be solid, dashed, dotted, or none.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic paths are internal.
		}
		$width = isset( $value['width'] ) && '' !== $value['width']
			? Style_Resolver::length( $value['width'], $path . '.width', 0, self::WIDTH_MAX )
			: ( null === $color ? 0 : self::IMPLIED_WIDTH );

		if ( 'none' === $line || 0 === $width ) {
			return null;
		}

		return array(
			'width' => $width,
			'style' => $line,
			'color' => $color,
		);
	}

	/**
	 * CSS value for one border line.
	 *
	 * @param array{width: int, style: string, color: string|null} $border Resolved border.
	 */
	private static function line( array $border ): string {
		return sprintf( '%dpx %s', $border['width'], $border['style'] ) . ( null === $border['color'] ? '' : ' ' . $border['color'] );
	}
}
