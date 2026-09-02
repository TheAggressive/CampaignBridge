import apiFetch from '@wordpress/api-fetch';

export interface PostItem {
  id: number | string;
  label?: string;
  title?: string | { rendered?: string };
}

const postRequests = new Map<string, Promise<PostItem[]>>();

/** Share post-selector requests across every card using the same post type. */
export function fetchPosts(postType: string): Promise<PostItem[]> {
  const cached = postRequests.get(postType);
  if (cached) {
    return cached;
  }

  const request = apiFetch<{ items?: PostItem[] }>({
    path: `/campaignbridge/v1/posts?post_type=${encodeURIComponent(postType)}`,
  })
    .then(response => response.items ?? [])
    .catch(error => {
      postRequests.delete(postType);
      throw error;
    });

  postRequests.set(postType, request);
  return request;
}
