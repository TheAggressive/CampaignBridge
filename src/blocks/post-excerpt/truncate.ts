import { decodeEntities } from '@wordpress/html-entities';

/**
 * Cap an excerpt to `maxWords` words, appending "…" only when it is cut.
 *
 * A trailing "…" from the source (e.g. WordPress' auto-excerpt) is stripped
 * before counting so the word budget means exactly `maxWords` visible words.
 * This mirrors `Renderer_Support::truncate_words()` on the email side.
 */
export function truncateExcerpt(raw: string, maxWords: number): string {
  const text = decodeEntities(raw)
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  const words = text.replace(/…+$/u, '').split(/\s+/).filter(Boolean);

  if (words.length <= maxWords) {
    return words.join(' ');
  }

  return `${words.slice(0, maxWords).join(' ')}…`;
}
