import { parse, rawHandler, serialize } from '@wordpress/blocks';

/**
 * Parses HTML content into WordPress blocks, handling both block markup and raw HTML.
 *
 * If the HTML contains WordPress block comments (<!-- wp:... -->), it parses them
 * as blocks. Otherwise, it converts raw HTML using the raw handler.
 *
 * @param {string} [html=""] - The HTML content to parse
 * @return {Array} Array of parsed block objects
 */
export function parseOrConvert( html = '' ) {
	// console.log("raw", console.log(html));
	// console.log("html", parse(html), rawHandler({ HTML: html }));
	return html.includes( '<!-- wp:' )
		? parse( html )
		: rawHandler( { HTML: html } );
}

/**
 * Safely serializes WordPress blocks to HTML, with error handling.
 *
 * Attempts to serialize the given blocks to HTML. If serialization fails,
 * returns an empty string instead of throwing an error.
 *
 * @param {Array} [blocks=[]] - Array of block objects to serialize
 * @return {string} The serialized HTML content, or empty string on error
 */
export function serializeSafe( blocks = [] ) {
	try {
		return serialize( blocks );
	} catch {
		return '';
	}
}
