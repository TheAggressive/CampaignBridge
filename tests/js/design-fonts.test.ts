import { editorDesignConfig } from '../../src/scripts/editor/editor-design-config';
import {
  parseDesignFontRegistry,
  serializeDesignFontRegistry,
  synchronizeEditorDesignFonts,
} from '../../src/scripts/editor/design-fonts';

const font = {
  slug: 'custom-a1b2c3d4e5f6',
  name: 'Campaign Display',
  family: 'Campaign Display,Georgia,serif',
  weights: [400, 700],
  url: 'https://fonts.googleapis.com/css2?family=Campaign+Display:wght@400;700&display=swap',
};

describe('template design font registry', () => {
  beforeEach(() => {
    editorDesignConfig.features = {
      typography: {
        fontFamilies: {
          theme: [
            {
              slug: 'arial',
              name: 'Arial',
              fontFamily: 'Arial,Helvetica,sans-serif',
            },
          ],
        },
      },
    };
    editorDesignConfig.fontAssets = {};
    editorDesignConfig.defaultFonts = ['arial'];
    editorDesignConfig.designFontSlugs = [];
    editorDesignConfig.baseFontSlots = {
      heading: 'arial',
      body: 'arial',
      button: 'arial',
    };
  });

  it('parses and serializes validated provider records and type slots', () => {
    const registry = parseDesignFontRegistry(
      JSON.stringify({
        version: 1,
        fonts: [font],
        slots: { heading: font.slug, unknown: 'inter' },
      })
    );

    expect(registry).toEqual({
      version: 1,
      fonts: [font],
      slots: { heading: font.slug },
    });
    expect(
      parseDesignFontRegistry(serializeDesignFontRegistry(registry))
    ).toEqual(registry);
  });

  it('fails closed for malformed provider data', () => {
    const registry = parseDesignFontRegistry(
      JSON.stringify({
        version: 1,
        fonts: [{ ...font, family: 'Bad;}body{display:none' }],
        slots: { heading: font.slug },
      })
    );

    expect(registry.fonts).toEqual([]);
  });

  it('adds and removes template presets without disturbing base fonts', () => {
    synchronizeEditorDesignFonts({
      version: 1,
      fonts: [font],
      slots: { heading: font.slug },
    });

    expect(
      (
        editorDesignConfig.features.typography as {
          fontFamilies: { theme: Array<{ slug: string }> };
        }
      ).fontFamilies.theme.map(item => item.slug)
    ).toEqual(['arial', font.slug]);
    expect(editorDesignConfig.fontAssets[font.slug]).toBe(font.url);
    expect(editorDesignConfig.defaultFonts).toEqual([font.slug, 'arial']);

    synchronizeEditorDesignFonts({ version: 1, fonts: [], slots: {} });

    expect(
      (
        editorDesignConfig.features.typography as {
          fontFamilies: { theme: Array<{ slug: string }> };
        }
      ).fontFamilies.theme.map(item => item.slug)
    ).toEqual(['arial']);
    expect(editorDesignConfig.fontAssets).toEqual({});
    expect(editorDesignConfig.defaultFonts).toEqual(['arial']);
  });
});
