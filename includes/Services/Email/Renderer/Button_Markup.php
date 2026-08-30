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

/** Emits one portable CTA with a desktop Outlook VML fallback. */
final class Button_Markup {
	/**
	 * Render button HTML.
	 *
	 * @param string      $url        Valid HTTPS URL.
	 * @param string      $label      Validated button label.
	 * @param string      $background Portable background color.
	 * @param string      $text       Portable text color.
	 * @param string|null $align Portable alignment, or null for an unwrapped button.
	 * @param int         $width Outlook fallback width.
	 */
	public static function html( string $url, string $label, string $background, string $text, ?string $align, int $width = 200 ): string {
		$url        = Renderer_Support::html( $url );
		$label      = Renderer_Support::html( $label );
		$background = Renderer_Support::html( $background );
		$text       = Renderer_Support::html( $text );
		$button     = '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'
			. $url . '" style="height:44px;v-text-anchor:middle;width:' . $width . 'px" arcsize="9%" stroke="f" fillcolor="' . $background
			. '"><w:anchorlock/><center style="color:' . $text . ';font-family:Arial,sans-serif;font-size:16px;font-weight:bold">'
			. $label . '</center></v:roundrect><![endif]--><!--[if !mso]><!--><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-radius:4px;background-color:'
			. $background . '"><a href="' . $url . '" style="display:inline-block;padding:12px 24px;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;line-height:20px;color:'
			. $text . ';text-decoration:none">' . $label . '</a></td></tr></table><!--<![endif]-->';

		if ( null === $align ) {
			return $button;
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="'
			. Renderer_Support::html( $align ) . '">' . $button . '</td></tr></table>';
	}
}
