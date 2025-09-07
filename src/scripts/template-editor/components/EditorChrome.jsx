import { BlockEditorProvider } from "@wordpress/block-editor";
import { parse } from "@wordpress/blocks";
import { Popover, SlotFillProvider } from "@wordpress/components";
import { useSelect } from "@wordpress/data";
import { useEffect, useMemo, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { FullscreenMode, InterfaceSkeleton } from "@wordpress/interface";
import { ShortcutProvider } from "@wordpress/keyboard-shortcuts";
import { getPostRaw, savePostContent } from "../services/api";
import { blockPatternCategories, blockPatterns } from "../utils/blockPatterns";
import { serializeSafe } from "../utils/blocks";
import Content from "./Content";
import Footer from "./Footer";
import Header from "./Header";
import Sidebar from "./Sidebar";

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
}) {
  const [ready, setReady] = useState(false);
  const [blocks, setBlocks] = useState([]);
  const [saveStatus, setSaveStatus] = useState("saved"); // 'saved', 'saving', 'error'

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

  /**
   * Enhanced save function with status tracking and auto-save functionality.
   * Includes debounced saving, status updates, and error handling.
   */
  const save = useMemo(() => {
    let debounceTimer;
    let autoSaveTimer;

    const performSave = async (blocksToSave, isAutoSave = false) => {
      try {
        setSaveStatus(isAutoSave ? "autosaving" : "saving");

        const result = await savePostContent(postId, {
          content: serializeSafe(blocksToSave),
          isAutoSave,
        });

        setSaveStatus("saved");

        onBlocksChange && onBlocksChange(blocksToSave);

        return result;
      } catch (error) {
        console.error("Save failed:", error);
        setSaveStatus("error");
        throw error;
      }
    };

    // Debounced manual save (for user actions)
    const debouncedSave = (next) => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => performSave(next, false), 600);
    };

    // Auto-save every 60 seconds (WordPress standard)
    const startAutoSave = (next) => {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(() => {
        performSave(next, true);
        startAutoSave(next); // Restart the cycle
      }, 60000); // 60 seconds
    };

    // Return the main save function
    return (next, force = false) => {
      if (force) {
        // Immediate save for critical operations
        clearTimeout(debounceTimer);
        clearTimeout(autoSaveTimer);
        return performSave(next, false);
      }

      debouncedSave(next);
      startAutoSave(next);
    };
  }, [postId, onBlocksChange]);

  /**
   * Block editor configuration settings.
   * Defines the editor behavior including toolbar settings, focus mode, and media upload handling.
   */
  const editorSettings = useMemo(
    () => ({
      hasInlineToolbar: true,
      focusMode: false,
      hasFixedToolbar: false,
      inserter: true,
      richEditingEnabled: true,
      allowedBlockTypes: true,
      mediaUpload: window.wp.media || null,
      __experimentalBlockPatterns: blockPatterns,
      __experimentalBlockPatternCategories: blockPatternCategories,
    }),
    [],
  );

  const isFullscreen = useSelect(
    (select) =>
      select("core/preferences").get("core/edit-post", "fullscreenMode"),
    [],
  );

  if (!ready) {
    return (
      <div className="cb-editor-loading">
        <p>{__("Initializing editor…", "campaignbridge")}</p>
      </div>
    );
  }

  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <FullscreenMode isActive={isFullscreen} />
        <BlockEditorProvider
          value={blocks}
          onChange={
            /**
             * Handles block content changes by updating local state and triggering save.
             * Changes are tracked by our custom history system for undo/redo functionality.
             * @param {Array} n - The new array of block objects
             */
            (n) => {
              setBlocks(n);
              save(n);
            }
          }
          settings={editorSettings}
        >
          <InterfaceSkeleton
            header={
              <Header
                list={list}
                currentId={currentId}
                loading={loading}
                onSelect={onSelect}
                onNew={onNew}
                saveStatus={saveStatus}
              />
            }
            sidebar={<Sidebar />}
            content={<Content />}
            footer={<Footer />}
          />
        </BlockEditorProvider>

        <Popover.Slot />
      </SlotFillProvider>
    </ShortcutProvider>
  );
}
