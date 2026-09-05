export type BrandKitSource = 'defaults' | 'custom' | 'theme';

export interface BrandSlot {
  id: string;
  name: string;
  description: string;
  color: string;
}

export interface BrandKitPayload {
  source: BrandKitSource;
  slots: BrandSlot[];
}

export interface BrandKitI18n {
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
}

export interface BrandKitConfig {
  restUrl: string;
  nonce: string;
  kit: BrandKitPayload;
  i18n: BrandKitI18n;
}

declare global {
  interface Window {
    campaignbridgeBrandKit?: BrandKitConfig;
  }
}
