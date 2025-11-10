<?php
/**
 * Email Structure Builder for CampaignBridge.
 *
 * Handles building email headers and footers with proper HTML structure
 * and responsive design for email compatibility.
 *
 * @package CampaignBridge\Services\Email
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Structure Builder
 *
 * Builds email HTML structure including DOCTYPE, head, body, and responsive styling.
 */
class EmailStructure {
	/**
	 * Build email header with proper DOCTYPE and meta tags.
	 *
	 * @param array<string, mixed> $options Generation options.
	 * @return string Email header HTML.
	 */
	public function build_header( array $options ): string {
		$width       = $options['email_width'];
		$max_width   = $options['max_width'];
		$bg_color    = $options['background_color'];
		$text_color  = $options['text_color'];
		$font_family = $options['font_family'];

		return sprintf(
			'<!DOCTYPE html>
<html lang="%s">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>%s</title>
	<!--[if mso]>
	<noscript>
		<xml>
			<o:OfficeDocumentSettings>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	</noscript>
	<![endif]-->
	<style type="text/css">
		/* Reset styles for email clients */
		body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%%; -ms-text-size-adjust: 100%%; }
		table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

		/* Responsive design */
		@media only screen and (max-width: %dpx) {
			.email-container { width: 100%% !important; max-width: 100%% !important; }
			.email-content { padding: 16px !important; }
			.mobile-stack { display: block !important; width: 100%% !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: %s; font-family: %s; color: %s;">
	<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background-color: %s;">
		<tr>
			<td align="center" style="padding: 20px 0;">
				<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="%d" class="email-container" style="max-width: %dpx; margin: 0 auto; background-color: %s; border-radius: 8px; overflow: hidden;">
					<tr>
						<td class="email-content" style="padding: 20px;">',
			esc_attr( get_locale() ),
			esc_html( get_bloginfo( 'name' ) ),
			$width,
			esc_attr( $bg_color ),
			esc_attr( $font_family ),
			esc_attr( $text_color ),
			esc_attr( $bg_color ),
			$width,
			$max_width,
			esc_attr( $bg_color )
		);
	}

	/**
	 * Build email footer to close HTML structure.
	 *
	 * @param array<string, mixed> $options Generation options.
	 * @return string Email footer HTML.
	 */
	public function build_footer( array $options ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
	}
}
