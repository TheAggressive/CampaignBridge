import { BlockEditorProvider } from "@wordpress/block-editor";
import { parse } from "@wordpress/blocks";
import { Button, Popover, SlotFillProvider } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import { useCallback, useEffect, useRef, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
/* Keep InterfaceSkeleton if it’s already working in your bundle.
   If not, this still acts as a simple layout container. */
import { FullscreenMode, InterfaceSkeleton } from "@wordpress/interface";
import { ShortcutProvider } from "@wordpress/keyboard-shortcuts";
import { getPostRaw, savePostContent } from "../services/api";
import { blockPatternCategories, blockPatterns } from "../utils/blockPatterns";
import { serializeSafe } from "../utils/blocks";
import { useEditorSettings } from "../utils/useEditorSettings";
import Content from "./Content";
import Footer from "./Footer";
import Header from "./Header";
import SecondarySidebar from "./SecondarySidebar/SecondarySidebar";
import Sidebar from "./Sidebar";

/* NEW: Preferences store for UI state */
import { store as preferencesStore } from "@wordpress/preferences";
import { useAutoSave } from "../utils/useAutoSave";
import { useNotices } from "../utils/useNotices";

/* Namespace/keys (must match Header.jsx) */
const NS = "campaignbridge/template-editor";
const K_PRIMARY = "primaryOpen";
const K_SECONDARY = "secondaryOpen";

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
 *   onSelect={handleSelect}
 *   onNew={handleNew}
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

  // No RAF cleanup needed; scheduling handled in useAutoSave

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

  // Use default debounce from hook; can override by passing second arg
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

  /* NEW: read open states from Preferences */
  const primaryOpen = useSelect((select) => {
    const v = select(preferencesStore).get(NS, K_PRIMARY);
    return typeof v === "boolean" ? v : true;
  }, []);
  const secondaryOpen = useSelect((select) => {
    const v = select(preferencesStore).get(NS, K_SECONDARY);
    return typeof v === "boolean" ? v : false;
  }, []);

  /* NEW: setters for the close “X” buttons */
  const { set } = useDispatch(preferencesStore);

  // Button refs for polished focus management
  const primaryToggleRef = useRef(null);
  const secondaryToggleRef = useRef(null);

  const moveFocusAfterClose = (sidebarId, toggleRef) => {
    // Defer to after state change so aria-hidden/inert have applied
    requestAnimationFrame(() => {
      const sidebar = document.getElementById(sidebarId);
      const active = document.activeElement;
      if (sidebar && active && sidebar.contains(active)) {
        const btn = toggleRef?.current;
        if (btn && typeof btn.focus === "function") {
          btn.focus();
        } else if (typeof document.body.focus === "function") {
          document.body.focus();
        }
      }
    });
  };

  const closePrimary = () => {
    set(NS, K_PRIMARY, false);
    moveFocusAfterClose("cb-primary-sidebar", primaryToggleRef);
  };
  const closeSecondary = () => {
    set(NS, K_SECONDARY, false);
    moveFocusAfterClose("cb-secondary-sidebar", secondaryToggleRef);
  };

  // Keyboard: match Gutenberg + ESC close
  useEffect(() => {
    const onKeyDown = (event) => {
      const active = document.activeElement;
      const primaryEl = document.getElementById("cb-primary-sidebar");
      const secondaryEl = document.getElementById("cb-secondary-sidebar");
      const onPrimaryToggle = active === primaryToggleRef.current;
      const onSecondaryToggle = active === secondaryToggleRef.current;

      // Toggle Settings (primary): Ctrl+Shift+Comma (Win) / Cmd+Shift+Comma (Mac)
      const isTogglePrimary =
        (event.code === "Comma" || event.key === ",") &&
        event.shiftKey &&
        ((event.ctrlKey && !event.metaKey) ||
          (event.metaKey && !event.ctrlKey));
      if (isTogglePrimary) {
        event.preventDefault();
        set(NS, K_PRIMARY, !primaryOpen);
        return;
      }

      // Toggle List View (secondary): Shift+Alt+O
      const key = (event.key || "").toLowerCase();
      const isToggleSecondary =
        (event.code === "KeyO" || key === "o") &&
        event.altKey &&
        event.shiftKey;
      if (isToggleSecondary) {
        event.preventDefault();
        set(NS, K_SECONDARY, !secondaryOpen);
        return;
      }

      // ESC closes the sidebar that currently has focus (or its toggle is focused)
      if (event.key === "Escape") {
        if (primaryOpen && (primaryEl?.contains(active) || onPrimaryToggle)) {
          event.preventDefault();
          closePrimary();
          return;
        }
        if (
          secondaryOpen &&
          (secondaryEl?.contains(active) || onSecondaryToggle)
        ) {
          event.preventDefault();
          closeSecondary();
        }
      }
    };

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [primaryOpen, secondaryOpen]);

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
    primaryOpen ? "cb-editor--has-primary" : "cb-editor--no-primary"
  } ${secondaryOpen ? "cb-editor--has-secondary" : "cb-editor--no-secondary"}`;

  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <FullscreenMode isActive={isFullscreen} />
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
                saveStatus={saveStatus}
                primaryToggleRef={primaryToggleRef}
                secondaryToggleRef={secondaryToggleRef}
              />
            }
            /* CONTENT stays the same */
            content={<Content />}
            /* PRIMARY SIDEBAR — always render for width animation */
            sidebar={
              <aside
                className={`cb-editor__sidebar cb-editor__sidebar--primary ${
                  primaryOpen
                    ? "cb-editor__sidebar--open"
                    : "cb-editor__sidebar--closed"
                }`}
                id="cb-primary-sidebar"
                inert={primaryOpen ? undefined : "true"}
                aria-hidden={!primaryOpen}
                aria-label={__("Primary sidebar", "campaignbridge")}
              >
                <div className="cb-editor__sidebar-inner">
                  <div className="cb-editor__sidebar-chrome">
                    <Button
                      className="cb-editor__close-btn"
                      variant="tertiary"
                      onClick={closePrimary}
                      label={__("Close primary sidebar", "campaignbridge")}
                      icon="no"
                    />
                  </div>
                  <div className="cb-editor__sidebar-content">
                    <Sidebar />
                  </div>
                </div>
              </aside>
            }
            /* SECONDARY SIDEBAR — always render for width animation */
            secondarySidebar={
              <aside
                className={`cb-editor__sidebar cb-editor__sidebar--secondary ${
                  secondaryOpen
                    ? "cb-editor__sidebar--open"
                    : "cb-editor__sidebar--closed"
                }`}
                id="cb-secondary-sidebar"
                inert={secondaryOpen ? undefined : "true"}
                aria-hidden={!secondaryOpen}
                aria-label={__("Secondary sidebar", "campaignbridge")}
              >
                <div className="cb-editor__sidebar-inner">
                  <div className="cb-editor__sidebar-chrome">
                    <Button
                      className="cb-editor__close-btn"
                      variant="tertiary"
                      onClick={closeSecondary}
                      label={__("Close secondary sidebar", "campaignbridge")}
                      icon="no"
                    />
                  </div>
                  <div className="cb-editor__sidebar-content">
                    <SecondarySidebar />
                  </div>
                </div>
              </aside>
            }
            footer={<Footer />}
          />
        </BlockEditorProvider>

        <Popover.Slot />
      </SlotFillProvider>
    </ShortcutProvider>
  );
}
