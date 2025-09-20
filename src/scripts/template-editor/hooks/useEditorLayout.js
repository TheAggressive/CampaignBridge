import { useDispatch, useSelect } from "@wordpress/data";
import { useEffect, useState } from "@wordpress/element";
import { store as noticesStore } from "@wordpress/notices";
import { store as preferencesStore } from "@wordpress/preferences";
import { EDITOR_CONSTANTS } from "../constants/editor";

/**
 * Custom hook for managing editor layout state and sidebar coordination
 *
 * @returns {Object} Layout state and sidebar management functions
 */
export function useEditorLayout() {
  // Sidebar tab state
  const [sidebarActiveTab, setSidebarActiveTab] = useState(
    EDITOR_CONSTANTS.SIDEBAR_TABS.TEMPLATE,
  );

  // Track active complementary areas
  const activePrimary = useSelect(
    (select) =>
      select("core/interface").getActiveComplementaryArea(
        EDITOR_CONSTANTS.SIDEBAR_SCOPES.PRIMARY,
      ),
    [EDITOR_CONSTANTS.SIDEBAR_SCOPES.PRIMARY],
  );

  const activeSecondary = useSelect(
    (select) =>
      select("core/interface").getActiveComplementaryArea(
        EDITOR_CONSTANTS.SIDEBAR_SCOPES.SECONDARY,
      ),
    [EDITOR_CONSTANTS.SIDEBAR_SCOPES.SECONDARY],
  );

  // Detect block selection for auto-switching
  const selectedBlock = useSelect((select) => {
    try {
      const { getSelectedBlock } = select("core/block-editor");
      return getSelectedBlock();
    } catch (error) {
      console.warn(
        "useEditorLayout: Error accessing block editor state:",
        error,
      );
      return null;
    }
  }, []);

  // Auto-switch to block inspector when a block is selected
  useEffect(() => {
    if (
      selectedBlock &&
      sidebarActiveTab !== EDITOR_CONSTANTS.SIDEBAR_TABS.INSPECTOR
    ) {
      setSidebarActiveTab(EDITOR_CONSTANTS.SIDEBAR_TABS.INSPECTOR);
    }
  }, [selectedBlock, sidebarActiveTab]);

  // Restore sidebar states from preferences on mount
  const { enableComplementaryArea } = useDispatch("core/interface");
  const { get: getPreference } = useSelect(
    (select) => select(preferencesStore),
    [],
  );

  useEffect(() => {
    // Restore primary sidebar state from preferences
    const primaryOpen = getPreference(
      "campaignbridge/template-editor",
      "primarySidebarOpen",
    );
    if (primaryOpen) {
      enableComplementaryArea(
        EDITOR_CONSTANTS.SIDEBAR_SCOPES.PRIMARY,
        "primary",
      );
    }

    // Restore secondary sidebar state from preferences
    const secondaryOpen = getPreference(
      "campaignbridge/template-editor",
      "secondarySidebarOpen",
    );
    if (secondaryOpen) {
      enableComplementaryArea(
        EDITOR_CONSTANTS.SIDEBAR_SCOPES.SECONDARY,
        "secondary",
      );
    }
  }, [getPreference, enableComplementaryArea]);

  // Snackbar notifications
  const snackbarNotices = useSelect(
    (select) =>
      select(noticesStore)
        .getNotices()
        .filter((notice) => notice.type === "snackbar"),
    [],
  );

  const { removeNotice } = useDispatch(noticesStore);

  // Layout class management
  const hasPrimary = !!activePrimary;
  const hasSecondary = activeSecondary === "secondary";

  const skeletonClassName = `${EDITOR_CONSTANTS.CSS_CLASSES.EDITOR} ${
    hasPrimary
      ? EDITOR_CONSTANTS.LAYOUT_MODIFIERS.HAS_PRIMARY
      : EDITOR_CONSTANTS.LAYOUT_MODIFIERS.NO_PRIMARY
  } ${
    hasSecondary
      ? EDITOR_CONSTANTS.LAYOUT_MODIFIERS.HAS_SECONDARY
      : EDITOR_CONSTANTS.LAYOUT_MODIFIERS.NO_SECONDARY
  }`;

  // Sidebar configuration
  const primarySidebarProps = {
    scope: EDITOR_CONSTANTS.SIDEBAR_SCOPES.PRIMARY,
    identifier: "primary",
    className: EDITOR_CONSTANTS.CSS_CLASSES.SIDEBAR_PRIMARY,
    isPinnable: false,
  };

  const secondarySidebarProps = {
    scope: EDITOR_CONSTANTS.SIDEBAR_SCOPES.SECONDARY,
    identifier: "secondary",
    closeLabel: "Close list view",
    isSecondary: true,
    className: EDITOR_CONSTANTS.CSS_CLASSES.SIDEBAR_SECONDARY,
    isPinnable: false,
    header: "List View",
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
