import { BlockEditorProvider } from '@wordpress/block-editor';
import { Popover, SlotFillProvider, SnackbarList } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  ComplementaryArea,
  FullscreenMode,
  InterfaceSkeleton,
} from '@wordpress/interface';
import { ShortcutProvider } from '@wordpress/keyboard-shortcuts';
import { useAutoSaveManager } from '../hooks/useAutoSaveManager';
import { useEditorData } from '../hooks/useEditorData';
import { LAYOUT_CONSTANTS, useEditorLayout } from '../hooks/useEditorLayout';
import { useEditorSettings } from '../hooks/useEditorSettings';
import { useNotices } from '../hooks/useNotices';
import { SIDEBAR_CONSTANTS, useSidebarState } from '../hooks/useSidebarState';
import { blockPatternCategories, blockPatterns } from '../utils/blockPatterns';
import Content from './Content';
import { ErrorState, LoadingState } from './EditorStates';
import Footer from './Footer';
import Header from './Header';
import SecondarySidebar from './Sidebars/SecondarySidebar';
import { SidebarContent, SidebarHeader } from './Sidebars/Sidebar';

/**
 * Editor Chrome Component (Refactored)
 *
 * Lightweight coordinator component that orchestrates the WordPress block editor experience
 * using custom hooks for state management. This component focuses solely on coordination
 * and rendering, with all complex logic extracted to specialized hooks.
 *
 * Features:
 * - Custom hooks for data loading, auto-save, and layout management
 * - Clean separation of concerns with dedicated state components
 * - Centralized constants for maintainability
 * - Simplified JSX with reusable components
 *
 * @example
 * ```jsx
 * <EditorChrome
 *   list={templates}
 *   currentId={1}
 *   loading={false}
 *   onSelect={onSelect}
 *   onNew={onNew}
 *   postId={1}
 *   postType="post"
 * />
 * ```
 */
export default function EditorChrome({
  list,
  currentId,
  loading,
  onSelect,
  onNew,
  postId,
  onBlocksChange = () => {},
  postType = 'post',
}) {
  // Use custom hooks to manage complex state
  const {
    ready,
    blocks,
    error,
    loading: dataLoading,
    setBlocks,
  } = useEditorData(postId, postType);

  const { success, error: errorNotice } = useNotices();
  const {
    settings: editorSettings,
    error: editorSettingsError,
    loading: editorSettingsLoading,
  } = useEditorSettings(postType);

  const { save, saveStatus } = useAutoSaveManager(
    postId,
    onBlocksChange,
    success,
    errorNotice
  );

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
      (
        select('core/preferences') as {
          get: (scope: string, key: string) => unknown;
        }
      ).get(
        SIDEBAR_CONSTANTS.PREFERENCES.FULLSCREEN_MODE,
        'isFullscreen'
      ) as boolean,
    []
  );

  // Get sidebar states using the dedicated hook for consistent state management
  // This replaces manual useSelect calls and ensures proper state restoration from preferences
  const { isPrimaryOpen, isSecondaryOpen, togglePrimary, toggleSecondary } = useSidebarState(
    SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    SIDEBAR_CONSTANTS.SCOPES.SECONDARY
  );

  // Control sidebar widths with JavaScript - aggressively override WordPress inline styles
  useEffect(() => {
    const findAndControlSidebars = () => {
      // Find all sidebar-related elements that might have WordPress inline styles
      const allSidebarElements = document.querySelectorAll(
        '[class*="cb-editor__sidebar"], [class*="complementary-area"], [class*="interface-interface-skeleton__sidebar"]'
      );

      allSidebarElements.forEach(element => {
        const htmlElement = element as HTMLElement;
        // Remove any WordPress inline width styles
        htmlElement.style.removeProperty('width');
      });

      // Find sidebar elements by their class names
      const primarySidebar = document.querySelector(
        '.cb-editor__sidebar.cb-editor__sidebar--primary'
      ) as HTMLElement;
      const secondarySidebar = document.querySelector(
        '.cb-editor__sidebar.cb-editor__sidebar--secondary'
      ) as HTMLElement;

      if (primarySidebar) {
        // Force override with !important-like behavior using setProperty
        primarySidebar.style.setProperty(
          'width',
          isPrimaryOpen ? '18rem' : '0px',
          'important'
        );
        primarySidebar.style.setProperty(
          'overflow',
          isPrimaryOpen ? 'visible' : 'hidden',
          'important'
        );
        primarySidebar.style.setProperty('transition', 'none', 'important');

        // Also override parent elements that might have WordPress styles
        let parent = primarySidebar.parentElement;
        let depth = 0;
        while (parent && parent !== document.body && depth < 5) {
          if (
            parent.classList.contains('complementary-area') ||
            parent.classList.contains(
              'interface-interface-skeleton__sidebar'
            ) ||
            parent.classList.contains('components-panel')
          ) {
            parent.style.setProperty(
              'width',
              isPrimaryOpen ? '18rem' : '0px',
              'important'
            );
            parent.style.setProperty(
              'overflow',
              isPrimaryOpen ? 'visible' : 'hidden',
              'important'
            );
            parent.style.setProperty('transition', 'none', 'important');
          }
          parent = parent.parentElement;
          depth++;
        }
      }

      if (secondarySidebar) {
        // Force override with !important-like behavior using setProperty
        secondarySidebar.style.setProperty(
          'width',
          isSecondaryOpen ? '24rem' : '0px',
          'important'
        );
        secondarySidebar.style.setProperty(
          'overflow',
          isSecondaryOpen ? 'visible' : 'hidden',
          'important'
        );
        secondarySidebar.style.setProperty('transition', 'none', 'important');

        // Also override parent elements that might have WordPress styles
        let parent = secondarySidebar.parentElement;
        let depth = 0;
        while (parent && parent !== document.body && depth < 5) {
          if (
            parent.classList.contains('complementary-area') ||
            parent.classList.contains(
              'interface-interface-skeleton__sidebar'
            ) ||
            parent.classList.contains('components-panel')
          ) {
            parent.style.setProperty(
              'width',
              isSecondaryOpen ? '24rem' : '0px',
              'important'
            );
            parent.style.setProperty(
              'overflow',
              isSecondaryOpen ? 'visible' : 'hidden',
              'important'
            );
            parent.style.setProperty('transition', 'none', 'important');
          }
          parent = parent.parentElement;
          depth++;
        }
      }
    };

    // Run immediately
    findAndControlSidebars();

    // Run after DOM updates
    requestAnimationFrame(findAndControlSidebars);

    // Run multiple times to catch WordPress style updates
    const timeoutIds = [
      setTimeout(findAndControlSidebars, 50),
      setTimeout(findAndControlSidebars, 150),
      setTimeout(findAndControlSidebars, 300),
      setTimeout(findAndControlSidebars, 500),
      setTimeout(findAndControlSidebars, 1000), // Extra delay for slow-loading WordPress styles
    ];

    // Set up a MutationObserver to watch for style changes
    const observer = new MutationObserver(mutations => {
      let shouldUpdate = false;
      mutations.forEach(mutation => {
        if (
          mutation.type === 'attributes' &&
          mutation.attributeName === 'style'
        ) {
          shouldUpdate = true;
        }
      });
      if (shouldUpdate) {
        // Debounce the updates to avoid excessive calls
        setTimeout(findAndControlSidebars, 10);
      }
    });

    // Start observing after a delay to let WordPress initialize
    const observerTimeout = setTimeout(() => {
      const sidebarContainer =
        document.querySelector('.interface-interface-skeleton__body') ||
        document.querySelector('.interface-interface-skeleton') ||
        document.body;
      if (sidebarContainer) {
        observer.observe(sidebarContainer, {
          attributes: true,
          subtree: true,
          attributeFilter: ['style'],
          attributeOldValue: true,
        });
      }
    }, 200);

    return () => {
      timeoutIds.forEach(clearTimeout);
      clearTimeout(observerTimeout);
      observer.disconnect();
    };
  }, [isPrimaryOpen, isSecondaryOpen]);

  // Unified update handler using custom hook
  const handleBlocksUpdate = useCallback(
    next => {
      setBlocks(next);
      if (typeof save.schedule === 'function') {
        save.schedule(next);
      } else {
        save(next);
      }
    },
    [save, setBlocks]
  );

  // Flush pending save on navigation/unload
  useEffect(() => {
    const beforeUnload = () => {
      if (typeof save.flush === 'function') {
        save.flush();
      }
    };
    window.addEventListener('beforeunload', beforeUnload);
    return () => window.removeEventListener('beforeunload', beforeUnload);
  }, [save]);

  // Early returns for loading and error states
  if (!ready) {
    return (
      <LoadingState message={__('Initializing editor…', 'campaignbridge')} />
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

  // Merge editor settings with patterns
  const mergedEditorSettings = {
    ...editorSettings,
    ...blockPatterns,
    ...blockPatternCategories,
  };

  return (
    <ShortcutProvider>
      <SlotFillProvider>
        <FullscreenMode isActive={isFullscreen} />

        {/* Primary sidebar with tabs */}
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

        {/* Secondary sidebar (list view) */}
        <ComplementaryArea {...secondarySidebarProps}>
          <div className={LAYOUT_CONSTANTS.CSS_CLASSES.SIDEBAR_CONTENT}>
            <SecondarySidebar />
          </div>
        </ComplementaryArea>

        {/* Block editor with merged settings */}
        <BlockEditorProvider
          value={blocks}
          onInput={handleBlocksUpdate}
          onChange={handleBlocksUpdate}
          settings={mergedEditorSettings}
        >
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
              />
            }
            content={<Content />}
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
