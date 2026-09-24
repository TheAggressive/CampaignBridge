export interface EditorDesignConfig {
  features: Record<string, unknown>;
  fontAssets: Record<string, string>;
  defaultFonts: string[];
}

declare global {
  // Server-validated email design, emitted only on the template editor.
  var campaignbridgeEditorDesign: EditorDesignConfig | undefined;
}

const emptyConfig: EditorDesignConfig = {
  features: {},
  fontAssets: {},
  defaultFonts: [],
};

export const editorDesignConfig =
  globalThis.campaignbridgeEditorDesign ?? emptyConfig;
