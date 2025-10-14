import apiFetch from "@wordpress/api-fetch";

/**
 * Fetches a list of available email templates from the WordPress REST API.
 *
 * @return {Promise<Array>} Array of template objects with id, title, status, and date
 */
export async function listTemplates() {
  return apiFetch({
    path: `/wp/v2/cb_email_template?per_page=100&status=publish&context=view&_fields=id,title,status,date`,
  });
}

/**
 * Creates a new draft email template via the WordPress REST API.
 *
 * @return {Promise<Object>} The created template object with id and other properties
 */
export async function createDraft(title) {
  return apiFetch({
    path: `/wp/v2/cb_email_template`,
    method: "POST",
    data: {
      status: "publish",
      title:
        title && String(title).trim()
          ? String(title).trim()
          : "Untitled template",
    },
  });
}

/**
 * Fetches a specific email template with full edit context from the WordPress REST API.
 *
 * @param {number} id - The ID of the template to fetch
 * @return {Promise<Object>} The template object with full edit context and embedded data
 */
export async function getPostRaw(id) {
  return apiFetch({
    path: `/wp/v2/cb_email_template/${id}?context=edit&_embed`,
  });
}

/**
 * Saves content and metadata for an existing email template via the REST API.
 *
 * @param {number} id                      Template ID
 * @param {Object} data                    Payload
 * @param {string} data.content            Serialized block content
 * @param {string} [data.status]           Optional post status
 * @param {string} [data.title]            Optional post title
 * @param {Object} [options]               fetch options (e.g., { signal })
 * @return {Promise<Object>}               Updated template
 */
export async function savePostContent(
  id,
  { content, status, title },
  options = {},
) {
  return apiFetch({
    path: `/wp/v2/cb_email_template/${id}`,
    method: "POST",
    data: {
      content,
      ...(status != null ? { status } : {}),
      ...(title != null ? { title } : {}),
    },
    ...options,
  });
}
