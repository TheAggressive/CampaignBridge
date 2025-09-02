import {
  BlockEditorProvider,
  BlockInspector,
  BlockTools,
  Inserter,
  BlockCanvas as WordPressBlockCanvas,
} from "@wordpress/block-editor";
import { parse } from "@wordpress/blocks";
import { Spinner } from "@wordpress/components";
import { useEffect, useMemo, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { getPostRaw, savePostContent } from "../services/api";
import { serializeSafe } from "../utils/blocks";

/**
 * Block canvas component that provides the WordPress block editor interface.
 *
 * Handles loading and rendering of block content for a specific post/template,
 * provides debounced auto-saving functionality, and manages the block editor
 * provider with appropriate settings.
 *
 * @param {Object} props - Component props
 * @param {number} props.postId - The ID of the post/template to load and edit
 * @param {function} [props.onBlocksChange] - Callback fired when blocks change
 * @returns {JSX.Element} The block editor canvas
 */
export default function BlockCanvas({ postId, onBlocksChange }) {
  const [ready, setReady] = useState(false);
  const [blocks, setBlocks] = useState([]);

  /**
   * Loads the post content and initializes the block editor.
   * Waits for block types to be registered, fetches the post data,
   * parses the content, and sets the component as ready.
   */
  useEffect(() => {
    let alive = true;
    (async () => {
      if (!postId) return;
      const post = await getPostRaw(postId);
      console.log("post", post?.content?.raw);
      if (!alive) return;
      const blocks = parse(post?.content?.raw || "");
      console.log("blocks", blocks);
      setBlocks(blocks);
      setReady(true);
    })();
    return () => {
      alive = false;
    };
  }, [postId]);

  /**
   * Creates a debounced save function that serializes blocks and saves them to the server.
   * Waits 600ms after the last change before saving to avoid excessive API calls.
   * Also triggers the onBlocksChange callback if provided.
   */
  const save = useMemo(() => {
    let t;
    return (next) => {
      clearTimeout(t);
      t = setTimeout(async () => {
        await savePostContent(postId, { content: serializeSafe(next) });
        onBlocksChange && onBlocksChange(next);
      }, 600);
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
      hasFixedToolbar: true,
      inserter: true,
      richEditingEnabled: true,
      mediaUpload: window.wp.media || null,
      __experimentalBlockPatterns: [],
      __experimentalBlockPatternCategories: [],
    }),
    [],
  );

  if (!ready) {
    return (
      <div className="cb-block-editor">
        <div className="cb-editor-loading">
          <Spinner />
          <p>{__("Initializing editor…", "campaignbridge")}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="cb-block-editor">
      <BlockEditorProvider
        value={blocks}
        onChange={
          /**
           * Handles block content changes by updating local state and triggering save.
           * @param {Array} n - The new array of block objects
           */
          (n) => {
            setBlocks(n);
            save(n);
          }
        }
        settings={editorSettings}
      >
        <BlockTools>
          <div className="cb-template-editor-inserter">
            <Inserter rootClientId={null} />
          </div>
          <WordPressBlockCanvas height="400px" />
        </BlockTools>
        <aside className="cb-template-editor-sidebar">
          <BlockInspector />
        </aside>
      </BlockEditorProvider>
    </div>
  );
}
