import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';
import { SIDEBAR_CONSTANTS } from './useSidebarState';

type WordPressInterface = {
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  getActiveComplementaryArea?: (scope: string) => string | undefined;
};

type WordPressBlockEditor = {
  getSelectedBlock?: () => { id?: string } | null;
};

// Layout-specific constants (exported for reuse if needed)
export const LAYOUT_CONSTANTS = {
  MODIFIERS: {
    HAS_PRIMARY: 'cb-editor--has-primary',
    NO_PRIMARY: 'cb-editor--no-primary',
    HAS_SECONDARY: 'cb-editor--has-secondary',
    NO_SECONDARY: 'cb-editor--no-secondary',
  },
  CSS_CLASSES: {
    EDITOR: 'cb-editor',
    EDITOR_SNACKBAR: 'cb-editor__snackbar',
    SIDEBAR_PRIMARY: 'cb-editor__sidebar cb-editor__sidebar--primary',
    SIDEBAR_SECONDARY: 'cb-editor__sidebar cb-editor__sidebar--secondary',
    SIDEBAR_CONTENT: 'cb-editor__sidebar-content',
  },
};

/**
 * Custom hook for managing editor layout state and sidebar coordination
 *
 * @returns {Object} Layout state and sidebar management functions
 */
export function useEditorLayout() {
  // Sidebar tab state
  const [sidebarActiveTab, setSidebarActiveTab] = useState('template-settings');

  // Track active complementary areas with better reactivity
  const activePrimary = useSelect(
    select => {
      try {
        return (
          select('core/interface') as WordPressInterface
        ).getActiveComplementaryArea?.(SIDEBAR_CONSTANTS.SCOPES.PRIMARY);
      } catch (error) {
        console.warn(
          'useEditorLayout: Error getting primary sidebar state:',
          error
        );
        return undefined;
      }
    },
    [SIDEBAR_CONSTANTS.SCOPES.PRIMARY]
  );

  const activeSecondary = useSelect(
    select => {
      try {
        return (
          select('core/interface') as WordPressInterface
        ).getActiveComplementaryArea?.(SIDEBAR_CONSTANTS.SCOPES.SECONDARY);
      } catch (error) {
        console.warn(
          'useEditorLayout: Error getting secondary sidebar state:',
          error
        );
        return undefined;
      }
    },
    [SIDEBAR_CONSTANTS.SCOPES.SECONDARY]
  );

  // Detect block selection for auto-switching
  const selectedBlock = useSelect(select => {
    try {
      const { getSelectedBlock } = select(
        'core/block-editor'
      ) as WordPressBlockEditor;
      return getSelectedBlock?.();
    } catch (error) {
      console.warn(
        'useEditorLayout: Error accessing block editor state:',
        error
      );
      return null;
    }
  }, []);

  // Auto-switch to block inspector when a block is selected
  useEffect(() => {
    if (selectedBlock && sidebarActiveTab !== 'block-inspector') {
      setSidebarActiveTab('block-inspector');
    }
  }, [selectedBlock, sidebarActiveTab]);

  // Note: Sidebar state restoration is now handled by useSidebarState hook

  // Snackbar notifications
  const snackbarNotices = useSelect(
    select =>
      select(noticesStore)
        .getNotices()
        .filter(notice => notice.type === 'snackbar'),
    []
  );

  const { removeNotice } = useDispatch(noticesStore);

  // Layout class management
  const hasPrimary = !!activePrimary;
  const hasSecondary =
    activeSecondary === SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY;

  const skeletonClassName = `${LAYOUT_CONSTANTS.CSS_CLASSES.EDITOR} ${
    hasPrimary
      ? LAYOUT_CONSTANTS.MODIFIERS.HAS_PRIMARY
      : LAYOUT_CONSTANTS.MODIFIERS.NO_PRIMARY
  } ${
    hasSecondary
      ? LAYOUT_CONSTANTS.MODIFIERS.HAS_SECONDARY
      : LAYOUT_CONSTANTS.MODIFIERS.NO_SECONDARY
  }`;

  // Sidebar configuration with dynamic state-based classes
  const primarySidebarProps = {
    scope: SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
    className: `${LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_PRIMARY} cb-editor__sidebar--${activePrimary === SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY ? 'open' : 'closed'}`,
    isPinnable: false,
  };

  const secondarySidebarProps = {
    scope: SIDEBAR_CONSTANTS.SCOPES.SECONDARY,
    identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY,
    closeLabel: 'Close list view',
    isSecondary: true,
    className: `${LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_SECONDARY} cb-editor__sidebar--${activeSecondary === SIDEBAR_CONSTANTS.IDENTIFIERS.SECONDARY ? 'open' : 'closed'}`,
    isPinnable: false,
    header: 'List View',
  };

  return {
    // Layout state
    skeletonClassName,
    hasPrimary,
    hasSecondary,

    // Sidebar state
    sidebarActiveTab,
    setSidebarActiveTab,

    // Sidebar props
    primarySidebarProps,
    secondarySidebarProps,

    // Notifications
    snackbarNotices,
    removeNotice,
  };
}
