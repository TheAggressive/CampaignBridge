export interface EditorFontAssetSettings {
  campaignbridgeFontAssets?: Record<string, string>;
  campaignbridgeDefaultFonts?: string[];
}

export interface EditorFontBlock {
  name: string;
  attributes?: Record<string, unknown>;
  innerBlocks?: EditorFontBlock[];
}

const FONT_BLOCKS = new Set(['core/heading', 'core/paragraph', 'core/button']);

function presetSlug(value: unknown): string | null {
  if (typeof value !== 'string' || value.length === 0) return null;

  const prefix = 'var:preset|font-family|';
  return value.startsWith(prefix) ? value.slice(prefix.length) : value;
}

function blockFontSlug(block: EditorFontBlock): string | null {
  if (!FONT_BLOCKS.has(block.name)) return null;

  const attributes = block.attributes ?? {};
  const explicit = presetSlug(attributes.fontFamily);
  if (explicit) return explicit;

  const style = attributes.style;
  if (!style || typeof style !== 'object') return null;
  const typography = (style as Record<string, unknown>).typography;
  if (!typography || typeof typography !== 'object') return null;

  return presetSlug((typography as Record<string, unknown>).fontFamily);
}

/** URLs needed by resolved defaults and explicit native block overrides. */
export function requiredEditorFontUrls(
  blocks: EditorFontBlock[],
  settings: EditorFontAssetSettings
): string[] {
  const assets = settings.campaignbridgeFontAssets ?? {};
  const slugs = new Set(settings.campaignbridgeDefaultFonts ?? []);

  const visit = (items: EditorFontBlock[]) => {
    for (const block of items) {
      const slug = blockFontSlug(block);
      if (slug) slugs.add(slug);
      if (block.innerBlocks?.length) visit(block.innerBlocks);
    }
  };
  visit(blocks);

  return Array.from(
    new Set(
      Array.from(slugs)
        .map(slug => assets[slug])
        .filter((url): url is string => typeof url === 'string' && url !== '')
    )
  );
}

/** Match Core's kebab-case output for our constrained lowercase preset slugs. */
function fontPresetClassSlug(slug: string): string {
  return slug
    .replace(/([0-9])([a-z])/g, '$1-$2')
    .replace(/([a-z])([0-9])/g, '$1-$2');
}

/**
 * Apply effective semantic type slots inside the editor canvas.
 *
 * Assigning textContent keeps validated family stacks as CSS values without
 * constructing HTML. Omitted or unknown slots simply inherit server defaults.
 */
export function syncEditorTypeFontStyles(
  canvasDocument: Document,
  families: Record<string, string>,
  slots: Record<string, string>
): void {
  const selectorBySlot: Record<string, string> = {
    body: ':where(.editor-styles-wrapper),:where([data-type="core/paragraph"])',
    heading: ':where([data-type="core/heading"])',
    button: ':where([data-type="core/button"] .wp-block-button__link)',
  };
  // Mirror Core's preset utility classes (and the server's saved-preset
  // rules): an explicit block font must beat semantic slots and Core's
  // `:root :where(.wp-element-button, .wp-block-button__link)` inherit rule,
  // which has equal specificity to a single class and is emitted later.
  const presetRules = Object.entries(families)
    .filter(
      ([slug, family]) =>
        /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) && family.length > 0
    )
    .map(
      ([slug, family]) =>
        `.editor-styles-wrapper .has-${fontPresetClassSlug(slug)}-font-family{font-family:${family}!important}`
    )
    .join('');
  const slotRules = Object.entries(selectorBySlot)
    .map(([slot, selector]) => {
      const family = families[slots[slot]];
      return family ? `${selector}{font-family:${family}}` : '';
    })
    .join('');
  const rules = `${presetRules}${slotRules}`;

  const selector = 'style[data-campaignbridge-editor-type-fonts]';
  const existing =
    canvasDocument.head.querySelector<HTMLStyleElement>(selector);
  if (!rules) {
    existing?.remove();
    return;
  }

  const style = existing ?? canvasDocument.createElement('style');
  style.dataset.campaignbridgeEditorTypeFonts = '1';
  style.textContent = rules;
  if (!existing) canvasDocument.head.appendChild(style);
}

/** Synchronize CampaignBridge-owned stylesheet links inside the editor canvas. */
export function syncEditorFontStylesheets(
  canvasDocument: Document,
  urls: string[]
): void {
  const required = new Set(urls);
  const existing = Array.from(
    canvasDocument.head.querySelectorAll<HTMLLinkElement>(
      'link[data-campaignbridge-editor-font]'
    )
  );

  for (const link of existing) {
    const source = link.dataset.campaignbridgeEditorFont ?? link.href;
    if (!required.delete(source)) link.remove();
  }

  for (const url of required) {
    const link = canvasDocument.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.dataset.campaignbridgeEditorFont = url;
    canvasDocument.head.appendChild(link);
  }
}
