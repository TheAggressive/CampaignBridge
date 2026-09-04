import apiFetch from '@wordpress/api-fetch';

export interface PostItem {
  id: number | string;
  label?: string;
  title?: string | { rendered?: string };
}

const postRequests = new Map<string, Promise<PostItem[]>>();

/**
 * Share post-selector requests across every card using the same post type.
 *
 * Only in-flight requests are shared. The entry is evicted once the request
 * settles so a later mount — template switching no longer reloads the page —
 * sees posts published since.
 */
export function fetchPosts(postType: string): Promise<PostItem[]> {
  const cached = postRequests.get(postType);
  if (cached) {
    return cached;
  }

  const request = apiFetch<{ items?: PostItem[] }>({
    path: `/campaignbridge/v1/posts?post_type=${encodeURIComponent(postType)}`,
  }).then(response => response.items ?? []);

  const evict = () => {
    if (postRequests.get(postType) === request) {
      postRequests.delete(postType);
    }
  };

  // Settle handlers on both paths, so a rejection here is never unhandled.
  request.then(evict, evict);

  postRequests.set(postType, request);
  return request;
}
