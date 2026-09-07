import apiFetch from '@wordpress/api-fetch';

export interface PostItem {
  id: number | string;
  label?: string;
  title?: string | { rendered?: string };
  excerptPreview?: string;
}

/**
 * Default word cap for the excerpt preview.
 *
 * Keep in sync with `Rest_Constants::DEFAULT_EXCERPT_MAX_WORDS` (PHP).
 * The server clamps any value to [1, 500] (see Rest_Constants::EXCERPT_PREVIEW_MAX_WORDS).
 */
export const DEFAULT_EXCERPT_MAX_WORDS = 50;

const postRequests = new Map<string, Promise<PostItem[]>>();

/**
 * Share post-selector requests across every card using the same post type.
 *
 * Only in-flight requests are shared. The entry is evicted once the request
 * settles so a later mount — template switching no longer reloads the page —
 * sees posts published since.
 */
export function fetchPosts(
  postType: string,
  maxWords = DEFAULT_EXCERPT_MAX_WORDS
): Promise<PostItem[]> {
  const cacheKey = `${postType}:${maxWords}`;
  const cached = postRequests.get(cacheKey);
  if (cached) {
    return cached;
  }

  const params = new URLSearchParams({
    post_type: postType,
    maxWords: String(maxWords),
  });

  const request = apiFetch<{ items?: PostItem[] }>({
    path: `/campaignbridge/v1/posts?${params.toString()}`,
  }).then(response => response.items ?? []);

  const evict = () => {
    if (postRequests.get(cacheKey) === request) {
      postRequests.delete(cacheKey);
    }
  };

  // Settle handlers on both paths, so a rejection here is never unhandled.
  request.then(evict, evict);

  postRequests.set(cacheKey, request);
  return request;
}
