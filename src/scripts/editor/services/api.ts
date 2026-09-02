import apiFetch from '@wordpress/api-fetch';

/**
 * Fetches a list of available email templates from the WordPress REST API.
 *
 * @return {Promise<Array<Object>>} Array of template objects with id, title, status, and date
 */
export async function listTemplates(
  includeDrafts = true
): Promise<Array<Record<string, any>>> {
  const status = includeDrafts ? 'draft,publish' : 'publish';
  const context = includeDrafts ? 'edit' : 'view';

  return apiFetch({
    path: `/wp/v2/cb_templates?per_page=100&status=${status}&context=${context}&_fields=id,title,status,date`,
  });
}

/**
 * Creates a new draft email template via the WordPress REST API.
 *
 * @param {string} [title] - Optional title for the new template
 * @return {Promise<Object>} The created template object with id and other properties
 */
export async function createDraft(
  title?: string
): Promise<Record<string, any>> {
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
