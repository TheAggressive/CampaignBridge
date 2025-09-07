import { registerCoreBlocks } from "@wordpress/block-library";
import { Notice, Spinner } from "@wordpress/components";
import domReady from "@wordpress/dom-ready";
import { createRoot, useEffect, useState } from "@wordpress/element";
import "@wordpress/format-library";
import { __ } from "@wordpress/i18n";
import { InterfaceSkeleton } from "@wordpress/interface";
import EditorChrome from "./components/EditorChrome";
import Header from "./components/Header";
import { createDraft, listTemplates } from "./services/api";
import { registerCampaignBridgeBlocks } from "./utils/registerCampaignBridgeBlocks";
import { getParam, setParamAndReload } from "./utils/url";

/**
 * Main application component for the CampaignBridge Template Editor.
 *
 * Manages the overall state of the template editor including:
 * - Loading and displaying list of available templates
 * - Handling template selection and creation
 * - Rendering the appropriate UI based on current state
 *
 * @return {JSX.Element} The main editor application
 */
export default function CampaignBridgeBlockEditor() {
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
        if (alive) {
          setList(posts || []);
        }
      } catch (e) {
        if (alive) {
          setError(e?.message || "Failed to load templates.");
        }
      } finally {
        if (alive) {
          setLoading(false);
        }
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
    <div className="cb-block-editor-shell">
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      {loading ? (
        <div className="cb-block-editor-loading">
          <Spinner />
        </div>
      ) : currentId ? (
        <EditorChrome
          list={list}
          currentId={currentId}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
          postId={currentId}
        />
      ) : (
        <EmptyState
          list={list}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
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
 * @param {Object} props - Component props
 * @param {Array} props.list - Array of available templates
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {function} props.onSelect - Callback fired when a template is selected
 * @param {function} props.onNew - Callback fired when creating a new template
 * @return {JSX.Element} The empty state UI
 */
function EmptyState({ list, loading, onSelect, onNew }) {
  return (
    <InterfaceSkeleton
      header={
        <>
          <Header
            list={list}
            currentId={null}
            loading={loading}
            onSelect={onSelect}
            onNew={onNew}
          />
        </>
      }
      content={
        <div className="cb-block-editor-placeholder-content">
          <h3>{__("Select a Template", "campaignbridge")}</h3>
          <p>
            {__(
              "Choose a template from the dropdown above, or create a new one.",
              "campaignbridge",
            )}
          </p>
        </div>
      }
    />
  );
}

/**
 * Initializes the template editor application when the DOM is ready.
 * Finds the root element and renders the App component using React 18's createRoot API.
 */
domReady(() => {
  const root = document.getElementById("cb-block-editor-root");
  if (root) {
    // Use React 18 createRoot API instead of deprecated render
    const reactRoot = createRoot(root);
    registerCoreBlocks();
    registerCampaignBridgeBlocks();
    reactRoot.render(<CampaignBridgeBlockEditor />);
  }
});
