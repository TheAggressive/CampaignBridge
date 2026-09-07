import { createBlock, getBlockType, type Block } from '@wordpress/blocks';

/**
 * Shared transform helpers for `campaignbridge/post-button` and
 * `campaignbridge/post-link` block transforms.
 *
 * Both transforms create a new block of a sibling type with a small set of
 * mapped attributes. `safeCreateBlock` guards against an unregistered target
 * (returns `null` so WordPress skips the transform); `present` normalizes
 * "missing" attribute values (null/undefined/empty-string) to a single
 * check; `align` narrows a free-form string to the three supported
 * alignments.
 */
export function safeCreateBlock(
  name: string,
  attributes: Record<string, unknown>
): Block | null {
  if (!getBlockType(name)) {
    return null;
  }
  return createBlock(name, attributes);
}

export function present(value: unknown): value is NonNullable<unknown> {
  if (value === null || value === undefined) {
    return false;
  }
  if (typeof value === 'string') {
    return value.trim() !== '';
  }
  return true;
}

export function align(value: unknown): 'left' | 'center' | 'right' | undefined {
  return value === 'left' || value === 'center' || value === 'right'
    ? value
    : undefined;
}
