import {
  requiredEditorFontUrls,
  syncEditorFontStylesheets,
} from '../../src/scripts/editor/editor-font-assets';

const assets = {
  inter:
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
  montserrat:
    'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
  playfair:
    'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap',
};

describe('native editor font assets', () => {
  it('loads resolved defaults and explicit nested overrides only', () => {
    const urls = requiredEditorFontUrls(
      [
        {
          name: 'campaignbridge/section',
          innerBlocks: [
            {
              name: 'core/heading',
              attributes: { fontFamily: 'montserrat' },
            },
            {
              name: 'core/paragraph',
              attributes: {
                style: {
                  typography: {
                    fontFamily: 'var:preset|font-family|inter',
                  },
                },
              },
            },
          ],
        },
      ],
      {
        campaignbridgeFontAssets: assets,
        campaignbridgeDefaultFonts: ['playfair', 'inter'],
      }
    );

    expect(urls).toEqual([assets.playfair, assets.inter, assets.montserrat]);
    expect(urls).toHaveLength(3);
  });

  it('does not load unreferenced or unknown font presets', () => {
    expect(
      requiredEditorFontUrls(
        [{ name: 'core/paragraph', attributes: { fontFamily: 'unknown' } }],
        {
          campaignbridgeFontAssets: assets,
          campaignbridgeDefaultFonts: ['inter'],
        }
      )
    ).toEqual([assets.inter]);
  });

  it('deduplicates canvas links and removes fonts no longer required', () => {
    const canvas = document.implementation.createHTMLDocument('Editor canvas');

    syncEditorFontStylesheets(canvas, [assets.inter, assets.inter]);
    syncEditorFontStylesheets(canvas, [assets.inter]);
    expect(
      canvas.head.querySelectorAll('link[data-campaignbridge-editor-font]')
    ).toHaveLength(1);

    syncEditorFontStylesheets(canvas, [assets.montserrat]);
    const links = Array.from(
      canvas.head.querySelectorAll<HTMLLinkElement>(
        'link[data-campaignbridge-editor-font]'
      )
    );
    expect(links).toHaveLength(1);
    expect(links[0].dataset.campaignbridgeEditorFont).toBe(assets.montserrat);
  });

  it('requests nothing when external font assets are unavailable', () => {
    expect(
      requiredEditorFontUrls([], {
        campaignbridgeFontAssets: {},
        campaignbridgeDefaultFonts: ['inter'],
      })
    ).toEqual([]);
  });
});
