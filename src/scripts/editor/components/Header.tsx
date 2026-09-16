import { Button } from '@wordpress/components';
import { time } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { FullscreenToggle } from './Button/FullscreenToggle';
import { PrimarySidebarToggle } from './Button/PrimarySidebarToggle';
import { SecondarySidebarToggle } from './Button/SecondarySidebarToggle';
import PreviewButton from './PreviewButton';
import TemplateToolbar from './TemplateToolbar';
import type { SaveStatus, TemplateSummary } from '../types';

/* CSS classes */
const CLASSES = {
  HEADER: 'cb-editor__header',
  HEADER_LEFT: 'cb-editor__header-left',
  HEADER_CENTER: 'cb-editor__header-center',
  HEADER_ACTIONS: 'cb-editor__header-actions',
};

/**
 * Header Component
 *
 * Main header component for the CampaignBridge template editor.
 * This component orchestrates the template toolbar and self-contained
 * toggle button components.
 *
 * Features:
 * - Template selection dropdown with search and creation
 * - Self-contained toggle buttons with internal state management
 * - Clean separation of concerns with modular architecture
 *
 * Architecture:
 * - Uses self-contained button components (each handles its own state)
 * - Minimal orchestration - just imports and renders components
 * - Each button component manages its own preferences and shortcuts
 * - Header focuses purely on layout and template functionality
 *
 * Keyboard Shortcuts (WordPress Native - handled by individual components):
 * - Primary Sidebar: Ctrl+Shift+, (comma)
 * - Secondary Sidebar: Shift+Alt+O
 * - Fullscreen: Ctrl+Shift+Alt+F
 *
 * @param {Object} props - Component props
 * @param {Array} props.list - Array of available templates for the dropdown
 * @param {number|null} props.currentId - ID of the currently selected template
 * @param {boolean} props.loading - Whether templates are currently loading
 * @param {function} props.onSelect - Callback fired when a template is selected
 * @param {function} props.onNew - Callback fired when creating a new template
 * @param {boolean} props.isPrimaryOpen - Whether the primary sidebar is open
 * @param {boolean} props.isSecondaryOpen - Whether the secondary sidebar is open
 * @param {function} props.togglePrimary - Function to toggle the primary sidebar
 * @param {function} props.toggleSecondary - Function to toggle the secondary sidebar
 * @returns {JSX.Element} The editor header with toolbar and controls
 *
 * @example
 * ```jsx
 * <Header
 *   list={templates}
 *   currentId={1}
 *   loading={false}
 *   onSelect={handleSelect}
 *   onNew={handleNew}
 *   isPrimaryOpen={true}
 *   isSecondaryOpen={false}
 *   togglePrimary={handleTogglePrimary}
 *   toggleSecondary={handleToggleSecondary}
 * />
 * ```
 */
interface HeaderProps {
  list: TemplateSummary[];
  currentId: number | null;
  loading: boolean;
  onSelect: (id: number | null) => void;
  onNew: () => void;
  isPrimaryOpen: boolean;
  isSecondaryOpen: boolean;
  togglePrimary: () => void;
  toggleSecondary: () => void;
  hasEdits?: boolean;
  onSave?: () => void | Promise<unknown>;
  onPublish?: () => void | Promise<unknown>;
  onDuplicate?: () => void | Promise<unknown>;
  status?: string;
  saveStatus?: SaveStatus;
  isAutosaving?: boolean;
  hasAutosaved?: boolean;
  onOpenPreview?: () => void;
  onOpenHistory?: () => void;
  /** A duplicate or revision restore is running. */
  isOperationPending?: boolean;
}

export default function Header({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
  isPrimaryOpen,
  isSecondaryOpen,
  togglePrimary,
  toggleSecondary,
  hasEdits = false,
  onSave = () => {},
  onPublish = () => {},
  onDuplicate = () => {},
  status,
  saveStatus = 'saved',
  isAutosaving = false,
  hasAutosaved = false,
  onOpenPreview = () => {},
  onOpenHistory = () => {},
  isOperationPending = false,
}: HeaderProps): JSX.Element {
  const isSaving = saveStatus === 'saving';
  // Competing template actions wait for a save, duplicate, or restore.
  const actionsLocked = isSaving || isAutosaving || isOperationPending;
  const isDraft = status === 'draft' || status === undefined;
  const saveLabel = isSaving
    ? isDraft
      ? __('Saving…', 'campaignbridge')
      : __('Updating…', 'campaignbridge')
    : hasEdits
      ? isDraft
        ? __('Save draft', 'campaignbridge')
        : __('Update', 'campaignbridge')
      : isDraft
        ? __('Saved', 'campaignbridge')
        : __('Updated', 'campaignbridge');
  const publishLabel = isSaving
    ? __('Publishing…', 'campaignbridge')
    : __('Publish', 'campaignbridge');

  return (
    <div
      className={CLASSES.HEADER}
      role='toolbar'
      aria-label={__('Email editor toolbar', 'campaignbridge')}
    >
      <div className={CLASSES.HEADER_LEFT}>
        <SecondarySidebarToggle
          isOpen={isSecondaryOpen}
          onToggle={toggleSecondary}
        />
      </div>

      <div className={CLASSES.HEADER_CENTER}>
        <TemplateToolbar
          list={list}
          currentId={currentId}
          loading={loading}
          onSelect={onSelect}
          onNew={onNew}
        />
        {status && (
          <span
            className={`cb-editor__status-badge cb-editor__status-badge--${
              status === 'publish' ? 'published' : 'draft'
            }`}
            role='status'
            aria-label={
              status === 'publish'
                ? __('Template is published', 'campaignbridge')
                : __('Template is a draft', 'campaignbridge')
            }
          >
            {status === 'publish'
              ? __('Published', 'campaignbridge')
              : __('Draft', 'campaignbridge')}
          </span>
        )}
        {(isAutosaving || hasAutosaved) && !isSaving && (
          <small
            className='cb-editor__autosave-status'
            role='status'
            title={__(
              'Autosave protects unfinished changes. Use the primary action to save the template.',
              'campaignbridge'
            )}
          >
            {isAutosaving
              ? __('Autosaving…', 'campaignbridge')
              : __('Autosaved', 'campaignbridge')}
          </small>
        )}
      </div>

      <div className={CLASSES.HEADER_ACTIONS}>
        {isDraft && (
          <Button
            className='cb-editor__publish-button'
            variant='primary'
            onClick={() => void onPublish()}
            disabled={actionsLocked}
            isBusy={isSaving}
            aria-label={publishLabel}
          >
            {publishLabel}
          </Button>
        )}
        <Button
          className='cb-editor__duplicate-button'
          variant='tertiary'
          onClick={() => void onDuplicate()}
          disabled={actionsLocked}
          aria-label={__('Duplicate template', 'campaignbridge')}
        >
          {__('Duplicate', 'campaignbridge')}
        </Button>
        <Button
          className='cb-editor__history-button'
          variant='tertiary'
          icon={time}
          onClick={onOpenHistory}
          disabled={actionsLocked}
          aria-label={__('View revision history', 'campaignbridge')}
        />
        <PreviewButton onClick={onOpenPreview} />
        <Button
          className='cb-editor__save-button'
          variant={isDraft ? 'secondary' : 'primary'}
          onClick={() => void onSave()}
          disabled={!hasEdits || actionsLocked}
          isBusy={isSaving}
          aria-label={saveLabel}
        >
          {saveLabel}
        </Button>
        <PrimarySidebarToggle isOpen={isPrimaryOpen} onToggle={togglePrimary} />
        <FullscreenToggle />
      </div>
    </div>
  );
}
