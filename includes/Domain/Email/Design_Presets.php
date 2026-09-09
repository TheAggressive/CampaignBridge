<?php
/**
 * Email design presets.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The palette, type scale, and spacing scale email templates are built from.
 *
 * These are deliberately CampaignBridge's own rather than the site theme's.
 * A theme palette is designed for a browser with a stylesheet; an email has
 * neither, and every value here has to survive being inlined into a table
 * cell. Keeping one definition in PHP means the editor offers exactly the
 * values the compiler can resolve.
 */
final class Design_Presets {
	/**
	 * Colour presets.
	 *
	 * @var array<int, array{slug: string, name: string, color: string}>
	 */
	private const COLORS = array(
		array(
			'slug'  => 'text',
			'name'  => 'Text',
			'color' => '#111111',
		),
		array(
			'slug'  => 'secondary',
			'name'  => 'Secondary text',
			'color' => '#666666',
		),
		array(
			'slug'  => 'background',
			'name'  => 'Background',
			'color' => '#ffffff',
		),
		array(
			'slug'  => 'card',
			'name'  => 'Card',
			'color' => '#f4f4f4',
		),
		array(
			'slug'  => 'border',
			'name'  => 'Border',
			'color' => '#e0e0e0',
		),
		array(
			'slug'  => 'brand',
			'name'  => 'Brand',
			'color' => '#1a6dcc',
		),
		array(
			'slug'  => 'on-brand',
			'name'  => 'On brand',
			'color' => '#ffffff',
		),
	);

	/**
	 * Font size presets, in pixels.
	 *
	 * @var array<int, array{slug: string, name: string, size: string}>
	 */
	private const FONT_SIZES = array(
		array(
			'slug' => 'small',
			'name' => 'Small',
			'size' => '14px',
		),
		array(
			'slug' => 'medium',
			'name' => 'Medium',
			'size' => '16px',
		),
		array(
			'slug' => 'large',
			'name' => 'Large',
			'size' => '20px',
		),
		array(
			'slug' => 'x-large',
			'name' => 'Extra large',
			'size' => '28px',
		),
		array(
			'slug' => 'xx-large',
			'name' => 'Huge',
			'size' => '36px',
		),
	);

	/**
	 * Spacing presets, in pixels.
	 *
	 * Numeric slugs match the convention core uses for spacing scales, so the
	 * editor renders them as an ordered scale rather than arbitrary names.
	 *
	 * @var array<int, array{slug: string, name: string, size: string}>
	 */
	private const SPACING_SIZES = array(
		array(
			'slug' => '20',
			'name' => '1',
			'size' => '8px',
		),
		array(
			'slug' => '30',
			'name' => '2',
			'size' => '12px',
		),
		array(
			'slug' => '40',
			'name' => '3',
			'size' => '16px',
		),
		array(
			'slug' => '50',
			'name' => '4',
			'size' => '24px',
		),
		array(
			'slug' => '60',
			'name' => '5',
			'size' => '32px',
		),
		array(
			'slug' => '70',
			'name' => '6',
			'size' => '48px',
		),
		array(
			'slug' => '80',
			'name' => '7',
			'size' => '64px',
		),
	);

	/**
	 * The curated type catalogue.
	 *
	 * Every entry's `family` is a complete, portable inline font stack: the
	 * primary family followed by the safe fallbacks. System fonts are already
	 * present on the reader's machine and never need a download; web fonts are
	 * fetched from Google Fonts only when actually used by a compiled email.
	 * The stack is always inlined so email clients that ignore the external
	 * stylesheet still render a sensible face.
	 *
	 * @var array<int, array{slug: string, name: string, family: string, type: string, weights: array<int, int>, url: string|null}>
	 */
	private const FONTS = array(
		array(
			'slug'    => 'arial',
			'name'    => 'Arial',
			'family'  => 'Arial,Helvetica,sans-serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'helvetica',
			'name'    => 'Helvetica',
			'family'  => 'Helvetica,Arial,sans-serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'verdana',
			'name'    => 'Verdana',
			'family'  => 'Verdana,Geneva,sans-serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'tahoma',
			'name'    => 'Tahoma',
			'family'  => 'Tahoma,Geneva,sans-serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'trebuchet-ms',
			'name'    => 'Trebuchet MS',
			'family'  => '"Trebuchet MS",Helvetica,sans-serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'georgia',
			'name'    => 'Georgia',
			'family'  => 'Georgia,"Times New Roman",serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'times-new-roman',
			'name'    => 'Times New Roman',
			'family'  => '"Times New Roman",Times,serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'courier-new',
			'name'    => 'Courier New',
			'family'  => '"Courier New",Courier,monospace',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		),
		array(
			'slug'    => 'inter',
			'name'    => 'Inter',
			'family'  => 'Inter,Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'open-sans',
			'name'    => 'Open Sans',
			'family'  => '"Open Sans",Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'roboto',
			'name'    => 'Roboto',
			'family'  => 'Roboto,Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'lato',
			'name'    => 'Lato',
			'family'  => 'Lato,Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Lato:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'montserrat',
			'name'    => 'Montserrat',
			'family'  => 'Montserrat,Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'merriweather',
			'name'    => 'Merriweather',
			'family'  => 'Merriweather,Georgia,serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'playfair',
			'name'    => 'Playfair Display',
			'family'  => 'Playfair Display,Georgia,serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'source-sans-3',
			'name'    => 'Source Sans 3',
			'family'  => 'Source Sans 3,Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'cormorant',
			'name'    => 'Cormorant Garamond',
			'family'  => 'Cormorant Garamond,Georgia,serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'noto-sans',
			'name'    => 'Noto Sans',
			'family'  => 'Noto Sans,Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'karla',
			'name'    => 'Karla',
			'family'  => '"Karla",Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Karla:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'oswald',
			'name'    => 'Oswald',
			'family'  => '"Oswald",Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'raleway',
			'name'    => 'Raleway',
			'family'  => '"Raleway",Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700&display=swap',
		),
		array(
			'slug'    => 'fira-sans',
			'name'    => 'Fira Sans',
			'family'  => '"Fira Sans",Arial,Helvetica,sans-serif',
			'type'    => 'web',
			'weights' => array( 400, 600, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;600;700&display=swap',
		),
	);

	/**
	 * The @font-face CSS for each web font, keyed by catalogue slug.
	 *
	 * These are the exact Google Fonts faces the catalogue offers, captured once
	 * (latin and latin-ext, woff2) and embedded in the compiled email instead of a
	 * <link> tag. Many clients strip head <link> tags, so an inline @font-face is
	 * the reliable way to actually render a web font; the inline font stack on
	 * every element still covers clients that ignore web fonts altogether.
	 *
	 * @var array<string, string>
	 */
	public const FONT_FACES = array(
		'inter'         => "@font-face{font-family:'Inter';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Inter';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Inter';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Inter';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Inter';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Inter';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'open-sans'     => "@font-face{font-family:'Open Sans';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/opensans/v44/memvYaGs126MiZpBA-UvWbX2vVnXBbObj2OVTSGmu1aB.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Open Sans';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/opensans/v44/memvYaGs126MiZpBA-UvWbX2vVnXBbObj2OVTS-muw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Open Sans';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/opensans/v44/memvYaGs126MiZpBA-UvWbX2vVnXBbObj2OVTSGmu1aB.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Open Sans';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/opensans/v44/memvYaGs126MiZpBA-UvWbX2vVnXBbObj2OVTS-muw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Open Sans';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/opensans/v44/memvYaGs126MiZpBA-UvWbX2vVnXBbObj2OVTSGmu1aB.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Open Sans';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/opensans/v44/memvYaGs126MiZpBA-UvWbX2vVnXBbObj2OVTS-muw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'roboto'        => "@font-face{font-family:'Roboto';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/roboto/v51/KFO7CnqEu92Fr1ME7kSn66aGLdTylUAMa3KUBGEe.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Roboto';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/roboto/v51/KFO7CnqEu92Fr1ME7kSn66aGLdTylUAMa3yUBA.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Roboto';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/roboto/v51/KFO7CnqEu92Fr1ME7kSn66aGLdTylUAMa3KUBGEe.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Roboto';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/roboto/v51/KFO7CnqEu92Fr1ME7kSn66aGLdTylUAMa3yUBA.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Roboto';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/roboto/v51/KFO7CnqEu92Fr1ME7kSn66aGLdTylUAMa3KUBGEe.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Roboto';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/roboto/v51/KFO7CnqEu92Fr1ME7kSn66aGLdTylUAMa3yUBA.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'lato'          => "@font-face{font-family:'Lato';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/lato/v25/S6uyw4BMUTPHjxAwXjeu.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Lato';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/lato/v25/S6uyw4BMUTPHjx4wXg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Lato';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/lato/v25/S6u9w4BMUTPHh6UVSwaPGR_p.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Lato';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/lato/v25/S6u9w4BMUTPHh6UVSwiPGQ.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'montserrat'    => "@font-face{font-family:'Montserrat';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/montserrat/v31/JTUSjIg1_i6t8kCHKm459Wdhyzbi.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/montserrat/v31/JTUSjIg1_i6t8kCHKm459Wlhyw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/montserrat/v31/JTUSjIg1_i6t8kCHKm459Wdhyzbi.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/montserrat/v31/JTUSjIg1_i6t8kCHKm459Wlhyw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/montserrat/v31/JTUSjIg1_i6t8kCHKm459Wdhyzbi.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/montserrat/v31/JTUSjIg1_i6t8kCHKm459Wlhyw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'merriweather'  => "@font-face{font-family:'Merriweather';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/merriweather/v33/u-4e0qyriQwlOrhSvowK_l5UcA6zuSYEqOzpPe3HOZJ5eX1WtLaQwmYiSeqkJ-mFqA.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Merriweather';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/merriweather/v33/u-4e0qyriQwlOrhSvowK_l5UcA6zuSYEqOzpPe3HOZJ5eX1WtLaQwmYiSeqqJ-k.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Merriweather';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/merriweather/v33/u-4e0qyriQwlOrhSvowK_l5UcA6zuSYEqOzpPe3HOZJ5eX1WtLaQwmYiSeqkJ-mFqA.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Merriweather';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/merriweather/v33/u-4e0qyriQwlOrhSvowK_l5UcA6zuSYEqOzpPe3HOZJ5eX1WtLaQwmYiSeqqJ-k.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Merriweather';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/merriweather/v33/u-4e0qyriQwlOrhSvowK_l5UcA6zuSYEqOzpPe3HOZJ5eX1WtLaQwmYiSeqkJ-mFqA.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Merriweather';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/merriweather/v33/u-4e0qyriQwlOrhSvowK_l5UcA6zuSYEqOzpPe3HOZJ5eX1WtLaQwmYiSeqqJ-k.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'playfair'      => "@font-face{font-family:'Playfair Display';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/playfairdisplay/v40/nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Playfair Display';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/playfairdisplay/v40/nuFiD-vYSZviVYUb_rj3ij__anPXDTzYgA.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Playfair Display';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/playfairdisplay/v40/nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Playfair Display';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/playfairdisplay/v40/nuFiD-vYSZviVYUb_rj3ij__anPXDTzYgA.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Playfair Display';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/playfairdisplay/v40/nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Playfair Display';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/playfairdisplay/v40/nuFiD-vYSZviVYUb_rj3ij__anPXDTzYgA.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'source-sans-3' => "@font-face{font-family:'Source Sans 3';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/sourcesans3/v27/xjAJXh3jI1xMMFsHgSE_w0OCwD3Q.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Source Sans 3';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/sourcesans3/v27/xjAJXh3jI1xMMFsHgmE_w0OC.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Source Sans 3';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/sourcesans3/v27/xjAJXh3jI1xMMFsHgSE_w0OCwD3Q.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Source Sans 3';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/sourcesans3/v27/xjAJXh3jI1xMMFsHgmE_w0OC.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Source Sans 3';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/sourcesans3/v27/xjAJXh3jI1xMMFsHgSE_w0OCwD3Q.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Source Sans 3';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/sourcesans3/v27/xjAJXh3jI1xMMFsHgmE_w0OC.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'cormorant'     => "@font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/cormorantgaramond/v16/co3bmX5slCNuHLH80ZSComcB7g98qFb0.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/cormorantgaramond/v16/co3bmX5slCNuHLH80ZSComcB7w98qFb0.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/cormorantgaramond/v16/co3bmX5slCNuHLH80ZSComcB7g98qFb0.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/cormorantgaramond/v16/co3bmX5slCNuHLH80ZSComcB7w98qFb0.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/cormorantgaramond/v16/co3bmX5slCNuHLH80ZSComcB7g98qFb0.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/cormorantgaramond/v16/co3bmX5slCNuHLH80ZSComcB7w98qFb0.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'noto-sans'     => "@font-face{font-family:'Noto Sans';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/notosans/v43/o-0IIpQHUcVZdvUtgYMO6w.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Noto Sans';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/notosans/v43/o-0IIpQHUcVZdvUN7QMO6w.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Noto Sans';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/notosans/v43/o-0IIpQHUcVZdvUtgYMO6w.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Noto Sans';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/notosans/v43/o-0IIpQHUcVZdvUN7QMO6w.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Noto Sans';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/notosans/v43/o-0IIpQHUcVZdvUtgYMO6w.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Noto Sans';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/notosans/v43/o-0IIpQHUcVZdvUN7QMO6w.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'karla'         => "@font-face{font-family:'Karla';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/karla/v33/qkB9XvYC6trAT55ZBi1ueQVIjQTD-JrIH2G7nytkHRyQ8p4wUjm6bnEr.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Karla';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/karla/v33/qkB9XvYC6trAT55ZBi1ueQVIjQTD-JrIH2G7nytkHRyQ8p4wUje6bg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Karla';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/karla/v33/qkB9XvYC6trAT55ZBi1ueQVIjQTD-JrIH2G7nytkHRyQ8p4wUjm6bnEr.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Karla';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/karla/v33/qkB9XvYC6trAT55ZBi1ueQVIjQTD-JrIH2G7nytkHRyQ8p4wUje6bg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Karla';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/karla/v33/qkB9XvYC6trAT55ZBi1ueQVIjQTD-JrIH2G7nytkHRyQ8p4wUjm6bnEr.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Karla';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/karla/v33/qkB9XvYC6trAT55ZBi1ueQVIjQTD-JrIH2G7nytkHRyQ8p4wUje6bg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'oswald'        => "@font-face{font-family:'Oswald';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/oswald/v57/TK3IWkUHHAIjg75cFRf3bXL8LICs1_Fv40pKlN4NNSeSASz7FmlYHYjedg.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Oswald';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/oswald/v57/TK3IWkUHHAIjg75cFRf3bXL8LICs1_Fv40pKlN4NNSeSASz7FmlWHYg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Oswald';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/oswald/v57/TK3IWkUHHAIjg75cFRf3bXL8LICs1_Fv40pKlN4NNSeSASz7FmlYHYjedg.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Oswald';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/oswald/v57/TK3IWkUHHAIjg75cFRf3bXL8LICs1_Fv40pKlN4NNSeSASz7FmlWHYg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Oswald';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/oswald/v57/TK3IWkUHHAIjg75cFRf3bXL8LICs1_Fv40pKlN4NNSeSASz7FmlYHYjedg.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Oswald';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/oswald/v57/TK3IWkUHHAIjg75cFRf3bXL8LICs1_Fv40pKlN4NNSeSASz7FmlWHYg.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'raleway'       => "@font-face{font-family:'Raleway';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyCMIT5lu.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Raleway';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyC0ITw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Raleway';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyCMIT5lu.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Raleway';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyC0ITw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Raleway';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyCMIT5lu.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Raleway';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyC0ITw.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
		'fira-sans'     => "@font-face{font-family:'Fira Sans';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/firasans/v18/va9E4kDNxMZdWfMOD5VvmYjLeTY.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Fira Sans';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/firasans/v18/va9E4kDNxMZdWfMOD5Vvl4jL.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Fira Sans';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/firasans/v18/va9B4kDNxMZdWfMOD5VnSKzeSBf6TF0.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Fira Sans';font-style:normal;font-weight:600;font-display:swap;src:url(https://fonts.gstatic.com/s/firasans/v18/va9B4kDNxMZdWfMOD5VnSKzeRhf6.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}
@font-face{font-family:'Fira Sans';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/firasans/v18/va9B4kDNxMZdWfMOD5VnLK3eSBf6TF0.woff2) format('woff2');unicode-range:U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;}
@font-face{font-family:'Fira Sans';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/firasans/v18/va9B4kDNxMZdWfMOD5VnLK3eRhf6.woff2) format('woff2');unicode-range:U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;}",
	);

	/**
	 * The safe default slug used when a font cannot be resolved.
	 *
	 * @var string
	 */
	private const DEFAULT_FONT = 'arial';

	/**
	 * Get the colour presets.
	 *
	 * @return array<int, array{slug: string, name: string, color: string}>
	 */
	public static function colors(): array {
		return self::COLORS;
	}

	/**
	 * Get the font size presets.
	 *
	 * @return array<int, array{slug: string, name: string, size: string}>
	 */
	public static function font_sizes(): array {
		return self::FONT_SIZES;
	}

	/**
	 * Get the spacing presets.
	 *
	 * @return array<int, array{slug: string, name: string, size: string}>
	 */
	public static function spacing_sizes(): array {
		return self::SPACING_SIZES;
	}

	/**
	 * Resolve a colour preset slug to its portable value.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function color( string $slug ): ?string {
		foreach ( self::COLORS as $preset ) {
			if ( $preset['slug'] === $slug ) {
				return $preset['color'];
			}
		}

		return null;
	}

	/**
	 * Resolve a font size preset slug to its declared size.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function font_size( string $slug ): ?string {
		return self::size_of( self::FONT_SIZES, $slug );
	}

	/**
	 * Resolve a spacing preset slug to its declared size.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function spacing( string $slug ): ?string {
		return self::size_of( self::SPACING_SIZES, $slug );
	}

	/**
	 * Get the font catalogue.
	 *
	 * @return array<int, array{slug: string, name: string, family: string, type: string, weights: array<int, int>, url: string|null}>
	 */
	public static function fonts(): array {
		return self::FONTS;
	}

	/**
	 * The slugs the compiler knows how to resolve.
	 *
	 * @return array<int, string>
	 */
	public static function font_slugs(): array {
		return array_map(
			static fn( array $font ): string => $font['slug'],
			self::FONTS
		);
	}

	/**
	 * Resolve a font slug to its full catalogue entry, or null.
	 *
	 * The CampaignBridge catalogue is authoritative: an unknown or foreign
	 * slug never resolves, so callers fall back to a known-safe face rather
	 * than shipping an arbitrary font stack into email HTML.
	 *
	 * @param string $slug Preset slug.
	 * @return array{slug: string, name: string, family: string, type: string, weights: array<int, int>, url: string|null}|null
	 */
	public static function font( string $slug ): ?array {
		foreach ( self::FONTS as $font ) {
			if ( $font['slug'] === $slug ) {
				return $font;
			}
		}

		return null;
	}

	/**
	 * The safe default font entry, always resolvable.
	 *
	 * @return array{slug: string, name: string, family: string, type: string, weights: array<int, int>, url: string|null}
	 */
	public static function default_font(): array {
		$font = self::font( self::DEFAULT_FONT );

		return $font ?? array(  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- unreachable, the default slug is always present.
			'slug'    => 'arial',
			'name'    => 'Arial',
			'family'  => 'Arial,Helvetica,sans-serif',
			'type'    => 'system',
			'weights' => array( 400, 600, 700 ),
			'url'     => null,
		);
	}

	/**
	 * The @font-face CSS for a web font slug, or an empty string for system fonts.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function font_face( string $slug ): string {
		return self::FONT_FACES[ $slug ] ?? '';
	}

	/**
	 * Look up a size in a preset table.
	 *
	 * @param array<int, array{slug: string, name: string, size: string}> $presets Preset table.
	 * @param string                                                      $slug    Preset slug.
	 */
	private static function size_of( array $presets, string $slug ): ?string {
		foreach ( $presets as $preset ) {
			if ( $preset['slug'] === $slug ) {
				return $preset['size'];
			}
		}

		return null;
	}
}
