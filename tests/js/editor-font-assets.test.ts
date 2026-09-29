import {
  requiredEditorFontUrls,
  syncEditorFontStylesheets,
  syncEditorTypeFontStyles,
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

  it('loads independently selected custom font presets', () => {
    const customAssets = {
      'custom-a1b2c3d4e5f6':
        'https://fonts.googleapis.com/css2?family=Example+Sans:wght@400;700&display=swap',
      'custom-f6e5d4c3b2a1':
        'https://fonts.googleapis.com/css2?family=Example+Serif:wght@400&display=swap',
    };
    const urls = requiredEditorFontUrls(
      [
        {
          name: 'core/heading',
          attributes: { fontFamily: 'custom-a1b2c3d4e5f6' },
        },
      ],
      {
        campaignbridgeFontAssets: customAssets,
        campaignbridgeDefaultFonts: ['custom-f6e5d4c3b2a1'],
      }
    );

    expect(urls).toEqual([
      customAssets['custom-f6e5d4c3b2a1'],
      customAssets['custom-a1b2c3d4e5f6'],
    ]);
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

  it('updates and removes live semantic type-slot styles', () => {
    const canvas = document.implementation.createHTMLDocument('Editor canvas');

    syncEditorTypeFontStyles(
      canvas,
      {
        body: 'Inter,Arial,sans-serif',
        'custom-72cae0925f81': 'Campaign Display,Georgia,serif',
      },
      { body: 'body', heading: 'custom-72cae0925f81' }
    );

    const style = canvas.head.querySelector<HTMLStyleElement>(
      'style[data-campaignbridge-editor-type-fonts]'
    );
    expect(style?.textContent).toContain(
      '[data-type="core/heading"]){font-family:Campaign Display,Georgia,serif}'
    );
    expect(style?.textContent).toContain(
      '.has-custom-72-cae-0925-f-81-font-family{font-family:Campaign Display,Georgia,serif}'
    );
    expect(style?.textContent).toContain('font-family:Inter,Arial,sans-serif');

    syncEditorTypeFontStyles(canvas, {}, {});
    expect(
      canvas.head.querySelector('style[data-campaignbridge-editor-type-fonts]')
    ).toBeNull();
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
