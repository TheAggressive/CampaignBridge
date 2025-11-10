/**
 * TypeScript types and interfaces for encrypted fields functionality
 *
 * @package CampaignBridge
 */

// WordPress API response types
export interface WordPressApiResponse {
  success: boolean;
  data?: any;
  [key: string]: any;
}

export interface ApiDecryptResponse extends WordPressApiResponse {
  data?: {
    decrypted: string;
  };
}

export interface ApiEncryptResponse extends WordPressApiResponse {
  data?: {
    encrypted: string;
    masked: string;
  };
}

// CampaignBridge admin configuration
export interface CampaignBridgeAdmin {
  security?: {
    revealTimeout?: number;
    maxRetries?: number;
    requestTimeout?: number;
  };
  nonce?: string;
  i18n?: {
    loading?: string;
    saving?: string;
    [key: string]: string | undefined;
  };
}

// DOM element interfaces
export interface FieldElements {
  field: HTMLElement;
  displayInput: HTMLInputElement | null;
  editInput: HTMLInputElement | null;
  hiddenInput: HTMLInputElement | null;
  controls: HTMLElement | null;
  editControls: HTMLElement | null;
  revealBtn: HTMLButtonElement | null;
  hideBtn: HTMLButtonElement | null;
  editBtn: HTMLButtonElement | null;
  saveBtn: HTMLButtonElement | null;
  cancelBtn: HTMLButtonElement | null;
}

// Validation result types
export type ValidationResult = {
  isValid: boolean;
  error?: string;
  field?: string;
};

// Event handler types
export type EventHandlerMap = Record<
  string,
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  (element: HTMLElement) => void | Promise<void>
>;

// State management types
export interface HandlerState {
  revealTimeouts: Map<string, number>;
  requestQueue: Map<string, string>;
  originalValues: Map<string, string>;
  revealedValues: Map<string, string>;
}

// Configuration constants
export interface HandlerConfig {
  REVEAL_TIMEOUT_MS: number;
  MAX_VALUE_LENGTH: number;
  MAX_RETRIES: number;
  REQUEST_TIMEOUT_MS: number;
}

// CSS class constants
export const CLASSES = {
  FIELD: 'campaignbridge-encrypted-field',
  DISPLAY: 'campaignbridge-encrypted-field__display',
  EDIT: 'campaignbridge-encrypted-field__edit',
  CONTROLS: 'campaignbridge-encrypted-field__controls',
  EDIT_CONTROLS: 'campaignbridge-encrypted-field__edit-controls',
  REVEAL_BTN: 'campaignbridge-encrypted-field__reveal-btn',
  HIDE_BTN: 'campaignbridge-encrypted-field__hide-btn',
  EDIT_BTN: 'campaignbridge-encrypted-field__edit-btn',
  SAVE_BTN: 'campaignbridge-encrypted-field__save-btn',
  CANCEL_BTN: 'campaignbridge-encrypted-field__cancel-btn',
  PERMISSION_DENIED: 'campaignbridge-encrypted-field--permission-denied',
  // Visibility state classes
  CONTROLS_HIDDEN: 'campaignbridge-encrypted-field__controls--hidden',
  DISPLAY_HIDDEN: 'campaignbridge-encrypted-field__display--hidden',
  REVEAL_BTN_HIDDEN: 'campaignbridge-encrypted-field__reveal-btn--hidden',
  HIDE_BTN_VISIBLE: 'campaignbridge-encrypted-field__hide-btn--visible',
  EDIT_VISIBLE: 'campaignbridge-encrypted-field__edit--visible',
  EDIT_CONTROLS_VISIBLE:
    'campaignbridge-encrypted-field__edit-controls--visible',
} as const;

// Global type declarations
declare global {
  // eslint-disable-next-line no-unused-vars -- Global type declaration for TypeScript, used at runtime.
  const campaignbridgeAdmin: CampaignBridgeAdmin;
}
