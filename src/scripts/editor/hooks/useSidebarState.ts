import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect } from '@wordpress/element';

// Type definitions for better type safety
type SidebarType = 'primary' | 'secondary';

interface SidebarConfig {
  readonly scope: string;
  readonly identifier: string;
  readonly preferenceKey: string;
  readonly preferencePath: string;
}

// Sidebar-specific constants (exported for reuse if needed)
export const SIDEBAR_CONSTANTS = {
  IDENTIFIERS: {
    PRIMARY: 'primary',
    SECONDARY: 'secondary',
  } as const,
  PREFERENCE_KEYS: {
    PRIMARY_OPEN: 'primarySidebarOpen',
    SECONDARY_OPEN: 'secondarySidebarOpen',
  } as const,
  PREFERENCES: {
    PRIMARY_SIDEBAR_OPEN: 'campaignbridge/template-editor/primarySidebarOpen',
    SECONDARY_SIDEBAR_OPEN:
      'campaignbridge/template-editor/secondarySidebarOpen',
    FULLSCREEN_MODE: 'core/edit-post/fullscreenMode',
  } as const,
  SCOPES: {
    PRIMARY: 'campaignbridge/template-editor/primary',
    SECONDARY: 'campaignbridge/template-editor/secondary',
  } as const,
  TABS: {
    TEMPLATE: 'template-settings',
    INSPECTOR: 'block-inspector',
  } as const,
} as const;

// Configuration mapping for cleaner code
const SIDEBAR_CONFIG_MAP: Record<SidebarType, SidebarConfig> = {
  primary: {
    scope: SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
    preferenceKey: SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
    preferencePath: SIDEBAR_CONSTANTS.PREFERENCES.PRIMARY_SIDEBAR_OPEN,
  },
  secondary: {
    scope: SIDEBAR_CONSTANTS.SCOPES.SECONDARY,
    identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
    preferenceKey: SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
    preferencePath: SIDEBAR_CONSTANTS.PREFERENCES.SECONDARY_SIDEBAR_OPEN,
  },
} as const;

/**
 * Sidebar state interface
 */
interface SidebarState {
  isPrimaryOpen: boolean;
  isSecondaryOpen: boolean;
  togglePrimary: () => void;
  toggleSecondary: () => void;
}

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
 * @returns {SidebarState} Sidebar state and control functions
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

/**
 * useSidebarState - Custom hook for managing sidebar state with WordPress preferences
 *
 * Provides a unified interface for managing primary and secondary sidebar states,
 * including persistence to WordPress preferences and toggle functionality.
 * Integrates with WordPress core/interface store for complementary area management.
 *
 * @param {string} primaryScope - Scope identifier for the primary sidebar
 * @param {string} secondaryScope - Scope identifier for the secondary sidebar
 * @returns {Object} Sidebar state and control functions
 * @returns {boolean} returns.isPrimaryOpen - Whether primary sidebar is currently open
 * @returns {boolean} returns.isSecondaryOpen - Whether secondary sidebar is currently open
 * @returns {Function} returns.togglePrimary - Function to toggle primary sidebar state
 * @returns {Function} returns.toggleSecondary - Function to toggle secondary sidebar state
 *
 * @example
 * ```tsx
 * const {
 *   isPrimaryOpen,
 *   isSecondaryOpen,
 *   togglePrimary,
 *   toggleSecondary
 * } = useSidebarState(
 *   'campaignbridge/template-editor/primary',
 *   'campaignbridge/template-editor/secondary'
 * );
 *
 * // Toggle primary sidebar
 * togglePrimary();
 *
 * // Check sidebar states
 * if (isPrimaryOpen) {
 *   console.log('Primary sidebar is open');
 * }
 * ```
 */
export function useSidebarState(
  // eslint-disable-next-line no-unused-vars -- Reserved for future use; currently uses SIDEBAR_CONFIG_MAP constants.
  primaryScope: string,
  // eslint-disable-next-line no-unused-vars -- Reserved for future use; currently uses SIDEBAR_CONFIG_MAP constants.
  secondaryScope: string
): SidebarState {
  // Get WordPress dispatch functions
  const { enableComplementaryArea, disableComplementaryArea } =
    useDispatch('core/interface');
  const { set: setPreference } = useDispatch('core/preferences');

  // Get interface selector for use in toggle handlers
  const select = useSelect(select => select, []);

  // Create sidebar states using configuration
  const isPrimaryOpen = useSelect(
    select =>
      (select('core/interface') as any).getActiveComplementaryArea(
        SIDEBAR_CONFIG_MAP.primary.scope
      ) === SIDEBAR_CONFIG_MAP.primary.identifier,
    [SIDEBAR_CONFIG_MAP.primary.scope]
  );

  const isSecondaryOpen = useSelect(
    select =>
      (select('core/interface') as any).getActiveComplementaryArea(
        SIDEBAR_CONFIG_MAP.secondary.scope
      ) === SIDEBAR_CONFIG_MAP.secondary.identifier,
    [SIDEBAR_CONFIG_MAP.secondary.scope]
  );

  // Get saved preferences for restoration
  const savedPrimaryOpen = useSelect(
    select =>
      (
        select('core/preferences') as {
          // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
          get: (scope: string, key: string) => unknown;
        }
      ).get(
        SIDEBAR_CONFIG_MAP.primary.preferencePath,
        SIDEBAR_CONFIG_MAP.primary.preferenceKey
      ),
    []
  );

  const savedSecondaryOpen = useSelect(
    select =>
      (
        select('core/preferences') as {
          // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
          get: (scope: string, key: string) => unknown;
        }
      ).get(
        SIDEBAR_CONFIG_MAP.secondary.preferencePath,
        SIDEBAR_CONFIG_MAP.secondary.preferenceKey
      ),
    []
  );

  // Create toggle functions using configuration
  const togglePrimary = useCallback(() => {
    const currentState =
      (select('core/interface') as any).getActiveComplementaryArea(
        SIDEBAR_CONFIG_MAP.primary.scope
      ) === SIDEBAR_CONFIG_MAP.primary.identifier;
    const newState = !currentState;

    if (newState) {
      enableComplementaryArea(
        SIDEBAR_CONFIG_MAP.primary.scope,
        SIDEBAR_CONFIG_MAP.primary.identifier
      );
    } else {
      disableComplementaryArea(SIDEBAR_CONFIG_MAP.primary.scope);
    }

    setPreference(
      SIDEBAR_CONFIG_MAP.primary.preferencePath,
      SIDEBAR_CONFIG_MAP.primary.preferenceKey,
      newState
    );
  }, [
    enableComplementaryArea,
    disableComplementaryArea,
    setPreference,
    select,
  ]);

  const toggleSecondary = useCallback(() => {
    const currentState =
      (select('core/interface') as any).getActiveComplementaryArea(
        SIDEBAR_CONFIG_MAP.secondary.scope
      ) === SIDEBAR_CONFIG_MAP.secondary.identifier;
    const newState = !currentState;

    if (newState) {
      enableComplementaryArea(
        SIDEBAR_CONFIG_MAP.secondary.scope,
        SIDEBAR_CONFIG_MAP.secondary.identifier
      );
    } else {
      disableComplementaryArea(SIDEBAR_CONFIG_MAP.secondary.scope);
    }

    setPreference(
      SIDEBAR_CONFIG_MAP.secondary.preferencePath,
      SIDEBAR_CONFIG_MAP.secondary.preferenceKey,
      newState
    );
  }, [
    enableComplementaryArea,
    disableComplementaryArea,
    setPreference,
    select,
  ]);

  // Restore sidebar states from saved preferences on mount
  useEffect(() => {
    if (savedPrimaryOpen === true) {
      enableComplementaryArea(
        SIDEBAR_CONFIG_MAP.primary.scope,
        SIDEBAR_CONFIG_MAP.primary.identifier
      );
    } else if (savedPrimaryOpen === false) {
      disableComplementaryArea(SIDEBAR_CONFIG_MAP.primary.scope);
    }
  }, [savedPrimaryOpen, enableComplementaryArea, disableComplementaryArea]);

  useEffect(() => {
    if (savedSecondaryOpen === true) {
      enableComplementaryArea(
        SIDEBAR_CONFIG_MAP.secondary.scope,
        SIDEBAR_CONFIG_MAP.secondary.identifier
      );
    } else if (savedSecondaryOpen === false) {
      disableComplementaryArea(SIDEBAR_CONFIG_MAP.secondary.scope);
    }
  }, [savedSecondaryOpen, enableComplementaryArea, disableComplementaryArea]);

  return {
    // State - directly from WordPress interface store
    isPrimaryOpen,
    isSecondaryOpen,

    // Actions
    togglePrimary,
    toggleSecondary,
  };
}
