import { BlockEditorProvider } from "@wordpress/block-editor";
import { parse } from "@wordpress/blocks";
import { Popover, SlotFillProvider, SnackbarList } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { useCallback, useEffect, useRef, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import {
  ComplementaryArea,
  FullscreenMode,
  InterfaceSkeleton,
} from "@wordpress/interface";
import { ShortcutProvider } from "@wordpress/keyboard-shortcuts";
import { getPostRaw, savePostContent } from "../services/api";
import { blockPatternCategories, blockPatterns } from "../utils/blockPatterns";
import { serializeSafe } from "../utils/blocks";
import { useEditorSettings } from "../utils/useEditorSettings";
import Content from "./Content";
import Footer from "./Footer";
import Header from "./Header";
import SecondarySidebar from "./Sidebars/SecondarySidebar";
import { SidebarContent, SidebarHeader } from "./Sidebars/Sidebar";

/* NEW: Preferences store for UI state */
import { store as noticesStore } from "@wordpress/notices";
import { store as preferencesStore } from "@wordpress/preferences";
import { useAutoSave } from "../utils/useAutoSave";
import { useNotices } from "../utils/useNotices";

/* Interface scopes (must match Header.jsx) - separate scopes for independent sidebars */
const SCOPE_PRIMARY = "campaignbridge/template-editor/primary";
const SCOPE_SECONDARY = "campaignbridge/template-editor/secondary";

/**
 * Editor Chrome Component
 *
 * Main editor component that orchestrates the WordPress block editor experience.
 * Sets up essential providers including ShortcutProvider, SlotFillProvider, and
 * BlockEditorProvider to ensure proper editor functionality. Manages post loading,
 * block state, and save operations while providing the InterfaceSkeleton layout.
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
  const [ready, setReady] = useState(false);
  const [blocks, setBlocks] = useState([]);
  const [saveStatus, setSaveStatus] = useState("saved"); // 'saved', 'saving', 'error'
  const { success, error: errorNotice } = useNotices();
  const {
    settings: editorSettings,
    error: editorSettingsError,
    loading: editorSettingsLoading,
  } = useEditorSettings(postType);

  // Load the post content and initialize the block editor
  // Waits for block types to be registered, fetches the post data,
  // parses the content, and sets the component as ready
  useEffect(() => {
    let alive = true;
    (async () => {
      if (!postId) return;
      const post = await getPostRaw(postId);
      if (!alive) return;
      const parsedBlocks = parse(post?.content?.raw || "");
      setBlocks(parsedBlocks);
      setReady(true);
    })();
    return () => {
      alive = false;
    };
  }, [postId]);

  // Debounced save using hook
  const lastNoticeAtRef = useRef(0);

  const performSave = useCallback(
    async (blocksToSave, { signal } = {}) => {
      try {
        setSaveStatus("saving");

        const result = await savePostContent(
          postId,
          {
            content: serializeSafe(blocksToSave),
          },
          signal,
        );

        setSaveStatus("saved");

        // Native snackbar notification (throttled to avoid spam)
        try {
          const now = Date.now();
          if (now - lastNoticeAtRef.current > 8000) {
            success(__("Template saved", "campaignbridge"));
            lastNoticeAtRef.current = now;
          }
        } catch {}

        onBlocksChange && onBlocksChange(blocksToSave);

        return result;
      } catch (error) {
        console.error("Save failed:", error);
        setSaveStatus("error");
        try {
          errorNotice(__("Failed to save changes", "campaignbridge"));
        } catch {}
        throw error;
      }
    },
    [postId, onBlocksChange, success, errorNotice],
  );

  const save = useAutoSave(performSave);

  // Unified update handler that coalesces onInput/onChange into a single save per frame
  const handleBlocksUpdate = useCallback(
    (next) => {
      setBlocks(next);
      if (typeof save.schedule === "function") {
        save.schedule(next);
      } else {
        save(next);
      }
    },
    [save],
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

  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get("core/edit-post", "fullscreenMode"),
    [],
  );

  // Track active complementary areas to drive layout classes - independent sidebars
  const activePrimary = useSelect(
    (select) =>
      select("core/interface").getActiveComplementaryArea(SCOPE_PRIMARY),
    [SCOPE_PRIMARY],
  );
  const activeSecondary = useSelect(
    (select) =>
      select("core/interface").getActiveComplementaryArea(SCOPE_SECONDARY),
    [SCOPE_SECONDARY],
  );

  // For animations: each slot has content when its respective area is active
  const hasPrimary = !!activePrimary; // Any primary area active
  const hasSecondary = activeSecondary === "secondary";

  // Sidebar tab state management
  const [sidebarActiveTab, setSidebarActiveTab] = useState("template-settings");

  // Auto-switch to block inspector when a block is selected
  const selectedBlock = useSelect((select) => {
    const { getSelectedBlock } = select("core/block-editor");
    return getSelectedBlock();
  }, []);

  useEffect(() => {
    if (selectedBlock) {
      setSidebarActiveTab("block-inspector");
    }
  }, [selectedBlock]);

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
      enableComplementaryArea(SCOPE_PRIMARY, "primary");
    }

    // Restore secondary sidebar state from preferences
    const secondaryOpen = getPreference(
      "campaignbridge/template-editor",
      "secondarySidebarOpen",
    );
    if (secondaryOpen) {
      enableComplementaryArea(SCOPE_SECONDARY, "secondary");
    }
  }, []); // Run once on mount

  // Hoist snackbar hooks (avoid calling hooks inside JSX props)
  const snackbarNotices = useSelect(
    (select) =>
      select(noticesStore)
        .getNotices()
        .filter((n) => n.type === "snackbar"),
    [],
  );
  const { removeNotice } = useDispatch(noticesStore);

  if (!ready) {
    return (
      <div className="cb-editor-loading">
        <p>{__("Initializing editor…", "campaignbridge")}</p>
      </div>
    );
  }

  if (editorSettingsLoading) {
    return (
      <div className="cb-editor-loading">
        <p>{__("Loading editor settings…", "campaignbridge")}</p>
      </div>
    );
  }

  if (editorSettingsError) {
    return (
      <div className="cb-editor-error">
        <p>{__("Error loading editor settings…", "campaignbridge")}</p>
      </div>
    );
  }

  const mergedEditorSettings = {
    ...editorSettings,
    ...blockPatterns,
    ...blockPatternCategories,
  };

  const skeletonClassName = `cb-editor ${
    hasPrimary ? "cb-editor--has-primary" : "cb-editor--no-primary"
  } ${hasSecondary ? "cb-editor--has-secondary" : "cb-editor--no-secondary"}`;

  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <FullscreenMode isActive={isFullscreen} />

        {/* Single ComplementaryArea with tabs in header like WordPress core */}
        <ComplementaryArea
          scope={SCOPE_PRIMARY}
          identifier="primary"
          className="cb-editor__sidebar cb-editor__sidebar--primary"
          isPinnable={false}
          header={
            <SidebarHeader
              activeTab={sidebarActiveTab}
              onTabChange={setSidebarActiveTab}
            />
          }
        >
          <div className="cb-editor__sidebar-content">
            <SidebarContent activeTab={sidebarActiveTab} />
          </div>
        </ComplementaryArea>

        <ComplementaryArea
          scope={SCOPE_SECONDARY}
          identifier="secondary"
          closeLabel={__("Close list view", "campaignbridge")}
          isSecondary
          className="cb-editor__sidebar cb-editor__sidebar--secondary"
          isPinnable={false}
        >
          <div className="cb-editor__sidebar-content">
            <SecondarySidebar />
          </div>
        </ComplementaryArea>

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
            /* CONTENT stays the same */
            content={<Content />}
            /* PRIMARY SIDEBAR */
            sidebar={
              <ComplementaryArea.Slot
                scope={SCOPE_PRIMARY}
                identifier="primary"
              />
            }
            /* SECONDARY SIDEBAR */
            secondarySidebar={
              <ComplementaryArea.Slot
                scope={SCOPE_SECONDARY}
                identifier="secondary"
              />
            }
            footer={<Footer />}
          />
        </BlockEditorProvider>

        <Popover.Slot />
        <div className="cb-editor__snackbar">
          <SnackbarList notices={snackbarNotices} onRemove={removeNotice} />
        </div>
      </SlotFillProvider>
    </ShortcutProvider>
  );
}
