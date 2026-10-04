<?php
/**
 * Shared bulletproof email button markup.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits one portable CTA with a desktop Outlook VML fallback.
 *
 * Both renderings come from the same resolved box, so the VML shape follows
 * the authored padding, border, corner radius, and type. Outlook's Word engine
 * draws one radius and one border for the whole shape; per-corner radii use
 * the largest corner there.
 */
final class Button_Markup {
	/** Average glyph width as a fraction of the font size, used to size the VML shape. */
	private const GLYPH_WIDTH = 0.6;

	/**
	 * Render button HTML.
	 *
	 * @param string               $url         Valid absolute URL.
	 * @param string               $label       Validated button label.
	 * @param string               $background  Portable fill color.
	 * @param string               $text        Portable text color.
	 * @param string|null          $align       Portable alignment, or null for an unwrapped button.
	 * @param string               $variant     Button style variant: primary, outline, or ghost.
	 * @param string               $font_family Resolved font-family CSS stack.
	 * @param array<string, mixed> $box         Resolved padding, corner radius, border, and type.
	 * @phpstan-param array{padding: array<string, int>, radius: array{topLeft: int, topRight: int, bottomRight: int, bottomLeft: int}|null, border: array{width: int, style: string, color: string}|null, font_size: int, line_height: int, font_weight: int, italic: bool} $box
	 */
	public static function html( string $url, string $label, string $background, string $text, ?string $align, string $variant, string $font_family, array $box ): string {
		$url         = Renderer_Support::html( $url );
		$label_html  = Renderer_Support::html( $label );
		$background  = Renderer_Support::html( $background );
		$text        = Renderer_Support::html( $text );
		$padding     = $box['padding'];
		$border      = $box['border'];
		$line_height = $box['line_height'];
		$type        = sprintf( 'font-family:%s;font-size:%dpx;font-weight:%d', $font_family, $box['font_size'], $box['font_weight'] ) . ( $box['italic'] ? ';font-style:italic' : '' );
		$radius_css  = Box_Style::radius_css( $box['radius'] );
		$fill        = 'ghost' === $variant || 'outline' === $variant ? 'transparent' : $background;

		$cell_style = ( '' === $radius_css ? '' : $radius_css . ';' ) . 'background-color:' . $fill
			. ( null === $border ? ( 'ghost' === $variant ? ';border:none' : '' ) : sprintf( ';border:%dpx %s %s', $border['width'], $border['style'], Renderer_Support::html( $border['color'] ) ) );
		$link_style = 'display:inline-block;' . Box_Style::spacing_css( 'padding', $padding ) . ';' . $type . ';line-height:' . $line_height . 'px;color:' . $text
			. ';text-decoration:' . ( 'ghost' === $variant ? 'underline' : 'none' ) . ( '' === $radius_css ? '' : ';' . $radius_css );

		$edge   = null === $border ? 0 : 2 * $border['width'];
		$height = $padding['top'] + $padding['bottom'] + $line_height + $edge;
		$width  = $padding['left'] + $padding['right'] + (int) ceil( mb_strlen( $label ) * $box['font_size'] * self::GLYPH_WIDTH ) + $edge;
		$arc    = null === $box['radius'] ? 0 : min( 50, (int) round( max( $box['radius'] ) * 100 / min( $height, $width ) ) );
		$paint  = ( null === $border ? 'stroke="f"' : sprintf( 'stroke="t" strokecolor="%s" strokeweight="%dpx"', Renderer_Support::html( $border['color'] ), $border['width'] ) )
			. ' fillcolor="' . ( 'transparent' === $fill ? 'none' : $fill ) . '"';
		$dash   = null === $border || 'solid' === $border['style'] ? '' : '<v:stroke dashstyle="' . ( 'dashed' === $border['style'] ? 'dash' : 'dot' ) . '"/>';

		$button = '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'
			. $url . '" style="height:' . $height . 'px;v-text-anchor:middle;width:' . $width . 'px" arcsize="' . $arc . '%" ' . $paint
			. '>' . $dash . '<w:anchorlock/><center style="color:' . $text . ';' . $type . '">'
			. $label_html . '</center></v:roundrect><![endif]--><!--[if !mso]><!--><table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate"><tr><td style="'
			. $cell_style . '"><a href="' . $url . '" style="' . $link_style . '">'
			. $label_html . '</a></td></tr></table><!--<![endif]-->';

		if ( null === $align ) {
			return $button;
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="'
			. Renderer_Support::html( $align ) . '">' . $button . '</td></tr></table>';
	}
}
