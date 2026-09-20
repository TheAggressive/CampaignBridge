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

/** Named HTML entities the editor preview decodes, mirroring `ENT_QUOTES`. */
const NAMED_ENTITIES: Record<string, string> = {
  nbsp: ' ',
  amp: '&',
  lt: '<',
  gt: '>',
  quot: '"',
  apos: "'",
  hellip: '\u2026',
};

/** Highest code point a numeric entity may decode to. */
const MAX_CODE_POINT = 0x10ffff;

/**
 * Decode HTML entities in exactly one pass.
 *
 * Replacing entities one kind at a time would decode twice: `&amp;lt;` would
 * become `&lt;` and then `<`, inventing markup the source never contained.
 * Matching every entity in a single scan means a replacement's own output is
 * never re-examined, so the text is decoded exactly one level, the way PHP's
 * `html_entity_decode()` does on the compiler side.
 */
function decodeEntitiesOnce(text: string): string {
  return text.replace(
    /&(?:#(\d{1,7})|#[xX]([0-9a-fA-F]{1,6})|([a-zA-Z][a-zA-Z0-9]{1,31}));/g,
    (match, decimal?: string, hexadecimal?: string, name?: string) => {
      if (decimal !== undefined || hexadecimal !== undefined) {
        const code =
          decimal !== undefined
            ? Number.parseInt(decimal, 10)
            : Number.parseInt(hexadecimal as string, 16);

        return Number.isInteger(code) && code > 0 && code <= MAX_CODE_POINT
          ? String.fromCodePoint(code)
          : match;
      }

      return NAMED_ENTITIES[(name as string).toLowerCase()] ?? match;
    }
  );
}

/**
 * Reduce rich post text to the bounded plain-text excerpt the compiler emits.
 *
 * Mirrors `Renderer_Support::truncate_words()` so the editor preview and the
 * compiled email agree on the same cap. Tags are stripped before entities are
 * decoded, so an escaped `&lt;script&gt;` can never become real markup.
 */
export function truncateWords(raw: string, maxWords: number): string {
  const text = decodeEntitiesOnce(raw.replace(/<[^>]*>/g, ' '))
    .trim()
    .replace(/\u2026+$/, '');
  const words = text.split(/\s+/).filter(Boolean);

  return words.length <= maxWords
    ? words.join(' ')
    : `${words.slice(0, maxWords).join(' ')}\u2026`;
}
