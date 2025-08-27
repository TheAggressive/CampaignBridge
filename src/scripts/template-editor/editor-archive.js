/**
 * WordPress dependencies
 */
import apiFetch from "@wordpress/api-fetch";
import {
  BlockEditorKeyboardShortcuts,
  BlockEditorProvider,
  BlockInspector,
  BlockList,
  BlockTools,
  ObserveTyping,
  WritingFlow,
} from "@wordpress/block-editor";
import { parse, serialize } from "@wordpress/blocks";
import {
  Button,
  Notice,
  Popover,
  SelectControl,
  SlotFillProvider,
  Spinner,
} from "@wordpress/components";
import { dispatch, select, useDispatch, useSelect } from "@wordpress/data";
import domReady from "@wordpress/dom-ready";
import {
  createRoot,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "@wordpress/element";
import "@wordpress/format-library";
import { __ } from "@wordpress/i18n";
import { ShortcutProvider } from "@wordpress/keyboard-shortcuts";

/**
 * Configuration and constants
 */
const CFG = window.CB_TM || {};
const CPT = CFG.postType || "cb_email_template";

/**
 * Utility functions
 */
const getParam = (k) => new URLSearchParams(window.location.search).get(k);

const setParamAndReload = (k, v) => {
  const url = new URL(window.location.href);
  if (v == null) url.searchParams.delete(k);
  else url.searchParams.set(k, String(v));
  window.location.replace(url.toString());
};

/**
 * API setup
 */
if (CFG.nonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(CFG.nonce));
}
if (CFG.apiRoot) {
  apiFetch.use(apiFetch.createRootURLMiddleware(CFG.apiRoot));
}

async function createDraft() {
  const rec = await dispatch("core").saveEntityRecord("postType", CPT, {
    status: "draft",
    title: CFG.defaultTitle || "Untitled",
  });
  return rec?.id;
}

async function fetchTemplates() {
  const cached = select("core").getEntityRecords("postType", CPT, {
    per_page: 100,
    _fields: ["id", "title", "status", "date"],
  });

  if (Array.isArray(cached) && cached.length > 0) {
    return cached;
  }

  try {
    const result = await apiFetch({
      path: `/wp/v2/${CPT}?per_page=100&_fields=id,title,status,date`,
    });
    return result;
  } catch (error) {
    throw error;
  }
}

function BlockEditor({ postId, onBlocksChange }) {
  const [blocks, setBlocks] = useState([]);

  const [isReady, setIsReady] = useState(false);

  const { settings } = useSelect((select) => {
    try {
      return {
        settings: select("core/block-editor").getSettings(),
      };
    } catch (error) {
      console.warn("Block editor store not available:", error);
      return {
        settings: {},
      };
    }
  }, []);

  const { updateSettings } = useDispatch("core/block-editor");

  // Safety check for dispatch availability
  const safeUpdateSettings = useCallback(
    (newSettings) => {
      try {
        if (updateSettings) {
          updateSettings(newSettings);
        }
      } catch (error) {
        console.warn("Failed to update block editor settings:", error);
      }
    },
    [updateSettings],
  );

  // Initialize when settings are available
  useEffect(() => {
    if (settings && Object.keys(settings).length > 0) {
      setIsReady(true);
    }
  }, [settings]);

  // Load blocks for the current post
  useEffect(() => {
    if (!postId) {
      return;
    }

    const loadBlocks = async () => {
      const post = await apiFetch({
        path: `/wp/v2/${CPT}/${postId}?context=edit&_embed`,
      });
      if (post.content && post.content.raw) {
        // Parse blocks from post content with a delay to ensure blocks are registered
        const parsedBlocks = parse(post.content.raw);

        console.log(post.content.raw);

        console.log(parsedBlocks);

        if (parsedBlocks && parsedBlocks.length > 0) {
          setBlocks(parsedBlocks);
        }
      }
    };

    loadBlocks();
  }, [postId]);

  // Save blocks when they change
  const saveBlocks = useCallback(
    async (newBlocks) => {
      if (!postId) return;

      try {
        const content = serialize(newBlocks);
        await apiFetch({
          path: `/wp/v2/${CPT}/${postId}`,
          method: "POST",
          data: {
            content: content,
            status: "draft",
          },
        });
        setBlocks(newBlocks);
        onBlocksChange && onBlocksChange(newBlocks);
      } catch (e) {
        console.error("Failed to save blocks:", e);
      }
    },
    [postId, onBlocksChange],
  );

  const editorSettings = useMemo(
    () => ({
      ...settings,
      hasInlineToolbar: true,
      focusMode: false,
      hasFixedToolbar: true,
      // Block settings
      allowedBlockTypes: true, // Allow all block types
      // Rich text settings
      richEditingEnabled: true,
      // Media settings
      mediaUpload: CFG.mediaUpload || null,
      // Enable native block inserter
      inserter: true,
      __experimentalBlockPatterns: [],
      __experimentalBlockPatternCategories: [],
      // Disable certain features that require WordPress admin context
      __experimentalDisableCustomColors: false,
      __experimentalDisableCustomGradients: false,
      __experimentalDisableCustomSpacing: false,
      __experimentalFeatures: {
        color: {
          custom: true,
          theme: true,
        },
        spacing: {
          custom: true,
          theme: true,
        },
        typography: {
          customFontSize: true,
          customLineHeight: true,
          fontStyle: true,
          fontWeight: true,
          letterSpacing: true,
          textDecoration: true,
          textTransform: true,
        },
      },
    }),
    [settings],
  );

  // Show loading state while editor is initializing
  if (!isReady || !settings || Object.keys(settings).length === 0) {
    return (
      <div className="cb-block-editor">
        <div className="cb-editor-loading">
          <Spinner />
          <p>{__("Initializing editor...", "campaignbridge")}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="cb-block-editor">
      <ShortcutProvider>
        <SlotFillProvider>
          <div className="cb-tm-layout">
            <div className="cb-tm-main">
              <BlockEditorProvider
                value={blocks}
                onChange={saveBlocks}
                settings={editorSettings}
              >
                <BlockEditorKeyboardShortcuts.Register />
                <BlockTools>
                  <WritingFlow>
                    <ObserveTyping>
                      <BlockList />
                    </ObserveTyping>
                  </WritingFlow>
                </BlockTools>

                <aside className="cb-tm-sidebar">
                  <BlockInspector />
                </aside>
                <Popover.Slot />
              </BlockEditorProvider>
            </div>
          </div>
        </SlotFillProvider>
      </ShortcutProvider>
    </div>
  );
}

function App() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  // Debug current ID resolution - calculate on every render
  const cfgPostId = CFG.currentPostId;
  const urlPostId = getParam("post_id");

  const currentId = Number(urlPostId || cfgPostId) || null;

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const posts = await fetchTemplates();
        if (!alive) return;
        // Ensure we have an array and filter out any invalid entries
        const validPosts = Array.isArray(posts)
          ? posts.filter((p) => p && typeof p === "object" && p.id)
          : [];
        setList(validPosts);
      } catch (e) {
        console.error("Template loading error:", e);
        setError(e?.message || "Failed to load templates.");
        setList([]); // Ensure list is always an array
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => (alive = false);
  }, []);

  // No automatic draft creation on page load
  // User must explicitly select a template or click "New Template"

  const onSelect = (val) => {
    const id = Number(val);

    // Ignore empty selections (like "Please select a template")
    if (val === "" || !id) return;

    setParamAndReload("post_id", id);
  };
  const onNew = async () => {
    const id = await createDraft();
    setParamAndReload("post_id", id);
  };

  return (
    <div className="cb-tm-shell">
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      <div className="cb-tm-toolbar">
        <SelectControl
          label={__("Templates", "campaignbridge")}
          value={currentId ? String(currentId) : ""}
          onChange={onSelect}
          disabled={loading && list.length === 0}
          __next40pxDefaultSize={true}
          __nextHasNoMarginBottom={true}
          options={[
            // Default option when no template is selected
            ...(currentId === null && !loading
              ? [
                  {
                    label: __("Please select a template", "campaignbridge"),
                    value: "",
                  },
                ]
              : []),
            // Loading option
            ...(loading
              ? [{ label: __("Loading…", "campaignbridge"), value: "" }]
              : []),
            // Template options
            ...list
              .filter((p) => p && typeof p === "object" && p.id)
              .map((p) => ({
                label: p?.title?.rendered || `#${p.id}`,
                value: String(p.id),
              }))
              .filter((option) => option.label && option.value !== undefined),
          ]}
        />
        <Button variant="primary" onClick={onNew}>
          {__("New Template", "campaignbridge")}
        </Button>
      </div>
      {loading && (
        <div className="cb-tm-loading">
          <Spinner />
        </div>
      )}
      {currentId ? (
        <BlockEditor
          postId={currentId}
          onBlocksChange={(blocks) => {
            // Handle block changes if needed
          }}
        />
      ) : (
        <div className="cb-editor-placeholder">
          <div className="cb-editor-placeholder-content">
            <h3>{__("Select a Template", "campaignbridge")}</h3>
            <p>
              {__(
                "Choose a template from the dropdown above to start editing, or create a new one.",
                "campaignbridge",
              )}
            </p>
          </div>
        </div>
      )}
    </div>
  );
}

domReady(() => {
  const root = document.getElementById("cb-template-manager-root");
  if (root) {
    // Use React 18 createRoot API instead of deprecated render
    const reactRoot = createRoot(root);
    reactRoot.render(<App />);
  }
});
