import contract from '../../../includes/Email_Blocks/email-blocks.json';

/**
 * The one read-only CampaignBridge post binding contract.
 *
 * `includes/Email_Blocks/email-blocks.json` is the single source for the
 * binding source name and every supported block, attribute, field, and
 * argument. `Core_Block_Normalizer` reads the same file, so the editor can
 * only author bindings the compiler accepts.
 */
export interface PostBindingRule {
  context: 'rich-text' | 'url';
  fields: string[];
  args: Record<
    string,
    { type: string; min: number; max: number; default: number }
  >;
}

const POST_BINDINGS = contract.postBindings as unknown as {
  source: string;
  attributes: Record<string, Record<string, PostBindingRule>>;
};

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
 * compiled email agree on the same cap.
 */
export function truncateWords(raw: string, maxWords: number): string {
  const text = raw
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#0?39;|&apos;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&hellip;/g, '…')
    .trim()
    .replace(/…+$/, '');
  const words = text.split(/\s+/).filter(Boolean);

  return words.length <= maxWords
    ? words.join(' ')
    : `${words.slice(0, maxWords).join(' ')}…`;
}
