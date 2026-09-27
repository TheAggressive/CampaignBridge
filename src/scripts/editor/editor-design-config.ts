export interface EditorDesignConfig {
  features: Record<string, unknown>;
  fontAssets: Record<string, string>;
  defaultFonts: string[];
  designFontSlugs: string[];
  baseFontSlots: Record<string, string>;
}

declare global {
  // Server-validated email design, emitted only on the template editor.
  var campaignbridgeEditorDesign: EditorDesignConfig | undefined;
}

const emptyConfig: EditorDesignConfig = {
  features: {},
  fontAssets: {},
  defaultFonts: [],
  designFontSlugs: [],
  baseFontSlots: {},
};

export const editorDesignConfig =
  globalThis.campaignbridgeEditorDesign ?? emptyConfig;
