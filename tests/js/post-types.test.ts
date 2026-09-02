jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));

import apiFetch from '@wordpress/api-fetch';

import { fetchPostTypes } from '../../src/blocks/shared/post-types';

const mockApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

describe('fetchPostTypes', () => {
  it('shares one request across post cards and CTA blocks', async () => {
    mockApiFetch.mockResolvedValueOnce({
      items: [
        {
          id: 'post',
          label: 'Post',
          archive_url: 'https://example.com/news',
        },
      ],
    });

    const first = fetchPostTypes();
    const second = fetchPostTypes();

    await expect(first).resolves.toHaveLength(1);
    await expect(second).resolves.toHaveLength(1);
    expect(mockApiFetch).toHaveBeenCalledTimes(1);
  });
});
