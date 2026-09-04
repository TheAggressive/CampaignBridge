import apiFetch from '@wordpress/api-fetch';

/**
 * Creates a new draft email template via the WordPress REST API.
 *
 * @param {string} [title] - Optional title for the new template
 * @return {Promise<Object>} The created template object with id and other properties
 */
export async function createDraft(title?: string): Promise<{ id: number }> {
  return apiFetch({
    path: `/wp/v2/cb_templates`,
    method: 'POST',
    data: {
      status: 'draft',
      content:
        '<!-- wp:campaignbridge/container --><!-- /wp:campaignbridge/container -->',
      title:
        title && String(title).trim()
          ? String(title).trim()
          : 'Untitled template',
    },
  });
}
