jest.mock('@wordpress/blocks', () => ({
  registerBlockBindingsSource: jest.fn(),
  registerBlockVariation: jest.fn(),
}));

import {
  registerBlockBindingsSource,
  registerBlockVariation,
} from '@wordpress/blocks';
import {
  BRAND_DATA_BINDING_SOURCE,
  BRAND_DATA_SOURCE,
  BRAND_LOGO_VARIATION,
} from '../../src/scripts/editor/brand-logo';
import {
  BRAND_BINDING_ATTRIBUTES,
  brandBinding,
} from '../../src/blocks/shared/brand-bindings';

const logo = {
  url: 'https://cdn.example.com/logo.png',
  alt: 'Example',
  width: 800,
  height: 240,
  link_url: 'https://example.com/',
};

const select = () => ({
  getSettings: () => ({ campaignbridgeBrandLogo: logo }),
});

describe('Brand Logo Core Image variation', () => {
  it('registers a read-only Brand Kit source', () => {
    expect(registerBlockBindingsSource).toHaveBeenCalledWith(
      BRAND_DATA_BINDING_SOURCE
    );
    expect(BRAND_DATA_BINDING_SOURCE.name).toBe(BRAND_DATA_SOURCE);
    expect(BRAND_DATA_BINDING_SOURCE).not.toHaveProperty('setValues');
    expect(BRAND_DATA_BINDING_SOURCE).not.toHaveProperty('canUserEditValue');
  });

  it('resolves only documented logo fields from editor settings', () => {
    expect(
      BRAND_DATA_BINDING_SOURCE.getValues({
        select,
        bindings: {
          url: { args: { field: 'logoUrl' } },
          alt: { args: { field: 'logoAlt' } },
          href: { args: { field: 'logoLink' } },
          unknown: { args: { field: 'other' } },
        },
      })
    ).toEqual({
      url: logo.url,
      alt: logo.alt,
      href: logo.link_url,
      unknown: undefined,
    });
  });

  it('registers a bounded Core Image variation', () => {
    expect(registerBlockVariation).toHaveBeenCalledWith(
      'core/image',
      BRAND_LOGO_VARIATION
    );
    expect(BRAND_LOGO_VARIATION.attributes).toEqual(
      expect.objectContaining({ width: 240, align: 'center' })
    );
    expect(BRAND_LOGO_VARIATION.attributes.metadata.bindings).toEqual({
      url: { source: BRAND_DATA_SOURCE, args: { field: 'logoUrl' } },
      alt: { source: BRAND_DATA_SOURCE, args: { field: 'logoAlt' } },
      href: { source: BRAND_DATA_SOURCE, args: { field: 'logoLink' } },
    });
  });

  it('derives its saved metadata from the packaged binding contract', () => {
    expect(Object.keys(BRAND_BINDING_ATTRIBUTES)).toEqual(['core/image']);
    expect(brandBinding('core/image', 'href')).toEqual({
      source: BRAND_DATA_SOURCE,
      args: { field: 'logoLink' },
    });
    expect(() => brandBinding('core/paragraph', 'content')).toThrow(
      'Unsupported Brand Kit binding'
    );
  });
});
