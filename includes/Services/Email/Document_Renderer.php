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
		$background = Renderer_Support::color( $context->metadata( 'background_color' ), '#f4f4f4' );

		return '<!doctype html>' . "\n"
			. '<html lang="' . $language . '">' . "\n"
			. '<head>' . "\n"
			. '<meta charset="utf-8">' . "\n"
			. '<meta name="viewport" content="width=device-width,initial-scale=1">' . "\n"
			. '<meta name="x-apple-disable-message-reformatting">' . "\n"
			. '<title>' . $title . '</title>' . "\n"
			. '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings xmlns:o="urn:schemas-microsoft-com:office:office"><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->' . "\n"
			. '<style>@media only screen and (max-width:620px){.cb-email-container{width:100%!important}.cb-email-cell{padding-left:16px!important;padding-right:16px!important}}</style>' . "\n"
			. '</head>' . "\n"
			. '<body style="margin:0;padding:0;background-color:' . $background . ';-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">' . "\n"
			. $body . "\n"
			. '</body>' . "\n"
			. '</html>' . "\n";
	}
}
