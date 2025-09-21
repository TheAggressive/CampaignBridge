import { select, subscribe, useDispatch, useSelect } from "@wordpress/data";
import { useCallback, useEffect, useState } from "@wordpress/element";

// Sidebar-specific constants (exported for reuse if needed)
export const SIDEBAR_CONSTANTS = {
  IDENTIFIERS: {
    PRIMARY: "primary",
    SECONDARY: "secondary",
  },
  PREFERENCE_KEYS: {
    PRIMARY_OPEN: "primarySidebarOpen",
    SECONDARY_OPEN: "secondarySidebarOpen",
  },
  PREFERENCES: {
    PRIMARY_SIDEBAR_OPEN: "campaignbridge/template-editor/primarySidebarOpen",
    SECONDARY_SIDEBAR_OPEN:
      "campaignbridge/template-editor/secondarySidebarOpen",
    FULLSCREEN_MODE: "core/edit-post/fullscreenMode",
  },
  SCOPES: {
    PRIMARY: "campaignbridge/template-editor/primary",
    SECONDARY: "campaignbridge/template-editor/secondary",
  },
  TABS: {
    TEMPLATE: "template-settings",
    INSPECTOR: "block-inspector",
  },
};

/**
 * @typedef {Object} SidebarState
 * @property {boolean} isPrimaryOpen - Whether primary sidebar is currently open
 * @property {boolean} isSecondaryOpen - Whether secondary sidebar is currently open
 * @property {Function} togglePrimary - Function to toggle primary sidebar open/closed state
 * @property {Function} toggleSecondary - Function to toggle secondary sidebar open/closed state
 */

/**
 * Custom hook for managing sidebar states with preference persistence.
 * Uses local state management with WordPress interface store synchronization
 * for reliable state tracking and instant UI feedback.
 *
 * Features:
 * - **Preference Persistence**: Saves sidebar states to WordPress preferences
 * - **State Restoration**: Restores saved states on component mount
 * - **Close Button Support**: Captures ComplementaryArea close button interactions
 * - **Local State Management**: Uses React state for reliable state tracking
 * - **Real-time Sync**: Synchronizes with WordPress interface store changes
 * - **Instant UI Feedback**: Immediate visual feedback on user interactions
 * - **Error Handling**: Graceful error handling with development logging
 *
 * @param {string} primaryScope - The scope identifier for the primary sidebar
 * @param {string} secondaryScope - The scope identifier for the secondary sidebar
 * @return {SidebarState}
 *
 * @example
 * ```jsx
 * const {
 *   isPrimaryOpen,
 *   isSecondaryOpen,
 *   togglePrimary,
 *   toggleSecondary
 * } = useSidebarState(
 *   SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
 *   SIDEBAR_CONSTANTS.SCOPES.SECONDARY
 * );
 *
 * // Toggle primary sidebar
 * togglePrimary();
 *
 * // Check if secondary sidebar is open
 * if (isSecondaryOpen) {
 *   // Do something with secondary sidebar
 * }
 * ```
 */
export function useSidebarState(primaryScope, secondaryScope) {
  // Use local state for reliable tracking of sidebar states
  const [primaryOpen, setPrimaryOpen] = useState(false);
  const [secondaryOpen, setSecondaryOpen] = useState(false);

  /**
   * Helper function to get current sidebar state from WordPress interface store.
   *
   * @private
   * @param {string} scope - The sidebar scope identifier (e.g., "campaignbridge/template-editor/primary")
   * @param {string} identifier - The sidebar identifier (e.g., "primary")
   * @return {boolean} Whether the sidebar is currently open
   * @throws {Error} If scope or identifier parameters are invalid
   */
  const getIsOpen = (scope, identifier) => {
    if (!scope || !identifier) {
      throw new Error(
        "getIsOpen: scope and identifier parameters are required",
      );
    }

    const state = select("core/interface").getActiveComplementaryArea(scope);
    return state === identifier;
  };

  /**
   * Helper function to save sidebar preference when state changes.
   * Only saves if the preference would actually change to avoid unnecessary updates.
   *
   * @private
   * @param {string} scope - The sidebar scope identifier (e.g., "campaignbridge/template-editor/primary")
   * @param {string} identifier - The sidebar identifier (e.g., "primary")
   * @param {string} preferenceKey - The preference key for saving state (e.g., "primarySidebarOpen")
   * @param {string} preferencePath - The preference path for saving state (e.g., "campaignbridge/template-editor/primarySidebarOpen")
   */
  const saveSidebarPreference = (
    scope,
    identifier,
    preferenceKey,
    preferencePath,
  ) => {
    const isOpen = getIsOpen(scope, identifier);
    const currentPref = select("core/preferences").get(
      preferencePath,
      preferenceKey,
    );

    // Only save if the preference would actually change
    if (isOpen !== currentPref) {
      setPreference(preferencePath, preferenceKey, isOpen);
    }
  };

  /**
   * Generic helper function to handle sidebar restoration from preferences.
   * Only acts if we have a definitive saved preference (true/false).
   * If savedState is undefined, we don't change anything to let default state prevail.
   *
   * @private
   * @param {string} scope - The sidebar scope identifier (e.g., "campaignbridge/template-editor/primary")
   * @param {string} identifier - The sidebar identifier (e.g., "primary")
   * @param {boolean|undefined} savedState - The saved preference state (true = open, false = closed, undefined = use default)
   */
  const restoreSidebarState = (scope, identifier, savedState) => {
    if (savedState === true) {
      enableComplementaryArea(scope, identifier);
    } else if (savedState === false) {
      disableComplementaryArea(scope);
    }
    // If savedState is undefined, we don't change anything - let the default state prevail
  };

  /**
   * Generic helper function to create a toggle handler for a sidebar.
   * Creates a memoized function that toggles sidebar state and saves preferences.
   * Provides immediate UI feedback by updating local state first.
   *
   * @private
   * @param {string} scope - The sidebar scope identifier (e.g., "campaignbridge/template-editor/primary")
   * @param {string} identifier - The sidebar identifier (e.g., "primary")
   * @param {string} preferenceKey - The preference key for saving state (e.g., "primarySidebarOpen")
   * @param {string} preferencePath - The preference path for saving state (e.g., "campaignbridge/template-editor/primarySidebarOpen")
   * @param {Function} setState - The React state setter function for updating local state
   * @return {Function} The memoized toggle handler function
   */
  const createToggleHandler = (
    scope,
    identifier,
    preferenceKey,
    preferencePath,
    setState,
  ) => {
    return useCallback(() => {
      try {
        // Get current state from interface store
        const currentState = getIsOpen(scope, identifier);
        const newState = !currentState;

        // Update interface store
        if (newState) {
          enableComplementaryArea(scope, identifier);
        } else {
          disableComplementaryArea(scope);
        }

        // Update local state immediately for instant UI feedback
        setState(newState);

        // Save preference for persistence
        setPreference(preferencePath, preferenceKey, newState);
      } catch (error) {
        if (process.env.NODE_ENV === "development") {
          console.warn("useSidebarState: Error toggling sidebar:", error);
        }
      }
    }, [scope, identifier, preferenceKey, preferencePath, setState]);
  };

  /**
   * Helper function to toggle a sidebar and save its preference.
   * @private
   *
   * @param {string} scope - The sidebar scope identifier
   * @param {string} identifier - The sidebar identifier
   * @param {string} preferenceKey - The preference key for saving state
   * @param {string} preferencePath - The preference path for saving state
   */
  const toggleSidebar = (scope, identifier, preferenceKey, preferencePath) => {
    try {
      const isOpen = getIsOpen(scope, identifier);

      if (isOpen) {
        disableComplementaryArea(scope);
        setPreference(preferencePath, preferenceKey, false);
      } else {
        enableComplementaryArea(scope, identifier);
        setPreference(preferencePath, preferenceKey, true);
      }
    } catch (error) {
      if (process.env.NODE_ENV === "development") {
        console.warn("useSidebarState: Error toggling sidebar:", error);
      }
    }
  };
  // Get saved preferences for sidebar states
  const savedPrimaryOpen = useSelect(
    (select) =>
      select("core/preferences").get(
        SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
        SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
      ),
    [],
  );
  const savedSecondaryOpen = useSelect(
    (select) =>
      select("core/preferences").get(
        SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
        SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
      ),
    [],
  );

  // Sync local state with saved preferences on mount
  useEffect(() => {
    if (savedPrimaryOpen !== undefined) {
      setPrimaryOpen(savedPrimaryOpen);
    }
  }, [savedPrimaryOpen]);

  useEffect(() => {
    if (savedSecondaryOpen !== undefined) {
      setSecondaryOpen(savedSecondaryOpen);
    }
  }, [savedSecondaryOpen]);

  // Get required functions from WordPress data package for state management
  const { set: setPreference } = useDispatch("core/preferences");
  const { enableComplementaryArea, disableComplementaryArea } =
    useDispatch("core/interface");

  // Restore sidebar states from saved preferences on mount
  useEffect(() => {
    // Only act if we have a definitive saved preference (not undefined)
    restoreSidebarState(
      primaryScope,
      SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
      savedPrimaryOpen,
    );
    // If savedPrimaryOpen is undefined, we don't change anything - let the default state prevail
  }, [
    savedPrimaryOpen,
    primaryScope,
    enableComplementaryArea,
    disableComplementaryArea,
  ]);

  useEffect(() => {
    // Only act if we have a definitive saved preference (not undefined)
    restoreSidebarState(
      secondaryScope,
      SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
      savedSecondaryOpen,
    );
    // If savedSecondaryOpen is undefined, we don't change anything - let the default state prevail
  }, [
    savedSecondaryOpen,
    secondaryScope,
    enableComplementaryArea,
    disableComplementaryArea,
  ]);

  // Subscribe to interface store changes to keep local state in sync
  useEffect(() => {
    const unsubscribe = subscribe(() => {
      // Save preferences when state changes (including from close buttons)
      saveSidebarPreference(
        primaryScope,
        SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
        SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
        SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
      );

      saveSidebarPreference(
        secondaryScope,
        SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
        SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
        SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
      );

      // Update local state when interface store changes
      const isPrimaryOpen = getIsOpen(
        primaryScope,
        SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
      );
      const isSecondaryOpen = getIsOpen(
        secondaryScope,
        SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
      );

      setPrimaryOpen(isPrimaryOpen);
      setSecondaryOpen(isSecondaryOpen);
    });

    return unsubscribe;
  }, [primaryScope, secondaryScope, setPreference]);

  /**
   * Toggle the primary sidebar with proper state management and preference persistence.
   */
  const togglePrimary = createToggleHandler(
    primaryScope,
    SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
    SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
    SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
    setPrimaryOpen,
  );

  /**
   * Toggle the secondary sidebar with proper state management and preference persistence.
   */
  const toggleSecondary = createToggleHandler(
    secondaryScope,
    SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
    SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
    SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
    setSecondaryOpen,
  );

  return {
    // State - from local state (more reliable)
    isPrimaryOpen: primaryOpen,
    isSecondaryOpen: secondaryOpen,

    // Actions
    togglePrimary,
    toggleSecondary,
  };
}
