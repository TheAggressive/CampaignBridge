import {
  POST_BINDING_ATTRIBUTES,
  POST_BINDING_SOURCE,
} from '../../src/blocks/shared/post-bindings';

/**
 * The inspector panel offers exactly the combinations the contract documents.
 *
 * WordPress' own bindings panel reads one source-wide field list and cannot be
 * scoped per attribute, so these assertions guard the narrower vocabulary the
 * CampaignBridge panel presents against the file the compiler validates with.
 */
describe('post binding inspector vocabulary', () => {
  it('offers only the fields each bound attribute accepts', () => {
    expect(
      Object.keys(POST_BINDING_ATTRIBUTES['core/paragraph'].content.fields)
    ).toEqual(['excerpt', 'content']);
    expect(
      Object.keys(POST_BINDING_ATTRIBUTES['core/heading'].content.fields)
    ).toEqual(['title', 'titleLink']);
    expect(
      Object.keys(POST_BINDING_ATTRIBUTES['core/button'].url.fields)
    ).toEqual(['url', 'postParentUrl', 'postTypeArchiveUrl']);
  });

  it('bounds the word cap the panel exposes for post text', () => {
    const { maxWords } = POST_BINDING_ATTRIBUTES['core/paragraph'].content.args;

    expect(maxWords).toEqual({
      type: 'integer',
      min: 10,
      max: 500,
      default: 50,
    });
  });

  it('gives the linked and unlinked title separate fields, not an attribute', () => {
    const { fields } = POST_BINDING_ATTRIBUTES['core/heading'].content;

    expect(fields.title).toEqual({ reads: 'title' });
    expect(fields.titleLink).toEqual({ reads: 'title', link: 'url' });
  });

  it('declares no word cap where the contract has none', () => {
    expect(POST_BINDING_ATTRIBUTES['core/heading'].content.args).toEqual({});
    expect(POST_BINDING_ATTRIBUTES['core/button'].url.args).toEqual({});
  });

  it('names one source for every bound attribute', () => {
    expect(POST_BINDING_SOURCE).toBe('campaignbridge/post-data');
  });
});
