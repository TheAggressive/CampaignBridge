import { parse, rawHandler, serialize, BlockInstance } from '@wordpress/blocks';

/**
 * Parses HTML content into WordPress blocks, handling both block markup and raw HTML.
 *
 * If the HTML contains WordPress block comments (<!-- wp:... -->), it parses them
 * as blocks. Otherwise, it converts raw HTML using the raw handler.
 */
export function parseOrConvert(html: string = ''): BlockInstance[] {
  return html.includes('<!-- wp:') ? parse(html) : rawHandler({ HTML: html });
}

/**
 * Safely serializes WordPress blocks to HTML, with error handling.
 *
 * Attempts to serialize the given blocks to HTML. If serialization fails,
 * returns an empty string instead of throwing an error.
 */
export function serializeSafe(blocks: BlockInstance[] = []): string {
  try {
    return serialize(blocks);
  } catch {
    return '';
  }
}
