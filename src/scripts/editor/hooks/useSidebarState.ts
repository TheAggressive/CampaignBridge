import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect } from '@wordpress/element';

type SidebarType = 'primary' | 'secondary';

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
    SCOPE: 'campaignbridge/template-editor',
    FULLSCREEN_MODE: 'fullscreenMode',
  },
  SCOPES: {
    PRIMARY: 'campaignbridge/template-editor/primary',
    SECONDARY: 'campaignbridge/template-editor/secondary',
  },
  TABS: {
    TEMPLATE: 'template-settings',
    INSPECTOR: 'block-inspector',
  },
} as const;

const SIDEBARS = {
  primary: {
    scope: SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
    preferenceKey: SIDEBAR_CONSTANTS.PREFERENCE_KEYS.PRIMARY_OPEN,
  },
  secondary: {
    scope: SIDEBAR_CONSTANTS.SCOPES.SECONDARY,
    identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
    preferenceKey: SIDEBAR_CONSTANTS.PREFERENCE_KEYS.SECONDARY_OPEN,
  },
} as const;

interface InterfaceSelectors {
  getActiveComplementaryArea: (scope: string) => string | undefined;
}

interface InterfaceActions {
  enableComplementaryArea: (scope: string, identifier: string) => void;
  disableComplementaryArea: (scope: string) => void;
}

interface PreferenceSelectors {
  get: (scope: string, key: string) => unknown;
}

interface PreferenceActions {
  set: (scope: string, key: string, value: unknown) => void;
}

interface SidebarState {
  isPrimaryOpen: boolean;
  isSecondaryOpen: boolean;
  openPrimary: () => void;
  togglePrimary: () => void;
  toggleSecondary: () => void;
}

/** Keep complementary-area state in WordPress stores and persist user intent. */
export function useSidebarState(): SidebarState {
  const { enableComplementaryArea, disableComplementaryArea } = useDispatch(
    'core/interface'
  ) as unknown as InterfaceActions;
  const { set: setPreference } = useDispatch(
    'core/preferences'
  ) as unknown as PreferenceActions;

  const {
    isPrimaryOpen,
    isSecondaryOpen,
    savedPrimaryOpen,
    savedSecondaryOpen,
  } = useSelect(select => {
    const interfaceSelectors = select(
      'core/interface'
    ) as unknown as InterfaceSelectors;
    const preferences = select(
      'core/preferences'
    ) as unknown as PreferenceSelectors;

    return {
      isPrimaryOpen:
        interfaceSelectors.getActiveComplementaryArea(
          SIDEBARS.primary.scope
        ) === SIDEBARS.primary.identifier,
      isSecondaryOpen:
        interfaceSelectors.getActiveComplementaryArea(
          SIDEBARS.secondary.scope
        ) === SIDEBARS.secondary.identifier,
      savedPrimaryOpen: preferences.get(
        SIDEBAR_CONSTANTS.PREFERENCES.SCOPE,
        SIDEBARS.primary.preferenceKey
      ),
      savedSecondaryOpen: preferences.get(
        SIDEBAR_CONSTANTS.PREFERENCES.SCOPE,
        SIDEBARS.secondary.preferenceKey
      ),
    };
  }, []);

  const setOpen = useCallback(
    (type: SidebarType, open: boolean) => {
      const config = SIDEBARS[type];

      if (open) {
        enableComplementaryArea(config.scope, config.identifier);
      } else {
        disableComplementaryArea(config.scope);
      }

      setPreference(
        SIDEBAR_CONSTANTS.PREFERENCES.SCOPE,
        config.preferenceKey,
        open
      );
    },
    [disableComplementaryArea, enableComplementaryArea, setPreference]
  );

  useEffect(() => {
    if (typeof savedPrimaryOpen === 'boolean') {
      setOpen('primary', savedPrimaryOpen);
    }
  }, [savedPrimaryOpen, setOpen]);

  useEffect(() => {
    if (typeof savedSecondaryOpen === 'boolean') {
      setOpen('secondary', savedSecondaryOpen);
    }
  }, [savedSecondaryOpen, setOpen]);

  const openPrimary = useCallback(() => setOpen('primary', true), [setOpen]);
  const togglePrimary = useCallback(
    () => setOpen('primary', !isPrimaryOpen),
    [isPrimaryOpen, setOpen]
  );
  const toggleSecondary = useCallback(
    () => setOpen('secondary', !isSecondaryOpen),
    [isSecondaryOpen, setOpen]
  );

  return {
    isPrimaryOpen,
    isSecondaryOpen,
    openPrimary,
    togglePrimary,
    toggleSecondary,
  };
}
