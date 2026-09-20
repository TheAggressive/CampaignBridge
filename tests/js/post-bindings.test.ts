jest.mock('@wordpress/blocks', () => ({
  registerBlockBindingsSource: jest.fn(),
}));

import { registerBlockBindingsSource } from '@wordpress/blocks';
import { POST_DATA_SOURCE } from '../../src/scripts/editor/post-bindings';
import {
  POST_BINDING_ATTRIBUTES,
  POST_BINDING_SOURCE,
  postBindings,
  truncateWords,
} from '../../src/blocks/shared/post-bindings';

type Store = {
  getEntityRecord: (kind: string, name: string, id: number) => unknown;
};

const POSTS: Record<number, Record<string, unknown>> = {
  7: {
    link: 'https://example.com/posts/7',
    parent: 4,
    excerpt: { rendered: '<p>One two three four five six seven eight.</p>' },
  },
  4: { link: 'https://example.com/parent' },
  9: {
    link: 'https://example.com/posts/9',
    parent: 0,
    excerpt: { rendered: '<p>A different post entirely.</p>' },
  },
};

const select = (store: unknown): unknown => {
  if (store === 'core/block-editor') {
    return {
      getSettings: () => ({
        campaignbridgePostTypeArchives: { post: 'https://example.com/news' },
      }),
    };
  }

  return {
    getEntityRecord: (_kind: string, _name: string, id: number) =>
      POSTS[id] ?? null,
  } as Store;
};

const context = (postId: number) => ({
  'campaignbridge:postId': postId,
  'campaignbridge:postType': 'post',
});

const values = (postId: number, args: Record<string, unknown>) =>
  POST_DATA_SOURCE.getValues({
    select,
    context: context(postId),
    bindings: { field: { args } },
  } as never);

describe('campaignbridge/post-data binding source', () => {
  it('registers under the one contract source name', () => {
    expect(registerBlockBindingsSource).toHaveBeenCalledWith(POST_DATA_SOURCE);
    expect(POST_DATA_SOURCE.name).toBe(POST_BINDING_SOURCE);
  });

  it('is read-only: it can never write back to the source post', () => {
    // WordPress disables editing of a bound attribute when the source defines
    // no canUserEditValue, and silently drops writes when it has no setValues.
    expect(POST_DATA_SOURCE).not.toHaveProperty('setValues');
    expect(POST_DATA_SOURCE).not.toHaveProperty('canUserEditValue');
  });

  it('reads the selected post from Post Card context alone', () => {
    expect(POST_DATA_SOURCE.usesContext).toEqual([
      'campaignbridge:postId',
      'campaignbridge:postType',
    ]);
    expect(values(0, { field: 'url' })).toEqual({ field: undefined });
  });

  it('resolves every field the contract allows to be bound', () => {
    expect(values(7, { field: 'url' })).toEqual({
      field: 'https://example.com/posts/7',
    });
    expect(values(7, { field: 'postParentUrl' })).toEqual({
      field: 'https://example.com/parent',
    });
    expect(values(7, { field: 'postTypeArchiveUrl' })).toEqual({
      field: 'https://example.com/news',
    });
    expect(values(7, { field: 'excerpt', maxWords: 3 })).toEqual({
      field: 'One two three…',
    });
  });

  it('follows the Post Card to another selected post', () => {
    expect(values(9, { field: 'excerpt', maxWords: 50 })).toEqual({
      field: 'A different post entirely.',
    });
    expect(values(9, { field: 'postParentUrl' })).toEqual({ field: undefined });
  });

  it('resolves nothing for a field outside the contract', () => {
    expect(values(7, { field: 'post_password' })).toEqual({ field: undefined });
  });

  it('only advertises fields once a post is selected', () => {
    expect(POST_DATA_SOURCE.getFieldsList({ context: context(0) })).toEqual([]);
    expect(
      POST_DATA_SOURCE.getFieldsList({ context: context(7) }).map(
        item => item.args.field
      )
    ).toEqual([
      'title',
      'titleLink',
      'excerpt',
      'content',
      'url',
      'postParentUrl',
      'postTypeArchiveUrl',
    ]);
  });
});

describe('post binding contract helpers', () => {
  it('builds metadata that names the one supported source', () => {
    expect(
      postBindings({ content: { field: 'excerpt', maxWords: 50 } })
    ).toEqual({
      bindings: {
        content: {
          source: 'campaignbridge/post-data',
          args: { field: 'excerpt', maxWords: 50 },
        },
      },
    });
  });

  it('exposes exactly the bindable Core attributes', () => {
    expect(Object.keys(POST_BINDING_ATTRIBUTES)).toEqual([
      'core/heading',
      'core/paragraph',
      'core/button',
    ]);
    expect(
      Object.keys(POST_BINDING_ATTRIBUTES['core/heading'].content.fields)
    ).toEqual(['title', 'titleLink']);
    expect(
      Object.keys(POST_BINDING_ATTRIBUTES['core/paragraph'].content.fields)
    ).toEqual(['excerpt', 'content']);
    expect(
      Object.keys(POST_BINDING_ATTRIBUTES['core/button'].url.fields)
    ).toEqual(['url', 'postParentUrl', 'postTypeArchiveUrl']);
  });

  it('caps excerpt words the same way the compiler does', () => {
    expect(truncateWords('<p>a b c d</p>', 2)).toBe('a b…');
    expect(truncateWords('a b', 5)).toBe('a b');
    expect(truncateWords('Tom &amp; Jerry&hellip;', 10)).toBe('Tom & Jerry');
  });
});
