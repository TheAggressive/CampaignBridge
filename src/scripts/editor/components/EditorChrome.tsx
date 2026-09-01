import { BlockEditorProvider } from '@wordpress/block-editor';
import {
  Popover,
  ResizableBox,
  SlotFillProvider,
  SnackbarList,
} from '@wordpress/components';
import { EntityProvider } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
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
import { useTemplateEditor } from '../hooks/useTemplateEditor';
import { blockPatternCategories, blockPatterns } from '../utils/blockPatterns';
import Content from './Content';
import EditorEffects from './EditorEffects';
import { ErrorState, LoadingState } from './EditorStates';
import Footer from './Footer';
import Header from './Header';
import SecondarySidebar from './Sidebars/SecondarySidebar';
import { SidebarContent, SidebarHeader } from './Sidebars/Sidebar';

const EMAIL_BLOCK_TYPES = [
  'campaignbridge/container',
  'campaignbridge/section',
  'campaignbridge/text',
  'campaignbridge/heading',
  'campaignbridge/image',
  'campaignbridge/button',
  'campaignbridge/divider',
  'campaignbridge/spacer',
  'campaignbridge/post-card',
  'campaignbridge/post-image',
  'campaignbridge/post-title',
  'campaignbridge/post-excerpt',
  'campaignbridge/post-cta',
];

interface EditorChromeProps {
  list: Array<Record<string, unknown>>;
  currentId: number;
  loading: boolean;
  // eslint-disable-next-line no-unused-vars -- Parameter name documents the callback contract.
  onSelect: (id: number | null) => void;
  onNew: () => void;
  postId: number;
  postType?: string;
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
}: EditorChromeProps): JSX.Element {
  const { success, error: errorNotice } = useNotices();
  const handleSaveSuccess = useCallback(
    () => success(__('Template saved.', 'campaignbridge')),
    [success]
  );
  const {
    blocks,
    hasEdits,
    isResolving,
    loadError,
    onChange,
    onInput,
    record,
    saveNow,
    saveStatus,
  } = useTemplateEditor({
    postId,
    postType,
    onSave: handleSaveSuccess,
    onError: errorNotice,
  });
  const {
    settings: editorSettings,
    error: editorSettingsError,
    loading: editorSettingsLoading,
  } = useEditorSettings(postType, postId);
  const {
    skeletonClassName,
    sidebarActiveTab,
    setSidebarActiveTab,
    primarySidebarProps,
    secondarySidebarProps,
    snackbarNotices,
    removeNotice,
  } = useEditorLayout();

  const isFullscreen = useSelect(
    select =>
      (select('core/preferences') as any).get(
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
  } = useSidebarState(
    SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    SIDEBAR_CONSTANTS.SCOPES.SECONDARY
  );
  const handleBlockSelected = useCallback(() => {
    setSidebarActiveTab(SIDEBAR_CONSTANTS.TABS.INSPECTOR);
    openPrimary();
  }, [openPrimary, setSidebarActiveTab]);

  if (isResolving) {
    return (
      <LoadingState message={__('Initializing editor…', 'campaignbridge')} />
    );
  }

  if (loadError || !record) {
    return (
      <ErrorState
        message={__('Unable to load this email template.', 'campaignbridge')}
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
        message={__('Error loading editor settings…', 'campaignbridge')}
      />
    );
  }

  const mergedEditorSettings = {
    ...editorSettings,
    allowedBlockTypes: EMAIL_BLOCK_TYPES,
    __experimentalBlockPatterns: blockPatterns,
    __experimentalBlockPatternCategories: blockPatternCategories,
  };
  const editorStyles = Array.isArray(editorSettings.styles)
    ? (editorSettings.styles as Array<Record<string, unknown>>)
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

        <ResizableBox
          size={{ width: 165 }}
          minWidth={165}
          maxWidth={300}
          enable={false}
        >
          <ComplementaryArea {...secondarySidebarProps}>
            <div className={LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_CONTENT}>
              <SecondarySidebar />
            </div>
          </ComplementaryArea>
        </ResizableBox>

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
          <InterfaceSkeleton
            className={skeletonClassName}
            header={
              <Header
                list={list}
                currentId={currentId}
                loading={loading}
                onSelect={onSelect}
                onNew={onNew}
                isPrimaryOpen={isPrimaryOpen}
                isSecondaryOpen={isSecondaryOpen}
                togglePrimary={togglePrimary}
                toggleSecondary={toggleSecondary}
                hasEdits={hasEdits}
                onSave={saveNow}
                saveStatus={saveStatus}
              />
            }
            content={<Content onSave={saveNow} styles={editorStyles} />}
            sidebar={<ComplementaryArea.Slot {...primarySidebarProps} />}
            secondarySidebar={
              <ComplementaryArea.Slot {...secondarySidebarProps} />
            }
            footer={<Footer />}
          />
        </BlockEditorProvider>

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
