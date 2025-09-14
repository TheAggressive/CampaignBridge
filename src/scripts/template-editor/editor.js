import { registerCoreBlocks } from "@wordpress/block-library";
import { Button, Icon, Notice, Spinner } from "@wordpress/components";
import domReady from "@wordpress/dom-ready";
import { createRoot, useEffect, useState } from "@wordpress/element";
import "@wordpress/format-library";
import { __ } from "@wordpress/i18n";
import { layout, plus } from "@wordpress/icons";
import { InterfaceSkeleton } from "@wordpress/interface";
import EditorChrome from "./components/EditorChrome";
import Header from "./components/Header";
import NewTemplateModal from "./components/NewTemplateModal";
import { listTemplates } from "./services/api";
import { registerCampaignBridgeBlocks } from "./utils/registerCampaignBridgeBlocks";
import { getParam, setParamAndReload } from "./utils/url";
import { useNewTemplate } from "./utils/useNewTemplate";

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
  const {
    open: newModalOpen,
    title: newTitle,
    busy: creating,
    openModal,
    closeModal,
    setTitle: setNewTitle,
    confirmCreate,
  } = useNewTemplate({ onError: setError });

  const currentId = getParam("post_id") ? Number(getParam("post_id")) : null;

  // Load the list of available templates on component mount
  // Uses cleanup flag to prevent state updates if component unmounts during async operation
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
   * Opens the naming modal for creating a new template.
   */
  const onNew = openModal;

  return (
    <div className="cb-editor-shell">
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}
      <NewTemplateModal
        open={newModalOpen}
        title={newTitle}
        onChangeTitle={setNewTitle}
        onCancel={closeModal}
        onConfirm={confirmCreate}
        busy={creating}
      />
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
        <Header
          list={list}
          currentId={null}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
      }
      content={
        <div
          className="cb-editor__empty"
          role="region"
          aria-label={__("No template selected", "campaignbridge")}
        >
          <div className="cb-editor__empty-card">
            <div className="cb-editor__empty-icon" aria-hidden>
              <Icon icon={layout} size={32} />
            </div>
            <h3 className="cb-editor__empty-title">
              {__("Get Started", "campaignbridge")}
            </h3>
            <p className="cb-editor__empty-desc">
              {__(
                "Choose an existing template above, or create a new one to start designing.",
                "campaignbridge",
              )}
            </p>
            <div className="cb-editor__empty-actions">
              <Button
                variant="primary"
                className="cb-editor__empty-primary"
                onClick={onNew}
              >
                <Icon icon={plus} size={20} />{" "}
                {__("New Template", "campaignbridge")}
              </Button>
            </div>
          </div>
        </div>
      }
    />
  );
}

// Initialize the template editor application when the DOM is ready
// Find the root element and render the App component using React 18's createRoot API
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
