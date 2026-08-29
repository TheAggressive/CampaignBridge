import { Block, parse, rawHandler, serialize } from '@wordpress/blocks';

/**
 * Parses HTML content into WordPress blocks, handling both block markup and raw HTML.
 *
 * If the HTML contains WordPress block comments (<!-- wp:... -->), it parses them
 * as blocks. Otherwise, it converts raw HTML using the raw handler.
 */
export function parseOrConvert(html: string = ''): Block[] {
  return html.includes('<!-- wp:') ? parse(html) : rawHandler({ HTML: html });
}

/**
 * Safely serializes WordPress blocks to HTML, with error handling.
 *
 * Attempts to serialize the given blocks to HTML. If serialization fails,
 * returns an empty string instead of throwing an error.
 */
export function serializeSafe(blocks: Block[] = []): string {
  try {
    return serialize(blocks);
  } catch {
    return '';
  }
}
