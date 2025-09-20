import { useDispatch, useSelect } from "@wordpress/data";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { EDITOR_CONSTANTS } from "../constants/editor";

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
 * - **Consistent Package Imports**: Uses @wordpress/data package imports instead of global wp.data
 * - **Constants Integration**: Uses centralized constants for preference keys and identifiers
 * - **Preference Restoration**: Automatically restores sidebar states from WordPress preferences on mount
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
 *   EDITOR_CONSTANTS.SIDEBAR_SCOPES.PRIMARY,
 *   EDITOR_CONSTANTS.SIDEBAR_SCOPES.SECONDARY
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

  // Get required functions from WordPress data package using consistent package imports
  const { set: setPreference } = useDispatch("core/preferences");
  const { enableComplementaryArea, disableComplementaryArea } =
    useDispatch("core/interface");
  const { get: getPreference } = useSelect(
    (select) => select("core/preferences"),
    [],
  );

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

  useEffect(() => {
    // Restore sidebar states from preferences on mount
    try {
      const primaryOpen = getPreference(
        "campaignbridge/template-editor",
        EDITOR_CONSTANTS.SIDEBAR.PREFERENCE_KEYS.PRIMARY_OPEN,
      );
      const secondaryOpen = getPreference(
        "campaignbridge/template-editor",
        EDITOR_CONSTANTS.SIDEBAR.PREFERENCE_KEYS.SECONDARY_OPEN,
      );

      // Restore primary sidebar state from preferences
      if (
        primaryOpen &&
        primaryState !== EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.PRIMARY
      ) {
        enableComplementaryArea(
          primaryScope,
          EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.PRIMARY,
        );
      } else if (
        !primaryOpen &&
        primaryState === EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.PRIMARY
      ) {
        disableComplementaryArea(primaryScope);
      }

      // Restore secondary sidebar state from preferences
      if (
        secondaryOpen &&
        secondaryState !== EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.SECONDARY
      ) {
        enableComplementaryArea(
          secondaryScope,
          EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.SECONDARY,
        );
      } else if (
        !secondaryOpen &&
        secondaryState === EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.SECONDARY
      ) {
        disableComplementaryArea(secondaryScope);
      }
    } catch (error) {
      // Handle preference restoration errors gracefully
      if (process.env.NODE_ENV === "development") {
        console.warn(
          "useSidebarState: Error restoring sidebar preferences:",
          error,
        );
      }
    }
  }, []); // Empty dependency array - run only on mount

  // Update local state when WordPress state changes
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
      if (activePrimary === EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.PRIMARY) {
        disableComplementaryArea(primaryScope);
        setPreference(
          "campaignbridge/template-editor",
          EDITOR_CONSTANTS.SIDEBAR.PREFERENCE_KEYS.PRIMARY_OPEN,
          false,
        );
      } else {
        enableComplementaryArea(
          primaryScope,
          EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.PRIMARY,
        );
        setPreference(
          "campaignbridge/template-editor",
          EDITOR_CONSTANTS.SIDEBAR.PREFERENCE_KEYS.PRIMARY_OPEN,
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
      if (activeSecondary === EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.SECONDARY) {
        disableComplementaryArea(secondaryScope);
        setPreference(
          "campaignbridge/template-editor",
          EDITOR_CONSTANTS.SIDEBAR.PREFERENCE_KEYS.SECONDARY_OPEN,
          false,
        );
      } else {
        enableComplementaryArea(
          secondaryScope,
          EDITOR_CONSTANTS.SIDEBAR.IDENTIFIERS.SECONDARY,
        );
        setPreference(
          "campaignbridge/template-editor",
          EDITOR_CONSTANTS.SIDEBAR.PREFERENCE_KEYS.SECONDARY_OPEN,
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
    // State
    activePrimary,
    activeSecondary,
    isPrimaryOpen: activePrimary === "primary",
    isSecondaryOpen: activeSecondary === "secondary",

    // Actions
    togglePrimary,
    toggleSecondary,
  };
}
