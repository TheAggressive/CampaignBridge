import { addFilter } from '@wordpress/hooks';
import { emailDesignSetting } from '../../src/scripts/editor/editor-design-settings';

jest.mock('@wordpress/hooks', () => ({ addFilter: jest.fn() }));
jest.mock('../../src/scripts/editor/editor-design-config', () => ({
  editorDesignConfig: {
    features: {
      color: {
        custom: false,
        palette: { theme: [{ slug: 'brand', color: '#123456' }] },
      },
      typography: {
        fontFamilies: {
          theme: [
            {
              slug: 'inter',
              name: 'Inter',
              fontFamily: 'Inter,Arial,sans-serif',
            },
          ],
        },
      },
    },
    fontAssets: {},
    defaultFonts: [],
  },
}));

describe('email design settings', () => {
  it('registers the native useSetting override', () => {
    expect(addFilter).toHaveBeenCalledWith(
      'blockEditor.useSetting.before',
      'campaignbridge/email-design',
      emailDesignSetting
    );
  });

  it('returns CampaignBridge catalogs and restrictions for owned paths', () => {
    expect(
      emailDesignSetting(undefined, 'typography.fontFamilies.theme')
    ).toEqual([
      {
        slug: 'inter',
        name: 'Inter',
        fontFamily: 'Inter,Arial,sans-serif',
      },
    ]);
    expect(emailDesignSetting(true, 'color.custom')).toBe(false);
    expect(emailDesignSetting('site', 'color.palette.theme')).toEqual([
      { slug: 'brand', color: '#123456' },
    ]);
  });

  it('leaves unrelated Core settings and missing values untouched', () => {
    expect(emailDesignSetting('site', 'layout.contentSize')).toBe('site');
    expect(emailDesignSetting('site', 'color.gradients.theme')).toBe('site');
  });
});
