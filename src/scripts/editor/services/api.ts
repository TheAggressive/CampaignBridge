import apiFetch from '@wordpress/api-fetch';

/**
 * Fetches a list of available email templates from the WordPress REST API.
 *
 * @return {Promise<Array<Object>>} Array of template objects with id, title, status, and date
 */
export async function listTemplates(): Promise<Array<Record<string, any>>> {
  return apiFetch({
    path: `/wp/v2/cb_templates?per_page=100&status=publish&context=view&_fields=id,title,status,date`,
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
      status: 'publish',
      title:
        title && String(title).trim()
          ? String(title).trim()
          : 'Untitled template',
    },
  });
}

/**
 * Fetches a specific email template with full edit context from the WordPress REST API.
 *
 * @param {number} id - The ID of the template to fetch
 * @return {Promise<Object>} The template object with full edit context and embedded data
 */
export async function getPostRaw(id: number): Promise<Record<string, any>> {
  return apiFetch({
    path: `/wp/v2/cb_templates/${id}?context=edit&_embed`,
  });
}

/**
 * Saves content and metadata for an existing email template via the REST API.
 *
 * @param {number} id - Template ID to update
 * @param {Object} data - Payload object containing update data
 * @param {string} data.content - Serialized block content
 * @param {string} [data.status] - Optional post status
 * @param {string} [data.title] - Optional post title
 * @param {Object} [options={}] - Additional fetch options (e.g., AbortSignal)
 * @return {Promise<Object>} Updated template object
 */
export async function savePostContent(
  id: number,
  {
    content,
    status,
    title,
  }: { content: string; status?: string; title?: string },
  options: Record<string, any> = {}
) {
  return apiFetch({
    path: `/wp/v2/cb_templates/${id}`,
    method: 'POST',
    data: {
      content,
      ...(status != null ? { status } : {}),
      ...(title != null ? { title } : {}),
    },
    ...options,
  });
}
