<?php
/**
 * Universal-profile email document renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Document_Renderer_Interface;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Renderer\Renderer_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Emits the universal email document shell and responsive baseline. */
final class Document_Renderer implements Document_Renderer_Interface {
	/**
	 * {@inheritDoc}
	 *
	 * @param string         $body    Compiled body HTML.
	 * @param Render_Context $context Immutable document context.
	 */
	public function render( string $body, Render_Context $context ): string {
		$language   = Renderer_Support::html( (string) $context->metadata( 'language', 'en' ) );
		$title      = Renderer_Support::html( (string) $context->metadata( 'title', 'CampaignBridge email' ) );
		$background = Renderer_Support::portable_color( $context->metadata( 'background_color' ) ) ?? '#f4f4f4';
		$font_links = $this->font_links( $context );

		return '<!doctype html>' . "\n"
			. '<html lang="' . $language . '">' . "\n"
			. '<head>' . "\n"
			. '<meta charset="utf-8">' . "\n"
			. '<meta name="viewport" content="width=device-width,initial-scale=1">' . "\n"
			. '<meta name="x-apple-disable-message-reformatting">' . "\n"
			. '<title>' . $title . '</title>' . "\n"
			. '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings xmlns:o="urn:schemas-microsoft-com:office:office"><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->' . "\n"
			. $font_links
			. '<style>@media only screen and (max-width:620px){.cb-email-container{width:100%!important}}@media only screen and (max-width:480px){.cb-columns-stack>tbody>tr>.cb-col{display:block!important;width:100%!important;box-sizing:border-box;padding-left:0!important;padding-right:0!important}}</style>' . "\n"
			. '</head>' . "\n"
			. '<body style="margin:0;padding:0;background-color:' . $background . ';-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">' . "\n"
			. $body . "\n"
			. '</body>' . "\n"
			. '</html>' . "\n";
	}

	/**
	 * Emit the Google Fonts stylesheet links for web fonts.
	 *
	 * Outlook ignores `<link>` tags and renders with its built-in fallback
	 * stack, so wrap every link in a non-MSO conditional. The stable CSS2 URL
	 * avoids persisting provider-specific WOFF2 file paths that can rotate. The
	 * inline fallback stack covers clients that strip the stylesheet.
	 *
	 * @param Render_Context $context Document context.
	 * @return string Empty string when no web fonts were referenced.
	 */
	private function font_links( Render_Context $context ): string {
		$fonts = $context->metadata( 'font_assets' );
		if ( ! is_array( $fonts ) || array() === $fonts ) {
			return '';
		}

		$links = '';
		$seen  = array();

		foreach ( $fonts as $font ) {
			if ( ! is_array( $font ) ) {
				continue;
			}

			$slug = $font['slug'] ?? null;
			$url  = $font['url'] ?? null;

			if ( ! is_string( $slug ) || '' === $slug || ! is_string( $url ) || '' === $url ) {
				continue;
			}

			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$links        .= '<!--[if !mso]><!-->' . "\n"
				. '<link rel="stylesheet" href="' . Renderer_Support::html( $url ) . '">' . "\n"
				. '<!--<![endif]-->' . "\n";
		}

		return $links;
	}
}
