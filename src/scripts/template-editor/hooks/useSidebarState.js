import { useDispatch } from "@wordpress/data";
import { useCallback, useEffect, useState } from "@wordpress/element";

/**
 * Custom hook for managing sidebar states with proper subscription and error handling.
 *
 * @param {string} primaryScope - The scope identifier for the primary sidebar
 * @param {string} secondaryScope - The scope identifier for the secondary sidebar
 * @returns {Object} Object containing sidebar states and toggle functions
 */
export function useSidebarState(primaryScope, secondaryScope) {
  const [activePrimary, setActivePrimary] = useState(null);
  const [activeSecondary, setActiveSecondary] = useState(null);

  // Get required functions from WordPress data package
  const { set: setPreference } = useDispatch("core/preferences");
  const { enableComplementaryArea, disableComplementaryArea } =
    useDispatch("core/interface");

  useEffect(() => {
    let isSubscribed = true;

    try {
      // Subscribe to data store changes using WordPress data API
      const unsubscribe = wp.data.subscribe(() => {
        if (!isSubscribed) return;

        try {
          const primary = wp.data
            .select("core/interface")
            .getActiveComplementaryArea(primaryScope);
          const secondary = wp.data
            .select("core/interface")
            .getActiveComplementaryArea(secondaryScope);

          setActivePrimary(primary);
          setActiveSecondary(secondary);
        } catch (error) {
          // Silently handle errors in production
          if (process.env.NODE_ENV === "development") {
            console.warn(
              "useSidebarState: Error updating sidebar state:",
              error,
            );
          }
        }
      });

      // Initial state
      const primary = wp.data
        .select("core/interface")
        .getActiveComplementaryArea(primaryScope);
      const secondary = wp.data
        .select("core/interface")
        .getActiveComplementaryArea(secondaryScope);

      if (isSubscribed) {
        setActivePrimary(primary);
        setActiveSecondary(secondary);
      }

      return () => {
        isSubscribed = false;
        unsubscribe();
      };
    } catch (error) {
      // Handle setup errors gracefully
      if (process.env.NODE_ENV === "development") {
        console.warn(
          "useSidebarState: Error setting up sidebar subscription:",
          error,
        );
      }
      return () => {}; // Return empty cleanup function
    }
  }, [primaryScope, secondaryScope]);

  /**
   * Toggle the primary sidebar with proper state management and preference persistence.
   */
  const togglePrimary = useCallback(() => {
    try {
      const currentState = wp.data
        .select("core/interface")
        .getActiveComplementaryArea(primaryScope);

      if (currentState === "primary") {
        disableComplementaryArea(primaryScope);
        setPreference(
          "campaignbridge/template-editor",
          "primarySidebarOpen",
          false,
        );
      } else {
        enableComplementaryArea(primaryScope, "primary");
        setPreference(
          "campaignbridge/template-editor",
          "primarySidebarOpen",
          true,
        );
      }
    } catch (error) {
      if (process.env.NODE_ENV === "development") {
        console.warn("useSidebarState: Error toggling primary sidebar:", error);
      }
    }
  }, [
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
      const currentState = wp.data
        .select("core/interface")
        .getActiveComplementaryArea(secondaryScope);

      if (currentState === "secondary") {
        disableComplementaryArea(secondaryScope);
        setPreference(
          "campaignbridge/template-editor",
          "secondarySidebarOpen",
          false,
        );
      } else {
        enableComplementaryArea(secondaryScope, "secondary");
        setPreference(
          "campaignbridge/template-editor",
          "secondarySidebarOpen",
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
