import apiFetch from '@wordpress/api-fetch';

export interface PostTypeItem {
  id: string;
  label: string;
  archive_url: string | null;
}

let postTypesRequest: Promise<PostTypeItem[]> | null = null;

/**
 * Load the configured post types once per editor session.
 *
 * Post cards and their CTA children share this request so a template with
 * several cards does not issue duplicate REST requests.
 */
export function fetchPostTypes(): Promise<PostTypeItem[]> {
  if (postTypesRequest) {
    return postTypesRequest;
  }

  postTypesRequest = apiFetch<{ items?: PostTypeItem[] }>({
    path: '/campaignbridge/v1/post-types',
  })
    .then(response => response.items ?? [])
    .catch(error => {
      postTypesRequest = null;
      throw error;
    });

  return postTypesRequest;
}
