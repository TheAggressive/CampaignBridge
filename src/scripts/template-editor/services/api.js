import apiFetch from "@wordpress/api-fetch";

const CFG = window.CB_TM || {};
const CPT = CFG.postType || "cb_email_template";

if (CFG.nonce) apiFetch.use(apiFetch.createNonceMiddleware(CFG.nonce));
if (CFG.apiRoot) apiFetch.use(apiFetch.createRootURLMiddleware(CFG.apiRoot));

/**
 * Fetches a list of available email templates from the WordPress REST API.
 *
 * @returns {Promise<Array>} Array of template objects with id, title, status, and date
 */
export async function listTemplates() {
  return apiFetch({
    path: `/wp/v2/${CPT}?per_page=100&_fields=id,title,status,date`,
  });
}

/**
 * Creates a new draft email template via the WordPress REST API.
 *
 * @returns {Promise<Object>} The created template object with id and other properties
 */
export async function createDraft() {
  return apiFetch({
    path: `/wp/v2/${CPT}`,
    method: "POST",
    data: { status: "draft", title: CFG.defaultTitle || "Untitled" },
  });
}

/**
 * Fetches a specific email template with full edit context from the WordPress REST API.
 *
 * @param {number} id - The ID of the template to fetch
 * @returns {Promise<Object>} The template object with full edit context and embedded data
 */
export async function getPostRaw(id) {
  return apiFetch({ path: `/wp/v2/${CPT}/${id}?context=edit&_embed` });
}

/**
 * Saves content and metadata for an existing email template via the WordPress REST API.
 *
 * @param {number} id - The ID of the template to update
 * @param {Object} data - The data to update
 * @param {string} data.content - The block content to save
 * @param {string} [data.status="draft"] - The post status (defaults to "draft")
 * @param {string} [data.title] - The post title (optional)
 * @returns {Promise<Object>} The updated template object
 */
export async function savePostContent(
  id,
  { content, status = "draft", title },
) {
  return apiFetch({
    path: `/wp/v2/${CPT}/${id}`,
    method: "POST",
    data: { content, status, ...(title != null ? { title } : {}) },
  });
}
