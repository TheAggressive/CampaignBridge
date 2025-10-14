import { registerCoreBlocks } from "@wordpress/block-library";
import { Button, Icon, Notice, Spinner } from "@wordpress/components";
import domReady from "@wordpress/dom-ready";
import { createRoot, useState } from "@wordpress/element";
import React from "react";
import "@wordpress/format-library";
import { __ } from "@wordpress/i18n";
import { layout, plus } from "@wordpress/icons";
import { InterfaceSkeleton } from "@wordpress/interface";
import EditorChrome from "./components/EditorChrome";
import Header from "./components/Header";
import NewTemplateModal from "./components/NewTemplateModal";
import { useNewTemplate } from "./hooks/useNewTemplate";
import { useTemplateRouting } from "./hooks/useTemplateRouting";
import { useTemplates } from "./hooks/useTemplates";
import { registerCampaignBridgeBlocks } from "./utils/registerCampaignBridgeBlocks";

export const POST_TYPE = "cb_email_template";

// Type definitions
interface TemplateItem {
  id: number;
  title: string;
  [key: string]: any;
}

interface EmptyStateProps {
  list: TemplateItem[];
  loading: boolean;
  onSelect: (id: number | null) => void;
  onNew: () => void;
}

/**
 * Main application component for the CampaignBridge Template Editor.
 *
 * Manages the overall state of the template editor including:
 * - Loading and displaying list of available templates
 * - Handling template selection and creation
 * - Rendering the appropriate UI based on current state
 */
export default function CampaignBridgeBlockEditor(): JSX.Element {
  const [error, setError] = useState("");
  const {
    items: list,
    loading,
    error: loadError,
  } = useTemplates({ onError: setError });
  const {
    open: newModalOpen,
    title: newTitle,
    busy: creating,
    openModal,
    closeModal,
    setTitle: setNewTitle,
    confirmCreate,
  } = useNewTemplate({ onError: setError });

  const { currentId, selectTemplate } = useTemplateRouting();

  // If hook provided a load error, surface it via Notice too
  const effectiveError = error || loadError || "";

  /**
   * Handles template selection by updating the URL parameter and reloading the page.
   */
  const onSelect = (id: number | null): void => {
    if (id) {
      selectTemplate(id);
    }
  };

  /**
   * Opens the naming modal for creating a new template.
   */
  const onNew = openModal;

  return (
    <div className="cb-editor-shell">
      {effectiveError && (
        <Notice status="error" isDismissible={false}>
          {effectiveError}
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
          postType={POST_TYPE}
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
