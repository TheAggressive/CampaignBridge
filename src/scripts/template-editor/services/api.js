import apiFetch from '@wordpress/api-fetch';

/**
 * Fetches a list of available email templates from the WordPress REST API.
 *
 * @return {Promise<Array>} Array of template objects with id, title, status, and date
 */
export async function listTemplates() {
	return apiFetch( {
		path: `/wp/v2/cb_email_template?per_page=100&_fields=id,title,status,date`,
	} );
}

/**
 * Creates a new draft email template via the WordPress REST API.
 *
 * @return {Promise<Object>} The created template object with id and other properties
 */
export async function createDraft() {
	return apiFetch( {
		path: `/wp/v2/cb_email_template`,
		method: 'POST',
		data: { status: 'draft', title: 'Untitled template' },
	} );
}

/**
 * Fetches a specific email template with full edit context from the WordPress REST API.
 *
 * @param {number} id - The ID of the template to fetch
 * @return {Promise<Object>} The template object with full edit context and embedded data
 */
export async function getPostRaw( id ) {
	return apiFetch( {
		path: `/wp/v2/cb_email_template/${ id }?context=edit&_embed`,
	} );
}

/**
 * Saves content and metadata for an existing email template via the WordPress REST API.
 *
 * @param {number} id                    - The ID of the template to update
 * @param {Object} data                  - The data to update
 * @param {string} data.content          - The block content to save
 * @param {string} [data.status="draft"] - The post status (defaults to "draft")
 * @param {string} [data.title]          - The post title (optional)
 * @return {Promise<Object>} The updated template object
 */
export async function savePostContent(
	id,
	{ content, status = 'draft', title }
) {
	return apiFetch( {
		path: `/wp/v2/cb_email_template/${ id }`,
		method: 'POST',
		data: { content, status, ...( title != null ? { title } : {} ) },
	} );
}
