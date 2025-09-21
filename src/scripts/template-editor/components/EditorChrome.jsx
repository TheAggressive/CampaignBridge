import { BlockEditorProvider } from "@wordpress/block-editor";
import { Popover, SlotFillProvider, SnackbarList } from "@wordpress/components";
import { useSelect } from "@wordpress/data";
import { useCallback, useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import {
  ComplementaryArea,
  FullscreenMode,
  InterfaceSkeleton,
} from "@wordpress/interface";
import { ShortcutProvider } from "@wordpress/keyboard-shortcuts";
import { useAutoSaveManager } from "../hooks/useAutoSaveManager";
import { useEditorData } from "../hooks/useEditorData";
import { LAYOUT_CONSTANTS, useEditorLayout } from "../hooks/useEditorLayout";
import { useEditorSettings } from "../hooks/useEditorSettings";
import { useNotices } from "../hooks/useNotices";
import { SIDEBAR_CONSTANTS } from "../hooks/useSidebarState";
import { blockPatternCategories, blockPatterns } from "../utils/blockPatterns";
import Content from "./Content";
import { ErrorState, LoadingState } from "./EditorStates";
import Footer from "./Footer";
import Header from "./Header";
import SecondarySidebar from "./Sidebars/SecondarySidebar";
import { SidebarContent, SidebarHeader } from "./Sidebars/Sidebar";

/**
 * Editor Chrome Component (Refactored)
 *
 * Lightweight coordinator component that orchestrates the WordPress block editor experience
 * using custom hooks for state management. This component focuses solely on coordination
 * and rendering, with all complex logic extracted to specialized hooks.
 *
 * Features:
 * - Custom hooks for data loading, auto-save, and layout management
 * - Clean separation of concerns with dedicated state components
 * - Centralized constants for maintainability
 * - Simplified JSX with reusable components
 *
 * @param {Object} props - Component props
 * @param {Array} props.list - Array of available templates for the header dropdown
 * @param {number|null} props.currentId - ID of the currently selected template
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {function} props.onSelect - Callback fired when a template is selected
 * @param {function} props.onNew - Callback fired when creating a new template
 * @param {string} props.postType - Post type for editor settings (default: 'post')
 * @param {number} props.postId - The ID of the post/template to load and edit
 * @param {function} [props.onBlocksChange] - Optional callback fired when blocks change
 * @returns {JSX.Element} The complete editor interface with all necessary providers
 *
 * @example
 * ```jsx
 * <EditorChrome
 *   list={templates}
 *   currentId={1}
 *   loading={false}
 *   onSelect={onSelect}
 *   onNew={onNew}
 *   postId={1}
 *   postType="post"
 * />
 * ```
 */
export default function EditorChrome({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
  postId,
  onBlocksChange,
  postType = "post",
}) {
  // Use custom hooks to manage complex state
  const {
    ready,
    blocks,
    error,
    loading: dataLoading,
    setBlocks,
  } = useEditorData(postId, postType);

  const { success, error: errorNotice } = useNotices();
  const {
    settings: editorSettings,
    error: editorSettingsError,
    loading: editorSettingsLoading,
  } = useEditorSettings(postType);

  const { save, saveStatus } = useAutoSaveManager(
    postId,
    onBlocksChange,
    success,
    errorNotice,
  );

  const {
    skeletonClassName,
    sidebarActiveTab,
    setSidebarActiveTab,
    primarySidebarProps,
    secondarySidebarProps,
    snackbarNotices,
    removeNotice,
  } = useEditorLayout();

  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get(
        SIDEBAR_CONSTANTS.PREFERENCES.FULLSCREEN_MODE,
      ),
    [],
  );

  // Unified update handler using custom hook
  const handleBlocksUpdate = useCallback(
    (next) => {
      setBlocks(next);
      if (typeof save.schedule === "function") {
        save.schedule(next);
      } else {
        save(next);
      }
    },
    [save, setBlocks],
  );

  // Flush pending save on navigation/unload
  useEffect(() => {
    const beforeUnload = () => {
      if (typeof save.flush === "function") {
        save.flush();
      }
    };
    window.addEventListener("beforeunload", beforeUnload);
    return () => window.removeEventListener("beforeunload", beforeUnload);
  }, [save]);

  // Early returns for loading and error states
  if (!ready) {
    return (
      <LoadingState message={__("Initializing editor…", "campaignbridge")} />
    );
  }

  if (editorSettingsLoading) {
    return (
      <LoadingState
        message={__("Loading editor settings…", "campaignbridge")}
      />
    );
  }

  if (editorSettingsError) {
    return (
      <ErrorState
        message={__("Error loading editor settings…", "campaignbridge")}
      />
    );
  }

  // Merge editor settings with patterns
  const mergedEditorSettings = {
    ...editorSettings,
    ...blockPatterns,
    ...blockPatternCategories,
  };

  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <FullscreenMode isActive={isFullscreen} />

        {/* Primary sidebar with tabs */}
        <ComplementaryArea
          {...primarySidebarProps}
          header={
            <SidebarHeader
              activeTab={sidebarActiveTab}
              onTabChange={setSidebarActiveTab}
            />
          }
        >
          <div className={LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_CONTENT}>
            <SidebarContent activeTab={sidebarActiveTab} />
          </div>
        </ComplementaryArea>

        {/* Secondary sidebar (list view) */}
        <ComplementaryArea {...secondarySidebarProps}>
          <div className={LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_CONTENT}>
            <SecondarySidebar />
          </div>
        </ComplementaryArea>

        {/* Block editor with merged settings */}
        <BlockEditorProvider
          value={blocks}
          onInput={handleBlocksUpdate}
          onChange={handleBlocksUpdate}
          settings={mergedEditorSettings}
        >
          <InterfaceSkeleton
            className={skeletonClassName}
            header={
              <Header
                list={list}
                currentId={currentId}
                loading={loading}
                onSelect={onSelect}
                onNew={onNew}
              />
            }
            content={<Content />}
            sidebar={<ComplementaryArea.Slot {...primarySidebarProps} />}
            secondarySidebar={
              <ComplementaryArea.Slot {...secondarySidebarProps} />
            }
            footer={<Footer />}
          />
        </BlockEditorProvider>

        <Popover.Slot />
        <div className={LAYOUT_CONSTANTS.CSS_CLASSES.EDITOR_SNACKBAR}>
          <SnackbarList notices={snackbarNotices} onRemove={removeNotice} />
        </div>
      </SlotFillProvider>
    </ShortcutProvider>
  );
}
