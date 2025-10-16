import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect } from '@wordpress/element';

// Sidebar-specific constants (exported for reuse if needed)
export const SIDEBAR_CONSTANTS = {
  IDENTIFIERS: {
    PRIMARY: 'primary',
    SECONDARY: 'secondary',
  },
  PREFERENCE_KEYS: {
    PRIMARY_OPEN: 'primarySidebarOpen',
    SECONDARY_OPEN: 'secondarySidebarOpen',
  },
  PREFERENCES: {
    PRIMARY_SIDEBAR_OPEN: 'campaignbridge/template-editor/primarySidebarOpen',
    SECONDARY_SIDEBAR_OPEN:
      'campaignbridge/template-editor/secondarySidebarOpen',
    FULLSCREEN_MODE: 'core/edit-post/fullscreenMode',
  },
  SCOPES: {
    PRIMARY: 'campaignbridge/template-editor/primary',
    SECONDARY: 'campaignbridge/template-editor/secondary',
  },
  TABS: {
    TEMPLATE: 'template-settings',
    INSPECTOR: 'block-inspector',
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
 * Custom hook for managing sidebar states using WordPress interface store.
 * Follows WordPress core patterns for complementary area state management.
 *
 * Features:
 * - **WordPress Interface Store**: Uses core/interface store as the single source of truth
 * - **Preference Persistence**: Saves sidebar states to WordPress preferences
 * - **State Restoration**: Restores saved states on component mount
 * - **Close Button Support**: Captures ComplementaryArea close button interactions
 * - **Real-time Sync**: Automatically syncs with WordPress interface store changes
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
  // Get sidebar states directly from WordPress interface store
  const isPrimaryOpen = useSelect(
    select => {
      const state = (
        select('core/interface') as any
      ).getActiveComplementaryArea(primaryScope);
      return state === SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY;
    },
    [primaryScope]
  );

  const isSecondaryOpen = useSelect(
    select => {
      const state = (
        select('core/interface') as any
      ).getActiveComplementaryArea(secondaryScope);
      return state === SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY;
    },
    [secondaryScope]
  );

  // Get saved preferences for restoration
  const savedPrimaryOpen = useSelect(
    select =>
      (
        select('core/preferences') as {
          get: (scope: string, key: string) => unknown;
        }
      ).get(
        SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
        SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN
      ),
    []
  );

  const savedSecondaryOpen = useSelect(
    select =>
      (
        select('core/preferences') as {
          get: (scope: string, key: string) => unknown;
        }
      ).get(
        SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
        SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN
      ),
    []
  );

  // Get WordPress dispatch functions
  const { enableComplementaryArea, disableComplementaryArea } =
    useDispatch('core/interface');
  const { set: setPreference } = useDispatch('core/preferences');

  /**
   * Restore sidebar state from saved preferences on mount.
   * Only acts if we have a definitive saved preference (true/false).
   * If savedState is undefined, we don't change anything to let default state prevail.
   */
  const restoreSidebarState = useCallback(
    (scope: string, identifier: string, savedState: boolean | undefined) => {
      if (savedState === true) {
        enableComplementaryArea(scope, identifier);
      } else if (savedState === false) {
        disableComplementaryArea(scope);
      }
      // If savedState is undefined, we don't change anything - let the default state prevail
    },
    [enableComplementaryArea, disableComplementaryArea]
  );

  // Restore sidebar states from saved preferences on mount
  useEffect(() => {
    restoreSidebarState(
      primaryScope,
      SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
      savedPrimaryOpen as boolean | undefined
    );
  }, [savedPrimaryOpen, primaryScope, restoreSidebarState]);

  useEffect(() => {
    restoreSidebarState(
      secondaryScope,
      SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
      savedSecondaryOpen as boolean | undefined
    );
  }, [savedSecondaryOpen, secondaryScope, restoreSidebarState]);

  /**
   * Create a toggle handler for a sidebar that updates interface store and saves preferences.
   */
  const createToggleHandler = useCallback(
    (
      scope: string,
      identifier: string,
      preferenceKey: string,
      preferencePath: string
    ) => {
      return () => {
        try {
          // Get current state from interface store
          const currentState =
            isPrimaryOpen && scope === primaryScope
              ? isPrimaryOpen
              : isSecondaryOpen && scope === secondaryScope
                ? isSecondaryOpen
                : false;
          const newState = !currentState;

          // Update interface store
          if (newState) {
            enableComplementaryArea(scope, identifier);
          } else {
            disableComplementaryArea(scope);
          }

          // Save preference for persistence
          setPreference(preferencePath, preferenceKey, newState);
        } catch (error) {
          if ((globalThis as any).process?.env?.NODE_ENV === 'development') {
            console.warn('useSidebarState: Error toggling sidebar:', error);
          }
        }
      };
    },
    [
      isPrimaryOpen,
      isSecondaryOpen,
      primaryScope,
      secondaryScope,
      enableComplementaryArea,
      disableComplementaryArea,
      setPreference,
    ]
  );

  /**
   * Toggle the primary sidebar with proper state management and preference persistence.
   */
  const togglePrimary = createToggleHandler(
    primaryScope,
    SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
    SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
    SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN
  );

  /**
   * Toggle the secondary sidebar with proper state management and preference persistence.
   */
  const toggleSecondary = createToggleHandler(
    secondaryScope,
    SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
    SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
    SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN
  );

  return {
    // State - directly from WordPress interface store
    isPrimaryOpen,
    isSecondaryOpen,

    // Actions
    togglePrimary,
    toggleSecondary,
  };
}
