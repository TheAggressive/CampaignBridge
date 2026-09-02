jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));

import apiFetch from '@wordpress/api-fetch';

import { fetchPosts } from '../../src/blocks/shared/posts';

const mockApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

describe('fetchPosts', () => {
  it('shares one selector request for each post type', async () => {
    mockApiFetch.mockResolvedValueOnce({
      items: [{ id: 42, title: 'Campaign update' }],
    });

    const first = fetchPosts('post');
    const second = fetchPosts('post');

    await expect(first).resolves.toHaveLength(1);
    await expect(second).resolves.toHaveLength(1);
    expect(mockApiFetch).toHaveBeenCalledTimes(1);
  });
});
