/** Title given to a template created without one. */
export const UNTITLED_TEMPLATE_TITLE = 'Untitled template';

/** The saved template values a duplicate is built from. */
export interface SavedTemplate {
  title?: string | { raw?: string };
  content?: string | { raw?: string };
  meta?: Record<string, unknown>;
}

/** The complete core REST create request for a template duplicate. */
export interface DuplicatePayload {
  status: 'draft';
  title: string;
  content: string;
  meta: Record<string, unknown>;
}

function rawValue(value: string | { raw?: string } | undefined): string {
  return typeof value === 'string' ? value : (value?.raw ?? '');
}

/**
 * Read the server's duplicable meta keys. Anything other than a JSON array of
 * strings returns null, so duplication refuses instead of guessing.
 */
export function parseDuplicableMetaKeys(
  value: string | undefined
): string[] | null {
  try {
    const keys: unknown = JSON.parse(value ?? '');
    return Array.isArray(keys) && keys.every(key => typeof key === 'string')
      ? keys
      : null;
  } catch {
    return null;
  }
}

/**
 * Build the create request for a duplicate of a saved template.
 *
 * Only the reusable template definition is copied: the title with a copy
 * suffix, the saved content, and the allowlisted metadata. The copy is always
 * a new draft; WordPress assigns its identity, author, and dates.
 */
export function buildDuplicatePayload(
  saved: SavedTemplate,
  duplicableMetaKeys: readonly string[]
): DuplicatePayload {
  const title = rawValue(saved.title).trim() || UNTITLED_TEMPLATE_TITLE;
  const meta: Record<string, unknown> = {};

  for (const key of duplicableMetaKeys) {
    if (saved.meta && Object.prototype.hasOwnProperty.call(saved.meta, key)) {
      const value = saved.meta[key];
      if (value !== undefined) {
        meta[key] = value;
      }
    }
  }

  return {
    status: 'draft',
    title: `${title} (Copy)`,
    content: rawValue(saved.content),
    meta,
  };
}
