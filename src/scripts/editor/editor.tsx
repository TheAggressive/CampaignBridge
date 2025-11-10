import { registerCoreBlocks } from '@wordpress/block-library';
import { Button, Icon, Notice, Spinner } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot, useState } from '@wordpress/element';
import '@wordpress/format-library';
import { __ } from '@wordpress/i18n';
import { layout, plus } from '@wordpress/icons';
import { InterfaceSkeleton } from '@wordpress/interface';
import EditorChrome from './components/EditorChrome';
import Header from './components/Header';
import NewTemplateModal from './components/NewTemplateModal';
import { useNewTemplate } from './hooks/useNewTemplate';
import { useTemplateRouting } from './hooks/useTemplateRouting';
import { useTemplates } from './hooks/useTemplates';
import { registerCampaignBridgeBlocks } from './utils/registerCampaignBridgeBlocks';

/**
 * Post type identifier for CampaignBridge email templates.
 *
 * This constant defines the custom post type used to store email templates
 * in the WordPress database. All template operations should use this constant
 * to ensure consistency across the application.
 *
 * @constant {string}
 */
export const POST_TYPE = 'cb_templates';

// Type definitions

/**
 * Represents a template item with basic metadata.
 *
 * @interface TemplateItem
 * @property {number} id - Unique identifier for the template
 * @property {string} title - Display title of the template
 * @property {any} [key] - Additional properties that may be present
 */
interface TemplateItem {
  id: number;
  title: string;
  [key: string]: any;
}

/**
 * Props for the EmptyState component.
 *
 * @interface EmptyStateProps
 * @property {TemplateItem[]} list - Array of available templates
 * @property {boolean} loading - Whether templates are currently loading
 * @property {(id: number | null) => void} onSelect - Callback when a template is selected
 * @property {() => void} onNew - Callback when creating a new template
 */
interface EmptyStateProps {
  list: TemplateItem[];
  loading: boolean;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
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
  const [error, setError] = useState('');
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
  const effectiveError = error || loadError || '';

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
    <div className='cb-editor-shell'>
      {effectiveError && (
        <Notice status='error' isDismissible={false}>
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
        <div className='cb-block-editor-loading'>
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
 * or create a new one. Provides a clean starting point for template creation.
 *
 * @param {EmptyStateProps} props - Component props
 * @param {TemplateItem[]} props.list - Array of available templates
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {(id: number | null) => void} props.onSelect - Callback when a template is selected
 * @param {() => void} props.onNew - Callback when creating a new template
 * @returns {JSX.Element} The empty state component
 */
function EmptyState({
  list,
  loading,
  onSelect,
  onNew,
}: EmptyStateProps): JSX.Element {
  return (
    <InterfaceSkeleton
      header={
        <Header
          list={list}
          currentId={null}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
          isPrimaryOpen={false}
          isSecondaryOpen={false}
          togglePrimary={() => {}}
          toggleSecondary={() => {}}
        />
      }
      content={
        <div
          className='cb-editor__empty'
          role='region'
          aria-label={__('No template selected', 'campaignbridge')}
        >
          <div className='cb-editor__empty-card'>
            <div className='cb-editor__empty-icon' aria-hidden>
              <Icon icon={layout} size={32} />
            </div>
            <h3 className='cb-editor__empty-title'>
              {__('Get Started', 'campaignbridge')}
            </h3>
            <p className='cb-editor__empty-desc'>
              {__(
                'Choose an existing template above, or create a new one to start designing.',
                'campaignbridge'
              )}
            </p>
            <div className='cb-editor__empty-actions'>
              <Button
                variant='primary'
                className='cb-editor__empty-primary'
                onClick={onNew}
              >
                <Icon icon={plus} size={20} />{' '}
                {__('New Template', 'campaignbridge')}
              </Button>
            </div>
          </div>
        </div>
      }
    />
  );
}

/**
 * Initialize the CampaignBridge template editor application.
 *
 * This function runs when the DOM is ready and sets up the React application
 * by finding the root DOM element and rendering the main editor component.
 * It also registers all necessary WordPress blocks before rendering.
 *
 * The application uses React 18's createRoot API for modern React rendering.
 * Core WordPress blocks and custom CampaignBridge blocks are registered
 * to ensure all block types are available in the editor.
 *
 * @function initializeEditor
 * @returns {void}
 */
domReady(() => {
  // Find the root DOM element for the editor
  const root = document.getElementById('cb-block-editor-root');

  if (root) {
    // Create React 18 root and render the main application
    const reactRoot = createRoot(root);

    // Register all necessary blocks before rendering
    registerCoreBlocks();
    registerCampaignBridgeBlocks();

    // Render the main editor component
    reactRoot.render(<CampaignBridgeBlockEditor />);
  }
});
