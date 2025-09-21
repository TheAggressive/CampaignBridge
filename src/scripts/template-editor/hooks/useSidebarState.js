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
 * @property {string|null} activePrimary - Currently active primary sidebar identifier
 * @property {string|null} activeSecondary - Currently active secondary sidebar identifier
 * @property {boolean} isPrimaryOpen - Whether primary sidebar is currently open
 * @property {boolean} isSecondaryOpen - Whether secondary sidebar is currently open
 * @property {Function} togglePrimary - Function to toggle primary sidebar open/closed state
 * @property {Function} toggleSecondary - Function to toggle secondary sidebar open/closed state
 */

/**
 * Enhanced custom hook for managing sidebar states with proper subscription, error handling, and preference persistence.
 *
 * Features:
 * - **Consistent Package Imports**: Uses @wordpress/data package imports (useSelect, useDispatch, subscribe) instead of global wp.data
 * - **Constants Integration**: Uses centralized constants for preference keys and identifiers
 * - **Dual State Synchronization**: Combines useSelect with subscribe() for robust state tracking
 * - **Enhanced Type Safety**: Comprehensive JSDoc with TypeScript-like typedefs
 * - **Memory Management**: Proper cleanup to prevent memory leaks
 * - **Error Recovery**: Graceful error handling with development-only logging
 * - **State Persistence**: Automatically saves sidebar states to WordPress preferences
 *
 * @param {string} primaryScope - The scope identifier for the primary sidebar
 * @param {string} secondaryScope - The scope identifier for the secondary sidebar
 * @return {SidebarState}
 *
 * @example
 * ```jsx
 * const {
 *   activePrimary,
 *   activeSecondary,
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
  const [activePrimary, setActivePrimary] = useState(null);
  const [activeSecondary, setActiveSecondary] = useState(null);

  // Get sidebar state from WordPress data using package imports
  const primaryState = useSelect(
    (select) =>
      select("core/interface").getActiveComplementaryArea(primaryScope),
    [primaryScope],
  );
  const secondaryState = useSelect(
    (select) =>
      select("core/interface").getActiveComplementaryArea(secondaryScope),
    [secondaryScope],
  );

  // Get required functions from WordPress data package for state management
  const { set: setPreference } = useDispatch("core/preferences");
  const { enableComplementaryArea, disableComplementaryArea } =
    useDispatch("core/interface");

  // Subscribe to interface store changes to ensure state updates
  useEffect(() => {
    const unsubscribe = subscribe(() => {
      const newPrimaryState =
        select("core/interface").getActiveComplementaryArea(primaryScope);
      const newSecondaryState =
        select("core/interface").getActiveComplementaryArea(secondaryScope);

      setActivePrimary(newPrimaryState);
      setActiveSecondary(newSecondaryState);
    });

    return unsubscribe;
  }, [primaryScope, secondaryScope]);

  // Also update state when useSelect results change
  useEffect(() => {
    setActivePrimary(primaryState);
  }, [primaryState]);

  useEffect(() => {
    setActiveSecondary(secondaryState);
  }, [secondaryState]);

  /**
   * Toggle the primary sidebar with proper state management and preference persistence.
   */
  const togglePrimary = useCallback(() => {
    try {
      if (activePrimary === SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY) {
        disableComplementaryArea(primaryScope);
        setPreference(
          SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
          SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
          false,
        );
      } else {
        enableComplementaryArea(
          primaryScope,
          SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
        );
        setPreference(
          SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
          SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
          true,
        );
      }
    } catch (error) {
      if (process.env.NODE_ENV === "development") {
        console.warn("useSidebarState: Error toggling primary sidebar:", error);
      }
    }
  }, [
    activePrimary,
    primaryScope,
    disableComplementaryArea,
    enableComplementaryArea,
    setPreference,
  ]);

  /**
   * Toggle the secondary sidebar with proper state management and preference persistence.
   */
  const toggleSecondary = useCallback(() => {
    try {
      if (activeSecondary === SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY) {
        disableComplementaryArea(secondaryScope);
        setPreference(
          SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
          SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
          false,
        );
      } else {
        enableComplementaryArea(
          secondaryScope,
          SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
        );
        setPreference(
          SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
          SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
          true,
        );
      }
    } catch (error) {
      if (process.env.NODE_ENV === "development") {
        console.warn(
          "useSidebarState: Error toggling secondary sidebar:",
          error,
        );
      }
    }
  }, [
    activeSecondary,
    secondaryScope,
    disableComplementaryArea,
    enableComplementaryArea,
    setPreference,
  ]);

  return {
    // State - directly from WordPress
    activePrimary,
    activeSecondary,
    isPrimaryOpen: activePrimary === SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
    isSecondaryOpen:
      activeSecondary === SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,

    // Actions
    togglePrimary,
    toggleSecondary,
  };
}
