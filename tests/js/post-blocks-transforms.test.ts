jest.mock('@wordpress/blocks', () => ({
  __esModule: true,
  createBlock: jest.fn(),
  getBlockType: jest.fn(),
}));

import { createBlock, getBlockType } from '@wordpress/blocks';
import { transforms as buttonTransforms } from '../../src/blocks/post-button/transforms';
import { transforms as linkTransforms } from '../../src/blocks/post-link/transforms';

const mockCreateBlock = createBlock as jest.MockedFunction<typeof createBlock>;
const mockGetBlockType = getBlockType as jest.MockedFunction<
  typeof getBlockType
>;

function getTransform(targetBlock: string) {
  const entry =
    targetBlock === 'campaignbridge/post-link'
      ? buttonTransforms.to[0]
      : linkTransforms.to[0];

  return entry.transform as (source: unknown) => unknown;
}

describe('post-button → post-link transform', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetBlockType.mockReturnValue({});
    mockCreateBlock.mockImplementation((name, attrs) => ({
      name,
      attrs,
      clientId: 'test-id',
    }));
  });

  it('targets campaignbridge/post-link', () => {
    expect(buttonTransforms.to[0].type).toBe('block');
    expect(buttonTransforms.to[0].blocks).toEqual(['campaignbridge/post-link']);
  });

  it('preserves label, destination, customUrl, and align', () => {
    const transform = getTransform('campaignbridge/post-link');
    transform({
      label: 'View article',
      destination: 'custom',
      customUrl: 'https://example.com',
      backgroundColor: '#111111',
      textColor: '#ffffff',
      align: 'center',
      style: 'button',
      linkColor: '#0066cc',
    });

    expect(mockCreateBlock).toHaveBeenCalledWith(
      'campaignbridge/post-link',
      expect.objectContaining({
        label: 'View article',
        destination: 'custom',
        customUrl: 'https://example.com',
        align: 'center',
      })
    );
  });

  it('maps linkColor into the link block linkColor', () => {
    const transform = getTransform('campaignbridge/post-link');
    transform({
      label: 'Read more',
      destination: 'article',
      customUrl: '',
      backgroundColor: '#111111',
      textColor: '#ffffff',
      align: 'left',
      style: 'button',
      linkColor: '#0066cc',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs.linkColor).toBe('#0066cc');
  });

  it('falls back to textColor when linkColor is empty', () => {
    const transform = getTransform('campaignbridge/post-link');
    transform({
      label: 'Read more',
      destination: 'article',
      customUrl: '',
      backgroundColor: '#111111',
      textColor: '#aabbcc',
      align: 'left',
      style: 'button',
      linkColor: '',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs.linkColor).toBe('#aabbcc');
  });

  it('does not leak backgroundColor or style into the link block', () => {
    const transform = getTransform('campaignbridge/post-link');
    transform({
      label: 'Read more',
      destination: 'article',
      customUrl: '',
      backgroundColor: '#ff0000',
      textColor: '#000000',
      align: 'left',
      style: 'button',
      linkColor: '',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs).not.toHaveProperty('backgroundColor');
    expect(attrs).not.toHaveProperty('style');
  });

  it('omits empty strings so block.json defaults apply', () => {
    const transform = getTransform('campaignbridge/post-link');
    transform({
      label: '',
      destination: '',
      customUrl: '',
      backgroundColor: '#111111',
      textColor: '',
      align: 'invalid',
      style: 'button',
      linkColor: '',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs.label).toBeUndefined();
    expect(attrs.destination).toBeUndefined();
    expect(attrs.customUrl).toBeUndefined();
    expect(attrs.linkColor).toBeUndefined();
    expect(attrs.align).toBeUndefined();
  });

  it('returns null when target block type is not registered', () => {
    mockGetBlockType.mockReturnValue(null);
    const transform = getTransform('campaignbridge/post-link');
    const result = transform({
      label: 'Read more',
      destination: 'article',
    });

    expect(result).toBeNull();
    expect(mockCreateBlock).not.toHaveBeenCalled();
  });
});

describe('post-link → post-button transform', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetBlockType.mockReturnValue({});
    mockCreateBlock.mockImplementation((name, attrs) => ({
      name,
      attrs,
      clientId: 'test-id',
    }));
  });

  it('targets campaignbridge/post-button', () => {
    expect(linkTransforms.to[0].type).toBe('block');
    expect(linkTransforms.to[0].blocks).toEqual(['campaignbridge/post-button']);
  });

  it('preserves label, destination, customUrl, and align', () => {
    const transform = getTransform('campaignbridge/post-button');
    transform({
      label: 'View article',
      destination: 'custom',
      customUrl: 'https://example.com',
      linkColor: '#0066cc',
      align: 'center',
    });

    expect(mockCreateBlock).toHaveBeenCalledWith(
      'campaignbridge/post-button',
      expect.objectContaining({
        label: 'View article',
        destination: 'custom',
        customUrl: 'https://example.com',
        align: 'center',
      })
    );
  });

  it('maps the link color into the button textColor', () => {
    const transform = getTransform('campaignbridge/post-button');
    transform({
      label: 'Read more',
      destination: 'article',
      customUrl: '',
      linkColor: '#0066cc',
      align: 'left',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs.textColor).toBe('#0066cc');
  });

  it('does not hard-code backgroundColor — lets block.json defaults apply', () => {
    const transform = getTransform('campaignbridge/post-button');
    transform({
      label: 'Read more',
      destination: 'article',
      customUrl: '',
      linkColor: '#0066cc',
      align: 'left',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs).not.toHaveProperty('backgroundColor');
    expect(attrs).not.toHaveProperty('style');
  });

  it('omits empty strings so block.json defaults apply', () => {
    const transform = getTransform('campaignbridge/post-button');
    transform({
      label: '',
      destination: '',
      customUrl: '',
      linkColor: '',
      align: 'invalid',
    });

    const attrs = mockCreateBlock.mock.calls[0][1] as Record<string, unknown>;
    expect(attrs.label).toBeUndefined();
    expect(attrs.destination).toBeUndefined();
    expect(attrs.customUrl).toBeUndefined();
    expect(attrs.textColor).toBeUndefined();
    expect(attrs.align).toBeUndefined();
  });

  it('returns null when target block type is not registered', () => {
    mockGetBlockType.mockReturnValue(null);
    const transform = getTransform('campaignbridge/post-button');
    const result = transform({
      label: 'Read more',
      destination: 'article',
    });

    expect(result).toBeNull();
    expect(mockCreateBlock).not.toHaveBeenCalled();
  });
});
