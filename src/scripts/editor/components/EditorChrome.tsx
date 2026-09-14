import { BlockEditorProvider } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { Popover, SlotFillProvider, SnackbarList } from '@wordpress/components';
import { EntityProvider, useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect, useCallback, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  ComplementaryArea,
  FullscreenMode,
  InterfaceSkeleton,
} from '@wordpress/interface';
import { ShortcutProvider } from '@wordpress/keyboard-shortcuts';
import { LAYOUT_CONSTANTS, useEditorLayout } from '../hooks/useEditorLayout';
import { useEditorSettings } from '../hooks/useEditorSettings';
import { useNotices } from '../hooks/useNotices';
import { SIDEBAR_CONSTANTS, useSidebarState } from '../hooks/useSidebarState';
import {
  EDITOR_NOTICE_IDS,
  editorMessages,
  useTemplateEditor,
} from '../hooks/useTemplateEditor';
import { useEmailPreview } from '../hooks/useEmailPreview';
import { blockPatternCategories, blockPatterns } from '../utils/blockPatterns';
import Content from './Content';
import EditorEffects from './EditorEffects';
import EmailPreviewModal from './EmailPreviewModal';
import {
  ErrorState,
  LoadingState,
  type EditorStateAction,
} from './EditorStates';
import Footer from './Footer';
import Header from './Header';
import RevisionHistory from './RevisionHistory';
import SecondarySidebar from './Sidebars/SecondarySidebar';
import { SidebarContent, SidebarHeader } from './Sidebars/Sidebar';
import type { TemplateSummary } from '../types';

interface EditorChromeProps {
  list: TemplateSummary[];
  currentId: number;
  loading: boolean;

  onSelect: (id: number | null) => void;
  onNew: () => void;
  postId: number;
  postType?: string;
  /** Server-provided meta keys a duplicate copies. */
  duplicableMetaKeys?: readonly string[] | null;
}

/**
 * Provide the current template entity before any child binds to entity props.
 */
export default function EditorChrome({
  postId,
  postType = 'post',
  ...props
}: EditorChromeProps): JSX.Element {
  return (
    <EntityProvider kind='postType' type={postType} id={postId}>
      <EditorChromeContent postId={postId} postType={postType} {...props} />
    </EntityProvider>
  );
}

/**
 * Compose WordPress's public standalone block-editor primitives around the
 * CampaignBridge email block grammar.
 */
function EditorChromeContent({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
  postId,
  postType = 'post',
  duplicableMetaKeys,
}: EditorChromeProps): JSX.Element {
  const { success, error: errorNotice } = useNotices();
  const {
    blocks,
    duplicate,
    hasEdits,
    isOperationPending,
    isResolving,
    loadError,
    needsReload,
    onChange,
    onInput,
    publish,
    record,
    restoreRevision,
    saveNow,
    saveStatus,
  } = useTemplateEditor({
    postId,
    postType,
    duplicableMetaKeys,
    onSuccess: success,
    onError: errorNotice,
  });

  const handleDuplicate = useCallback(async () => {
    const result = await duplicate();
    if (result.success && result.id) {
      // Navigate only after exactly one copy was created.
      onSelect(result.id);
    } else if (result.error) {
      errorNotice(result.error, { id: EDITOR_NOTICE_IDS.duplicate });
    }
  }, [duplicate, errorNotice, onSelect]);

  const {
    settings: editorSettings,
    error: editorSettingsError,
    loading: editorSettingsLoading,
  } = useEditorSettings(postType, postId);
  const isFullscreen = useSelect(
    select =>
      (
        select('core/preferences') as unknown as {
          get: (scope: string, key: string) => unknown;
        }
      ).get(
        SIDEBAR_CONSTANTS.PREFERENCES.SCOPE,
        SIDEBAR_CONSTANTS.PREFERENCES.FULLSCREEN_MODE
      ) as boolean,
    []
  );

  const {
    isPrimaryOpen,
    isSecondaryOpen,
    openPrimary,
    togglePrimary,
    toggleSecondary,
  } = useSidebarState();
  const {
    skeletonClassName,
    sidebarActiveTab,
    setSidebarActiveTab,
    primarySidebarProps,
    snackbarNotices,
    removeNotice,
  } = useEditorLayout({ isPrimaryOpen, isSecondaryOpen });

  const [previewOpen, setPreviewOpen] = useState(false);
  const handleOpenPreview = useCallback(() => {
    setPreviewOpen(true);
  }, []);
  const handleClosePreview = useCallback(() => {
    setPreviewOpen(false);
  }, []);

  const [revisionOpen, setRevisionOpen] = useState(false);
  const handleOpenHistory = useCallback(() => {
    setRevisionOpen(true);
  }, []);
  const handleRevisionClose = useCallback(() => {
    setRevisionOpen(false);
  }, []);

  const handleBlockSelected = useCallback(() => {
    setSidebarActiveTab(SIDEBAR_CONSTANTS.TABS.INSPECTOR);
    openPrimary();
  }, [openPrimary, setSidebarActiveTab]);
  const handleTemplateSelect = useCallback(
    (id: number | null) => {
      if (!id || id === postId) {
        return;
      }

      void saveNow().then(saved => {
        if (saved) {
          onSelect(id);
        }
      });
    },
    [onSelect, postId, saveNow]
  );

  const recoveryActions = useMemo((): EditorStateAction[] => {
    const templateList = new URL(window.location.href);
    templateList.searchParams.delete('post_id');

    return [
      {
        label: __('Try again', 'campaignbridge'),
        variant: 'primary',
        onClick: () => window.location.reload(),
      },
      {
        label: __('Back to templates', 'campaignbridge'),
        variant: 'secondary',
        href: templateList.toString(),
      },
    ];
  }, []);

  // The server restored a revision the editor could not load. Show no stale
  // content that could be saved over the restore; only a reload continues.
  if (needsReload) {
    return (
      <ErrorState
        message={editorMessages.restoreRefreshFailed()}
        actions={[
          {
            label: __('Reload editor', 'campaignbridge'),
            variant: 'primary',
            onClick: () => window.location.reload(),
          },
        ]}
      />
    );
  }

  if (isResolving) {
    return (
      <LoadingState message={__('Initializing editor…', 'campaignbridge')} />
    );
  }

  if (loadError || !record) {
    return (
      <ErrorState
        message={editorMessages.loadFailed()}
        actions={recoveryActions}
      />
    );
  }

  if (editorSettingsLoading) {
    return (
      <LoadingState
        message={__('Loading editor settings…', 'campaignbridge')}
      />
    );
  }

  if (editorSettingsError) {
    return (
      <ErrorState
        message={__(
          'Editor settings could not be loaded. Please try again.',
          'campaignbridge'
        )}
        actions={recoveryActions}
      />
    );
  }

  const allowedBlockTypes = editorSettings.allowedBlockTypes || [];
  const missingBlockTypes = allowedBlockTypes.filter(
    blockName => !getBlockType(blockName)
  );

  if (allowedBlockTypes.length === 0 || missingBlockTypes.length > 0) {
    return (
      <ErrorState
        message={__(
          'The compiler-supported email block catalog could not be loaded.',
          'campaignbridge'
        )}
      />
    );
  }

  const mergedEditorSettings = {
    ...editorSettings,
    allowedBlockTypes,
    __experimentalBlockPatterns: blockPatterns,
    __experimentalBlockPatternCategories: blockPatternCategories,
  };
  const editorStyles = Array.isArray(editorSettings.styles)
    ? editorSettings.styles
    : [];

  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <FullscreenMode isActive={isFullscreen} />
        <ComplementaryArea
          {...primarySidebarProps}
          header={
            <SidebarHeader
              activeTab={sidebarActiveTab}
              onTabChange={setSidebarActiveTab}
            />
          }
        >
          <div className={LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_CONTENT}>
            <SidebarContent
              activeTab={sidebarActiveTab}
              postType={postType}
              postId={postId}
            />
          </div>
        </ComplementaryArea>

        <div className='cb-editor__viewport'>
          <BlockEditorProvider
            value={blocks}
            onInput={onInput}
            onChange={onChange}
            settings={mergedEditorSettings}
          >
            <EditorEffects
              saveStatus={saveStatus}
              onBlockSelected={handleBlockSelected}
            />
            <PreviewController
              postId={postId}
              postType={postType}
              isOpen={previewOpen}
              onRequestClose={handleClosePreview}
              title={list.find(t => t.id === currentId)?.title || undefined}
              hasEdits={hasEdits}
            />
            <RevisionHistory
              postId={postId}
              postType={postType}
              isOpen={revisionOpen}
              onRequestClose={handleRevisionClose}
              onRestore={restoreRevision}
              hasEdits={hasEdits}
            />
            <InterfaceSkeleton
              className={skeletonClassName}
              header={
                <Header
                  list={list}
                  currentId={currentId}
                  loading={
                    loading || saveStatus === 'saving' || isOperationPending
                  }
                  isOperationPending={isOperationPending}
                  onSelect={handleTemplateSelect}
                  onNew={onNew}
                  isPrimaryOpen={isPrimaryOpen}
                  isSecondaryOpen={isSecondaryOpen}
                  togglePrimary={togglePrimary}
                  toggleSecondary={toggleSecondary}
                  hasEdits={hasEdits}
                  onSave={saveNow}
                  onPublish={publish}
                  onDuplicate={handleDuplicate}
                  status={record?.status}
                  saveStatus={saveStatus}
                  onOpenPreview={handleOpenPreview}
                  onOpenHistory={handleOpenHistory}
                />
              }
              content={<Content onSave={saveNow} styles={editorStyles} />}
              sidebar={<ComplementaryArea.Slot {...primarySidebarProps} />}
              secondarySidebar={
                isSecondaryOpen ? (
                  <SecondarySidebar onClose={toggleSecondary} />
                ) : null
              }
              labels={{
                secondarySidebar: __('List view', 'campaignbridge'),
              }}
              footer={<Footer />}
            />
          </BlockEditorProvider>
        </div>

        <Popover.Slot />
        <div className={LAYOUT_CONSTANTS.CSS_CLASSES.EDITOR_SNACKBAR}>
          <SnackbarList
            notices={snackbarNotices as any}
            onRemove={removeNotice}
          />
        </div>
      </SlotFillProvider>
    </ShortcutProvider>
  );
}

/**
 * Renders the email preview modal inside the BlockEditorProvider's scoped
 * registry so that useEmailPreview reads blocks from the correct store.
 *
 * Without this, the hook would read from the parent registry's block editor
 * store, which is a different instance than the one BlockEditorProvider
 * manages via withRegistryProvider.
 */
function PreviewController({
  postId,
  postType = 'campaignbridge_template',
  isOpen,
  onRequestClose,
  title,
}: {
  postId: number;
  postType?: string;
  isOpen: boolean;
  onRequestClose: () => void;
  title?: string;
  hasEdits?: boolean;
}): JSX.Element | null {
  const { preview, requestPreview, resetPreview, isStale } =
    useEmailPreview(postId);

  const [rawMeta = {}] = useEntityProp(
    'postType',
    postType,
    'meta',
    postId
  ) as [Record<string, unknown>, unknown, unknown];

  const metaString = (key: string): string | undefined =>
    typeof rawMeta[key] === 'string' && rawMeta[key] !== ''
      ? (rawMeta[key] as string)
      : undefined;

  const subject = metaString('campaignbridge_subject');

  useEffect(() => {
    if (isOpen) {
      void requestPreview();
    } else {
      resetPreview();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  if (!isOpen) {
    return null;
  }

  return (
    <EmailPreviewModal
      isOpen={isOpen}
      onRequestClose={onRequestClose}
      preview={preview}
      onRefresh={() => void requestPreview()}
      title={title}
      subject={subject}
      hasEdits={isStale}
    />
  );
}
