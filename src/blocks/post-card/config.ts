import { __ } from '@wordpress/i18n';

import { EMAIL_BLOCK_NESTING } from '../shared/nesting';
import { EXCERPT_MAX_WORDS, postBindings } from '../shared/post-bindings';

/** Child blocks supported by the post-card email grammar. */
export const POST_CARD_ALLOWED_BLOCKS = [...EMAIL_BLOCK_NESTING['post-card']];

/** A `core/heading` bound read-only to the selected post's title. */
export const boundTitle = (
  level = 2,
  linkToPost = false
): Record<string, unknown> => ({
  level,
  metadata: postBindings({
    content: { field: linkToPost ? 'titleLink' : 'title' },
  }),
});

/**
 * A `core/paragraph` bound read-only to the selected post's text.
 *
 * `excerpt` takes the post's summary; `content` takes the post body reduced to
 * plain text. Both are capped by the same bounded `maxWords` argument.
 */
export const boundText = (
  field: 'excerpt' | 'content' = 'excerpt',
  maxWords: number = EXCERPT_MAX_WORDS
): Record<string, unknown> => ({
  metadata: postBindings({ content: { field, maxWords } }),
});

/** A `core/button` whose URL is bound read-only to the selected post. */
export const boundButton = (
  label: string,
  className?: string
): Record<string, unknown> => ({
  text: label,
  ...(className ? { className } : {}),
  metadata: postBindings({ url: { field: 'url' } }),
});

/**
 * Initial post-card structure, declared on the block type for Gutenberg.
 *
 * Deliberately not derived from the allowed list: columns is a layout choice
 * an author opts into, so a new card starts as the stacked post composition.
 */
export type PostCardTemplate = Array<
  | [string]
  | [string, Record<string, unknown>]
  | [string, Record<string, unknown>, unknown[]]
>;

export const POST_CARD_TEMPLATE: PostCardTemplate = [
  ['campaignbridge/post-image'],
  ['core/heading', boundTitle()],
  ['core/paragraph', boundText()],
  [
    'core/buttons',
    {},
    [['core/button', boundButton(__('Read more', 'campaignbridge'))]],
  ],
];
