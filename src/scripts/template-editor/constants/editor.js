/**
 * Editor Chrome Constants
 *
 * Centralized constants for the EditorChrome component to improve maintainability
 * and reduce magic strings throughout the codebase.
 */

export const EDITOR_CONSTANTS = {
  // Save status states
  SAVE_STATUS: {
    SAVED: "saved",
    SAVING: "saving",
    ERROR: "error",
  },

  // Sidebar scopes
  SIDEBAR_SCOPES: {
    PRIMARY: "campaignbridge/template-editor/primary",
    SECONDARY: "campaignbridge/template-editor/secondary",
  },

  // Sidebar tab states
  SIDEBAR_TABS: {
    TEMPLATE: "template-settings",
    INSPECTOR: "block-inspector",
  },

  // Preference keys
  PREFERENCES: {
    PRIMARY_SIDEBAR_OPEN: "campaignbridge/template-editor/primarySidebarOpen",
    SECONDARY_SIDEBAR_OPEN:
      "campaignbridge/template-editor/secondarySidebarOpen",
    FULLSCREEN_MODE: "core/edit-post/fullscreenMode",
  },

  // CSS classes
  CSS_CLASSES: {
    EDITOR: "cb-editor",
    EDITOR_LOADING: "cb-editor-loading",
    EDITOR_ERROR: "cb-editor-error",
    EDITOR_SNACKBAR: "cb-editor__snackbar",
    SIDEBAR_PRIMARY: "cb-editor__sidebar cb-editor__sidebar--primary",
    SIDEBAR_SECONDARY: "cb-editor__sidebar cb-editor__sidebar--secondary",
    SIDEBAR_CONTENT: "cb-editor__sidebar-content",
  },

  // Layout modifiers
  LAYOUT_MODIFIERS: {
    HAS_PRIMARY: "cb-editor--has-primary",
    NO_PRIMARY: "cb-editor--no-primary",
    HAS_SECONDARY: "cb-editor--has-secondary",
    NO_SECONDARY: "cb-editor--no-secondary",
  },

  // Notification throttle (ms)
  NOTIFICATION_THROTTLE: 8000,

  // AutoSave configuration
  AUTOSAVE: {
    DEFAULT_DEBOUNCE_MS: 2500,
    MIN_DEBOUNCE_MS: 100,
    MAX_DEBOUNCE_MS: 10000,
  },

  // API endpoints
  API_PATHS: {
    EDITOR_SETTINGS: "/campaignbridge/v1/editor-settings",
  },

  // Sidebar configuration
  SIDEBAR: {
    IDENTIFIERS: {
      PRIMARY: "primary",
      SECONDARY: "secondary",
    },
    PREFERENCE_KEYS: {
      PRIMARY_OPEN: "primarySidebarOpen",
      SECONDARY_OPEN: "secondarySidebarOpen",
    },
  },

  // URL parameters
  URL_PARAMS: {
    TEMPLATE_ID: "post_id",
  },

  // Template configuration
  TEMPLATES: {
    CACHE_DURATION_MS: 5 * 60 * 1000, // 5 minutes
    RETRY: {
      MAX_RETRIES: 3,
      DELAY_MS: 1000,
    },
    ERROR_MESSAGES: {
      LOAD_FAILED: "Failed to load templates.",
      INVALID_RESPONSE: "Invalid response format: expected array",
      API_NOT_AVAILABLE: "Templates API not available.",
    },
  },
};
