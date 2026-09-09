import { webFontUrls } from '../../src/scripts/admin/brand-kit/fonts';

const system = {
  slug: 'arial',
  name: 'Arial',
  type: 'system',
  family: 'Arial,Helvetica,sans-serif',
  url: null,
};

const inter = {
  slug: 'inter',
  name: 'Inter',
  type: 'web',
  family: 'Inter,Arial,sans-serif',
  url: 'https://fonts.googleapis.com/css2?family=Inter&display=swap',
};

const lato = {
  slug: 'lato',
  name: 'Lato',
  type: 'web',
  family: 'Lato,Arial,sans-serif',
  url: 'https://fonts.googleapis.com/css2?family=Lato&display=swap',
};

describe('webFontUrls', () => {
  it('returns empty array when no web fonts are selected', () => {
    expect(webFontUrls([system, inter], { heading: 'arial' })).toEqual([]);
  });

  it('returns the CSS2 URL for each selected web font', () => {
    const urls = webFontUrls([system, inter, lato], {
      heading: 'inter',
      body: 'lato',
      button: 'arial',
    });
    expect(urls).toContain(inter.url);
    expect(urls).toContain(lato.url);
    expect(urls).toHaveLength(2);
  });

  it('emits URL once when several slots share a font', () => {
    const urls = webFontUrls([inter], { heading: 'inter', body: 'inter' });
    expect(urls).toEqual([inter.url]);
  });

  it('skips web options without a URL value', () => {
    const legacy = {
      slug: 'inter',
      name: 'Inter',
      type: 'web',
      family: 'Inter,Arial,sans-serif',
    };
    expect(webFontUrls([legacy], { heading: 'inter' })).toEqual([]);
  });

  it('returns the URL for a custom user-added font', () => {
    const custom = {
      slug: 'custom-font',
      name: 'My Custom Font',
      type: 'web',
      family: 'My Custom Font,sans-serif',
      url: 'https://fonts.googleapis.com/css2?family=My+Custom+Font&display=swap',
    };
    const urls = webFontUrls([custom, system], {
      heading: 'custom-font',
      body: 'arial',
    });
    expect(urls).toEqual([custom.url]);
  });

  it('handles empty options array', () => {
    expect(webFontUrls([], { heading: 'inter' })).toEqual([]);
  });
});
