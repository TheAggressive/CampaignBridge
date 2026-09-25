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
