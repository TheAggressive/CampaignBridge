import type { FontOption } from './types';

/**
 * Google Fonts CSS2 endpoint URLs for the web fonts selected across the
 * given slots.
 *
 * System fonts and options without a URL are skipped; a font shared by
 * several slots is emitted once.
 *
 * @param options Catalogue options from the brand kit payload.
 * @param selected Slot to font slug selection.
 * @returns CSS stylesheet URLs, empty when nothing is selected.
 */
export function webFontUrls(
  options: FontOption[],
  selected: Record<string, string>
): string[] {
  const selectedSlugs = new Set(Object.values(selected));
  const urls = new Set<string>();

  for (const option of options) {
    if (
      selectedSlugs.has(option.slug) &&
      typeof option.url === 'string' &&
      option.url
    ) {
      urls.add(option.url);
    }
  }

  return Array.from(urls);
}
