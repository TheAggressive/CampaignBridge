import { editorDesignConfig } from './editor-design-config';

export const DESIGN_FONT_META_KEY = 'campaignbridge_template_design_fonts';
export const MAX_DESIGN_FONTS = 24;

export interface DesignFont {
  slug: string;
  name: string;
  family: string;
  weights: number[];
  url: string;
}

export interface DesignFontRegistry {
  version: 1;
  fonts: DesignFont[];
  slots: Partial<Record<'heading' | 'body' | 'button', string>>;
}

export interface EditorFontOption {
  slug: string;
  name: string;
  fontFamily: string;
}

export const EMPTY_DESIGN_FONT_REGISTRY: DesignFontRegistry = {
  version: 1,
  fonts: [],
  slots: {},
};

function validFont(value: unknown): value is DesignFont {
  if (!value || typeof value !== 'object') return false;
  const font = value as Record<string, unknown>;

  return (
    typeof font.slug === 'string' &&
    /^custom-[a-f0-9]{12}$/.test(font.slug) &&
    typeof font.name === 'string' &&
    font.name.length > 0 &&
    font.name.length <= 80 &&
    !/[<>]/.test(font.name) &&
    Array.from(font.name).every(character => {
      const code = character.charCodeAt(0);
      return code >= 32 && code !== 127;
    }) &&
    typeof font.family === 'string' &&
    font.family.length > 0 &&
    font.family.length <= 160 &&
    /^[a-zA-Z][a-zA-Z0-9 _,'"-]*$/.test(font.family) &&
    Array.isArray(font.weights) &&
    font.weights.every(
      weight =>
        Number.isInteger(weight) &&
        Number(weight) >= 100 &&
        Number(weight) <= 900
    ) &&
    typeof font.url === 'string' &&
    font.url.startsWith('https://fonts.googleapis.com/css2?')
  );
}

export function parseDesignFontRegistry(value: unknown): DesignFontRegistry {
  if (typeof value !== 'string' || value.length > 32768) {
    return EMPTY_DESIGN_FONT_REGISTRY;
  }

  try {
    const parsed = JSON.parse(value) as Record<string, unknown>;
    if (parsed.version !== 1 || !Array.isArray(parsed.fonts)) {
      return EMPTY_DESIGN_FONT_REGISTRY;
    }

    const fonts = parsed.fonts.filter(validFont).slice(0, MAX_DESIGN_FONTS);
    const known = new Set(fonts.map(font => font.slug));
    const rawSlots =
      parsed.slots && typeof parsed.slots === 'object'
        ? (parsed.slots as Record<string, unknown>)
        : {};
    const slots: DesignFontRegistry['slots'] = {};

    for (const slot of ['heading', 'body', 'button'] as const) {
      const slug = rawSlots[slot];
      if (
        typeof slug === 'string' &&
        (/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) || known.has(slug))
      ) {
        slots[slot] = slug;
      }
    }

    return { version: 1, fonts, slots };
  } catch {
    return EMPTY_DESIGN_FONT_REGISTRY;
  }
}

export function serializeDesignFontRegistry(
  registry: DesignFontRegistry
): string {
  return JSON.stringify({
    version: 1,
    fonts: registry.fonts.slice(0, MAX_DESIGN_FONTS),
    slots: registry.slots,
  });
}

function typographyFeatures(): Record<string, unknown> {
  const typography = editorDesignConfig.features.typography;
  return typography && typeof typography === 'object'
    ? (typography as Record<string, unknown>)
    : {};
}

function themeFontOptions(): EditorFontOption[] {
  const families = typographyFeatures().fontFamilies;
  if (!families || typeof families !== 'object') return [];

  const theme = (families as Record<string, unknown>).theme;
  return Array.isArray(theme)
    ? theme.filter(
        (font): font is EditorFontOption =>
          Boolean(font) &&
          typeof font === 'object' &&
          typeof (font as Record<string, unknown>).slug === 'string' &&
          typeof (font as Record<string, unknown>).name === 'string' &&
          typeof (font as Record<string, unknown>).fontFamily === 'string'
      )
    : [];
}

export function editorFontOptions(): EditorFontOption[] {
  return themeFontOptions();
}

/**
 * Merge unsaved template fonts into the immutable server bootstrap.
 *
 * Core's useSetting filter reads this shared object. The caller dispatches one
 * settings update after this mutation so native typography controls re-render.
 */
export function synchronizeEditorDesignFonts(
  registry: DesignFontRegistry
): void {
  const previous = new Set(editorDesignConfig.designFontSlugs ?? []);
  const typography = typographyFeatures();
  const families =
    typography.fontFamilies && typeof typography.fontFamilies === 'object'
      ? (typography.fontFamilies as Record<string, unknown>)
      : {};
  const retained = themeFontOptions().filter(font => !previous.has(font.slug));
  const additions = registry.fonts.map(font => ({
    slug: font.slug,
    name: font.name,
    fontFamily: font.family,
  }));

  families.theme = [...retained, ...additions];
  typography.fontFamilies = families;
  editorDesignConfig.features.typography = typography;

  const nextAssets = { ...editorDesignConfig.fontAssets };
  for (const slug of previous) delete nextAssets[slug];
  for (const font of registry.fonts) nextAssets[font.slug] = font.url;
  editorDesignConfig.fontAssets = nextAssets;
  editorDesignConfig.designFontSlugs = registry.fonts.map(font => font.slug);

  const known = new Set([...retained, ...additions].map(font => font.slug));
  const effectiveSlots = {
    ...(editorDesignConfig.baseFontSlots ?? {}),
    ...registry.slots,
  };
  const defaults = Object.values(effectiveSlots).filter(slug =>
    known.has(slug)
  );
  if (defaults.length > 0) {
    editorDesignConfig.defaultFonts = Array.from(new Set(defaults));
  }
}
