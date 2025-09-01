import { registerCoreBlocks } from "@wordpress/block-library";
import { Notice, Spinner } from "@wordpress/components";
import domReady from "@wordpress/dom-ready";
import { createRoot, useEffect, useState } from "@wordpress/element";
import "@wordpress/format-library";
import { __ } from "@wordpress/i18n";
import BlockCanvas from "./components/BlockCanvas";
import EditorChrome from "./components/EditorChrome";
import TemplateToolbar from "./components/TemplateToolbar";
import { createDraft, listTemplates } from "./services/api";
import { registerCustomBlocks } from "./utils/registerCustomBlocks";
import { getParam, setParamAndReload } from "./utils/url";

/**
 * Main application component for the CampaignBridge Template Editor.
 *
 * Manages the overall state of the template editor including:
 * - Loading and displaying list of available templates
 * - Handling template selection and creation
 * - Rendering the appropriate UI based on current state
 *
 * @returns {JSX.Element} The main editor application
 */
export default function App() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const currentId = getParam("post_id") ? Number(getParam("post_id")) : null;

  /**
   * Loads the list of available templates on component mount.
   * Uses cleanup flag to prevent state updates if component unmounts during async operation.
   */
  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const posts = await listTemplates();
        if (alive) setList(posts || []);
      } catch (e) {
        if (alive) setError(e?.message || "Failed to load templates.");
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  /**
   * Handles template selection by updating the URL parameter and reloading the page.
   * @param {number|null} id - The ID of the selected template, or null to deselect
   */
  const onSelect = (id) => (id ? setParamAndReload("post_id", id) : null);

  /**
   * Creates a new draft template and navigates to it.
   * Updates the URL parameter with the new template ID and reloads the page.
   */
  const onNew = async () => {
    const p = await createDraft();
    setParamAndReload("post_id", p.id || p);
  };

  return (
    <div className="cb-tm-shell">
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      <TemplateToolbar
        list={list}
        currentId={currentId}
        loading={loading}
        onSelect={onSelect}
        onNew={onNew}
      />
      {loading ? (
        <div className="cb-tm-loading">
          <Spinner />
        </div>
      ) : currentId ? (
        <EditorChrome>
          <BlockCanvas postId={currentId} />
        </EditorChrome>
      ) : (
        <EmptyState />
      )}
    </div>
  );
}

/**
 * Empty state component displayed when no template is selected.
 *
 * Shows instructions to the user to either select an existing template
 * or create a new one.
 *
 * @returns {JSX.Element} The empty state UI
 */
function EmptyState() {
  return (
    <div className="cb-editor-placeholder">
      <div className="cb-editor-placeholder-content">
        <h3>{__("Select a Template", "campaignbridge")}</h3>
        <p>
          {__(
            "Choose a template above, or create a new one.",
            "campaignbridge",
          )}
        </p>
      </div>
    </div>
  );
}

/**
 * Initializes the template editor application when the DOM is ready.
 * Finds the root element and renders the App component using React 18's createRoot API.
 */
domReady(() => {
  const root = document.getElementById("cb-template-editor-root");
  if (root) {
    // Use React 18 createRoot API instead of deprecated render
    const reactRoot = createRoot(root);
    registerCoreBlocks();
    registerCustomBlocks();
    reactRoot.render(<App />);
  }
});
