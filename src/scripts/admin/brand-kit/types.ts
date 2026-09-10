export type BrandKitSource = 'defaults' | 'custom' | 'theme';

export interface BrandSlot {
  id: string;
  name: string;
  description: string;
  color: string;
}

export interface FontOption {
  slug: string;
  name: string;
  type: 'system' | 'web';
  family: string;
  url: string | null;
}

export interface BrandKitPayload {
  source: BrandKitSource;
  slots: BrandSlot[];
  fonts: Record<string, string>;
  fontOptions: FontOption[];
  fontSlots: Record<string, string>;
}

export interface GoogleFontResult {
  family: string;
  category: string;
  variants: string[];
}

export interface BrandKitI18n {
  coloursTitle: string;
  coloursHelp: string;
  edit: string;
  save: string;
  cancel: string;
  saved: string;
  saveFailed: string;
  colour: string;
  slot: string;
  use: string;
  empty: string;
  sourceTheme: string;
  sourceCustom: string;
  sourceDefaults: string;
  typography: string;
  fontSave: string;
  fontSaveFailed: string;
  fontFamily: string;
  preview: string;
  primaryButton: string;
  typographyHelp: string;
  headingUse: string;
  bodyUse: string;
  buttonUse: string;
  fontChange: string;
  fontLookup: string;
  fontLookupHelp: string;
  fontSearch: string;
  fontSearchButton: string;
  fontSearchEmpty: string;
  fontAdd: string;
  fontAdded: string;
  saving: string;
  savedStatus: string;
  contrastPass: string;
  contrastFail: string;
}

export interface BrandKitConfig {
  restUrl: string;
  nonce: string;
  kit: BrandKitPayload;
  externalFontsEnabled: boolean;
  i18n: BrandKitI18n;
}

declare global {
  interface Window {
    campaignbridgeBrandKit?: BrandKitConfig;
  }
}
