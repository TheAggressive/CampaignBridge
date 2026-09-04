import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';
import type { SidebarTab } from '../types';
import { SIDEBAR_CONSTANTS } from './useSidebarState';

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
    SIDEBAR_CONTENT: 'cb-editor__sidebar-content',
  },
} as const;

interface UseEditorLayoutOptions {
  isPrimaryOpen: boolean;
  isSecondaryOpen: boolean;
}

/** Derive editor layout props from the sidebar store's single subscription. */
export function useEditorLayout({
  isPrimaryOpen,
  isSecondaryOpen,
}: UseEditorLayoutOptions) {
  const [sidebarActiveTab, setSidebarActiveTab] = useState<SidebarTab>(
    SIDEBAR_CONSTANTS.TABS.TEMPLATE
  );
  const snackbarNotices = useSelect(
    select =>
      select(noticesStore)
        .getNotices()
        .filter(notice => notice.type === 'snackbar'),
    []
  );
  const { removeNotice } = useDispatch(noticesStore);

  const skeletonClassName = `${LAYOUT_CONSTANTS.CSS_CLASSES.EDITOR} ${
    isPrimaryOpen
      ? LAYOUT_CONSTANTS.MODIFIERS.HAS_PRIMARY
      : LAYOUT_CONSTANTS.MODIFIERS.NO_PRIMARY
  } ${
    isSecondaryOpen
      ? LAYOUT_CONSTANTS.MODIFIERS.HAS_SECONDARY
      : LAYOUT_CONSTANTS.MODIFIERS.NO_SECONDARY
  }`;

  return {
    skeletonClassName,
    sidebarActiveTab,
    setSidebarActiveTab,
    primarySidebarProps: {
      scope: SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
      identifier: SIDEBAR_CONSTANTS.IDENTIFIERS.PRIMARY,
      className: `${LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_PRIMARY} cb-editor__sidebar--${isPrimaryOpen ? 'open' : 'closed'}`,
      isPinnable: false,
    },
    snackbarNotices,
    removeNotice,
  };
}
