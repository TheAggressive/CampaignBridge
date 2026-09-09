<?php
/**
 * Universal-profile email document renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;
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
		$font_css   = $this->font_face_css( $context );
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
			. '<style>' . $font_css . '@media only screen and (max-width:620px){.cb-email-container{width:100%!important}}@media only screen and (max-width:480px){.cb-columns-stack>tbody>tr>.cb-col{display:block!important;width:100%!important;box-sizing:border-box;padding-left:0!important;padding-right:0!important}}</style>' . "\n"
			. '</head>' . "\n"
			. '<body style="margin:0;padding:0;background-color:' . $background . ';-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">' . "\n"
			. $body . "\n"
			. '</body>' . "\n"
			. '</html>' . "\n";
	}

	/**
	 * Inline the pinned @font-face CSS for referenced web fonts.
	 *
	 * The preset catalogue ships pre-fetched @font-face blocks so the
	 * compiled email declares its web faces without an external
	 * stylesheet request. Slugs outside the catalogue produce no CSS
	 * here and keep their stylesheet link as the fallback.
	 *
	 * @param Render_Context $context Document context.
	 * @return string Joined @font-face rules, or an empty string when none.
	 */
	private function font_face_css( Render_Context $context ): string {
		$fonts = $context->metadata( 'font_assets' );
		if ( ! is_array( $fonts ) || array() === $fonts ) {
			return '';
		}

		$css  = '';
		$seen = array();

		foreach ( $fonts as $font ) {
			if ( ! is_array( $font ) ) {
				continue;
			}

			$slug = $font['slug'] ?? null;

			if ( ! is_string( $slug ) || '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$face = $this->font_face( $slug, $context );
			if ( '' === $face ) {
				continue;
			}

			$seen[ $slug ] = true;
			$css          .= $face;
		}

		return $css;
	}

	/**
	 * Emit the Google Fonts stylesheet links for web fonts.
	 *
	 * Outlook ignores `<link>` tags and renders with its built-in fallback
	 * stack, so wrap every link in a `<!--[if !mso]><!-->...<!--<![endif]-->`
	 * conditional. The inline `font-family` fallback stack on each element
	 * covers Outlook and any client that strips the stylesheet. Fonts whose
	 * pinned @font-face CSS is inlined in the style block carry no link.
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

			// Pinned inline @font-face CSS replaces the stylesheet link.
			if ( '' !== $this->font_face( $slug, $context ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$links        .= '<!--[if !mso]><!-->' . "\n"
				. '<link rel="stylesheet" href="' . Renderer_Support::html( $url ) . '">' . "\n"
				. '<!--<![endif]-->' . "\n";
		}

		return $links;
	}

	/**
	 * Resolve pinned catalogue or brand-kit face CSS.
	 *
	 * @param string         $slug    Font slug.
	 * @param Render_Context $context Compile context.
	 */
	private function font_face( string $slug, Render_Context $context ): string {
		if ( Brand_Kit::CUSTOM_FONT_SLUG === $slug ) {
			return Renderer_Support::brand_kit( $context )->custom_font()['css'] ?? '';
		}

		return Design_Presets::font_face( $slug );
	}
}
