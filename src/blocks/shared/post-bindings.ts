import { decodeEntities } from '@wordpress/html-entities';

import contract from '../../../includes/Email_Blocks/email-blocks.json';

/**
 * The one read-only CampaignBridge post binding contract.
 *
 * `includes/Email_Blocks/email-blocks.json` is the single source for the
 * binding source name and every supported block, attribute, field, and
 * argument. `Core_Block_Normalizer` reads the same file, so the editor can
 * only author bindings the compiler accepts.
 */
/** How a bound value is projected into its attribute. */
export type PostBindingProjection = 'rich-text' | 'url';

/**
 * One bindable field.
 *
 * `reads` names the immutable snapshot field supplying the value; `link`, when
 * present, names the snapshot field the renderer links that value to.
 */
export interface PostBindingFieldRule {
  reads: string;
  link?: string;
}

/** A bounded integer binding argument, such as a word cap. */
export interface PostBindingArgSchema {
  type: 'integer';
  min: number;
  max: number;
  default: number;
}

/** Everything one block attribute may be bound to. */
export interface PostBindingRule {
  projection: PostBindingProjection;
  fields: Record<string, PostBindingFieldRule>;
  args: Record<string, PostBindingArgSchema>;
}

/** The `postBindings` section of the email block contract. */
export interface PostBindingContract {
  source: string;
  attributes: Record<string, Record<string, PostBindingRule>>;
}

/*
 * The JSON is the source of truth, so TypeScript infers `projection` as a bare
 * `string` and each argument's `type` likewise. This single assertion narrows
 * those to the documented vocabulary; it never changes the shape, and
 * `Email_Block_Contract` validates the same file on the PHP side. The runtime
 * shape is asserted in tests/js/post-bindings.test.ts.
 */
const POST_BINDINGS = contract.postBindings as PostBindingContract;

/** The one supported binding source name. */
export const POST_BINDING_SOURCE = POST_BINDINGS.source;

/** Post snapshot fields the binding source exposes, per block attribute. */
export const POST_BINDING_ATTRIBUTES = POST_BINDINGS.attributes;

/** Default word cap for a bound excerpt, from the contract. */
export const EXCERPT_MAX_WORDS =
  POST_BINDING_ATTRIBUTES['core/paragraph']?.content?.args?.maxWords?.default ??
  50;

export interface PostBindingArgs {
  field: string;
  maxWords?: number;
}

/** Build the saved `metadata.bindings` entry for one bound attribute. */
export function postBinding(args: PostBindingArgs): {
  source: string;
  args: PostBindingArgs;
} {
  return { source: POST_BINDING_SOURCE, args };
}

/** Build a whole `metadata.bindings` map from attribute => binding args. */
export function postBindings(bindings: Record<string, PostBindingArgs>): {
  bindings: Record<string, { source: string; args: PostBindingArgs }>;
} {
  return {
    bindings: Object.fromEntries(
      Object.entries(bindings).map(([attribute, args]) => [
        attribute,
        postBinding(args),
      ])
    ),
  };
}

/**
 * Reduce rich post text to the bounded plain-text excerpt the compiler emits.
 *
 * Mirrors `Renderer_Support::truncate_words()` so the editor preview and the
 * compiled email agree: script and style bodies go first, then tags, then
 * entities are decoded exactly once by WordPress' own utility, which decodes
 * through a detached `textarea`. Decoding after stripping means encoded markup
 * such as `&amp;lt;strong&amp;gt;` stays text and can never become an element.
 */
export function truncateWords(raw: string, maxWords: number): string {
  const stripped = raw
    .replace(/<(script|style)[^>]*?>[\s\S]*?<\/\1>/gi, ' ')
    .replace(/<[^>]*>/g, ' ');
  const text = decodeEntities(stripped)
    .trim()
    .replace(/\u2026+$/, '');
  const words = text.split(/\s+/).filter(Boolean);

  return words.length <= maxWords
    ? words.join(' ')
    : `${words.slice(0, maxWords).join(' ')}\u2026`;
}
